<?php

namespace App\Services;

use App\Models\Destinasi;
use App\Services\Concerns\ItineraryMathAndScoring;
use App\Support\DummyData;
use Carbon\Carbon;

class ItineraryGenerator
{
    use ItineraryMathAndScoring;

    /**
     * Waktu default untuk 3 slot kegiatan harian.
     *
     * @var array<int, array{mulai: string, durasi_default: int, slot: string, label: string}>
     */
    protected array $slotWaktu = [
        0 => ['mulai' => '08:30', 'durasi_default' => 90, 'slot' => 'pagi', 'label' => 'Pagi'],
        1 => ['mulai' => '11:45', 'durasi_default' => 75, 'slot' => 'siang', 'label' => 'Siang'],
        2 => ['mulai' => '15:15', 'durasi_default' => 90, 'slot' => 'sore', 'label' => 'Sore'],
    ];

    /**
     * Generate itinerary dinamis, variatif, dan terstruktur berdasarkan preferensi wisatawan.
     *
     * @param  array{tgl_berangkat: string, tgl_pulang: string, durasi: int, jumlah_orang: int, kategori: string[], tipe_traveler?: string, tingkat_fisik?: string}  $preferensi
     * @param  int|null  $seed  Seed angka acak untuk reproducibility (opsional)
     * @return array{tgl_berangkat: string, tgl_pulang: string, durasi: int, jumlah_orang: int, kategori: string[], status: string, tipe_traveler: string, tingkat_fisik: string, seed: int, jadwal: array}
     */
    public function generate(array $preferensi, ?int $seed = null): array
    {
        $currentSeed = $seed ?? mt_rand(100000, 999999);
        mt_srand($currentSeed);

        $durasi = max(1, min(7, (int) ($preferensi['durasi'] ?? 1)));
        $tipeTraveler = $preferensi['tipe_traveler'] ?? 'couple';
        $tingkatFisik = $preferensi['tingkat_fisik'] ?? 'sedang';

        // Baca destinasi dari model Database Destinasi (fallback ke DummyData jika DB kosong)
        try {
            $dbDestinasi = Destinasi::where('is_active', true)->get()->toArray();
            $allDestinasi = ! empty($dbDestinasi) ? $dbDestinasi : DummyData::destinasi();
        } catch (\Throwable $e) {
            $allDestinasi = DummyData::destinasi();
        }
        $selectedSubkats = $this->normalisasiSubkategori($preferensi['kategori'] ?? []);

        // 1. Scoring awal tiap destinasi berdasarkan kecocokan preferensi dasar
        $scoredPool = [];
        foreach ($allDestinasi as $dest) {
            $baseScore = $this->hitungSkorDasar($dest, $selectedSubkats, $tipeTraveler, $tingkatFisik);
            $scoredPool[] = [
                'destinasi' => $dest,
                'base_score' => $baseScore,
                'is_primary' => $this->isCocokKategori($dest, $selectedSubkats),
            ];
        }

        $startDate = Carbon::parse($preferensi['tgl_berangkat'] ?? now()->toDateString());
        $usedIds = [];
        $jadwal = [];

        for ($day = 1; $day <= $durasi; $day++) {
            $dayDate = $startDate->copy()->addDays($day - 1);
            $dayItems = [];
            $previousDest = null;
            $subkatsHariIni = [];
            $zonaHariIni = null;

            for ($slotIdx = 0; $slotIdx < 3; $slotIdx++) {
                $slotMeta = $this->slotWaktu[$slotIdx];
                $targetSlot = $slotMeta['slot'];

                // Hitung skor dinamis untuk slot ini (mempertimbangkan jarak dari stop sebelumnya & MMR)
                $kandidatSlot = [];
                foreach ($scoredPool as $item) {
                    $d = $item['destinasi'];
                    if (in_array($d['id'], $usedIds) && count($usedIds) < count($allDestinasi)) {
                        continue;
                    }

                    $skor = $item['base_score'];

                    // A. Bonus Kesesuaian Waktu Slot (Pagi / Siang / Sore)
                    $waktuCocok = (isset($d['waktu_terbaik']) && in_array($targetSlot, (array) $d['waktu_terbaik']))
                        || (isset($d['slot_ideal']) && in_array($targetSlot, (array) $d['slot_ideal']));
                    if ($waktuCocok) {
                        $skor += (float) config('gianyar.itinerary.bobot.waktu', 20);
                    }

                    // B. Geoclustering: Kedekatan dengan lokasi sebelumnya atau zona hari ini
                    if ($previousDest !== null) {
                        $jarakKm = $this->haversineKm(
                            (float) $previousDest['lat'],
                            (float) $previousDest['lng'],
                            (float) $d['lat'],
                            (float) $d['lng']
                        );
                        // Semakin dekat, semakin tinggi nilainya (radius optimal < 15km)
                        $kedekatanBonus = max(0, 20 - ($jarakKm * 1.2));
                        $skor += $kedekatanBonus;
                    } elseif ($zonaHariIni !== null && isset($d['zona']) && $d['zona'] === $zonaHariIni) {
                        $skor += (float) config('gianyar.itinerary.bobot.zona', 15);
                    }

                    // C. Diversity Penalty (MMR): Kurangi skor jika subkategori sudah ada di hari ini
                    $destSubkats = (array) ($d['subkategori'] ?? [$d['kategori'] ?? '']);
                    $overlapCount = count(array_intersect($destSubkats, $subkatsHariIni));
                    if ($overlapCount > 0) {
                        $penalty = (float) config('gianyar.itinerary.diversity_penalty', 15) * $overlapCount;
                        $skor -= $penalty;
                    }

                    // D. Jitter acak untuk menghasilkan variasi yang adil di antara kandidat seimbang
                    $jitterMax = (int) config('gianyar.itinerary.jitter_range', 10);
                    $skor += (mt_rand(0, $jitterMax * 10) / 10.0);

                    $kandidatSlot[] = [
                        'destinasi' => $d,
                        'skor' => max(1.0, $skor),
                        'is_primary' => $item['is_primary'],
                    ];
                }

                // Jika filter ketat habis, ambil dari semua yang belum terpakai
                if (empty($kandidatSlot)) {
                    foreach ($allDestinasi as $d) {
                        if (! in_array($d['id'], $usedIds)) {
                            $kandidatSlot[] = ['destinasi' => $d, 'skor' => 10.0, 'is_primary' => false];
                        }
                    }
                }

                // Weighted Random Selection over Top-K
                usort($kandidatSlot, fn ($a, $b) => $b['skor'] <=> $a['skor']);

                $topK = (int) config('gianyar.itinerary.top_k', 4);
                $tier = array_slice($kandidatSlot, 0, min($topK, count($kandidatSlot)));
                $terpilih = $this->pilihWeightedRandom($tier);

                $selectedDest = $terpilih['destinasi'];
                $usedIds[] = $selectedDest['id'];

                // Update state hari ini
                if ($slotIdx === 0 && isset($selectedDest['zona'])) {
                    $zonaHariIni = $selectedDest['zona'];
                }
                foreach ((array) ($selectedDest['subkategori'] ?? [$selectedDest['kategori'] ?? '']) as $sk) {
                    $subkatsHariIni[] = $sk;
                }

                // Hitung estimasi waktu tempuh dari destinasi sebelumnya
                $jarakKm = null;
                $waktuTempuhMenit = null;
                if ($previousDest !== null) {
                    $jarakKm = round($this->haversineKm(
                        (float) $previousDest['lat'],
                        (float) $previousDest['lng'],
                        (float) $selectedDest['lat'],
                        (float) $selectedDest['lng']
                    ), 1);
                    $speed = (float) config('gianyar.itinerary.kecepatan_rata_rata_kmh', 30);
                    $waktuTempuhMenit = max(10, (int) round(($jarakKm / $speed) * 60));
                }

                // Kalkulasi jam mulai & selesai yang realistis
                $durasiKunjungan = (int) ($selectedDest['durasi_kunjungan_menit'] ?? $slotMeta['durasi_default']);
                $jamMulaiFormatted = $slotMeta['mulai'];
                $jamSelesaiCarbon = Carbon::createFromFormat('H:i', $jamMulaiFormatted)->addMinutes($durasiKunjungan);
                $jamSelesaiFormatted = $jamSelesaiCarbon->format('H:i');

                // Generate alasan personalisasi
                $alasan = $this->buatAlasanRekomendasi($selectedDest, $targetSlot, $jarakKm, $tipeTraveler, $terpilih['is_primary']);

                $dayItems[] = [
                    'id' => $selectedDest['id'],
                    'destinasi_id' => $selectedDest['id'],
                    'slug' => $selectedDest['slug'] ?? '',
                    'slot' => $targetSlot,
                    'slot_label' => $slotMeta['label'],
                    'jam_mulai' => $jamMulaiFormatted,
                    'jam_selesai' => $jamSelesaiFormatted,
                    'durasi_menit' => $durasiKunjungan,
                    'jarak_dari_sebelumnya_km' => $jarakKm,
                    'waktu_tempuh_menit' => $waktuTempuhMenit,
                    'alasan' => $alasan,
                    'subkategori' => $selectedDest['subkategori'] ?? [$selectedDest['kategori'] ?? ''],
                    'kecamatan' => $selectedDest['kecamatan'] ?? 'Gianyar',
                    'destinasi' => $selectedDest,
                ];

                $previousDest = $selectedDest;
            }

            $jadwal[] = [
                'hari_ke' => $day,
                'tanggal' => $dayDate->translatedFormat('l, d F Y'),
                'zona' => $zonaHariIni ?? 'Gianyar',
                'items' => $dayItems,
            ];
        }

        return [
            'tgl_berangkat' => $preferensi['tgl_berangkat'] ?? now()->toDateString(),
            'tgl_pulang' => $preferensi['tgl_pulang'] ?? now()->addDays($durasi)->toDateString(),
            'durasi' => $durasi,
            'jumlah_orang' => (int) ($preferensi['jumlah_orang'] ?? 2),
            'kategori' => $preferensi['kategori'] ?? [],
            'tipe_traveler' => $tipeTraveler,
            'tingkat_fisik' => $tingkatFisik,
            'status' => 'draft',
            'seed' => $currentSeed,
            'seed_dasar' => $currentSeed,
            'versi' => 1,
            'jumlah_edit' => 0,
            'jadwal' => $jadwal,
        ];
    }
}
