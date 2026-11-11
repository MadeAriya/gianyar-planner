<?php

namespace App\Services;

use App\Models\Destinasi;
use App\Support\DummyData;
use Carbon\Carbon;

class ItineraryGenerator
{
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
     * Pipeline Algoritma:
     * 1. Category Normalization & Preference Expansion
     * 2. Candidate Matching & Multi-factor Scoring (kategori, tipe traveler, tingkat fisik, popularitas)
     * 3. Day-by-Day Geoclustering & Anchor Selection
     * 4. Haversine Distance Calculation & Proximity Optimization
     * 5. Slot Time-of-Day Suitability (Pagi/Siang/Sore)
     * 6. Diversity Penalty (MMR) untuk mencegah repetisi jenis wisata di hari yang sama
     * 7. Weighted Random Sampling over Top-K dengan Jitter Noise untuk variasi tinggi
     * 8. Human-friendly "Alasan Rekomendasi" & Travel Time Estimation
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
        $neededSlots = $durasi * config('gianyar.itinerary.slots_per_hari', 3);

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
                    'slot' => $targetSlot,
                    'slot_label' => $slotMeta['label'],
                    'jam_mulai' => $jamMulaiFormatted,
                    'jam_selesai' => $jamSelesaiFormatted,
                    'durasi_menit' => $durasiKunjungan,
                    'jarak_dari_sebelumnya_km' => $jarakKm,
                    'waktu_tempuh_menit' => $waktuTempuhMenit,
                    'alasan' => $alasan,
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
            'jadwal' => $jadwal,
        ];
    }

    /**
     * Normalisasi kategori input (bisa berupa subkategori spesifik atau kategori umum seperti alam/budaya).
     *
     * @param  string[]  $input
     * @return string[]
     */
    protected function normalisasiSubkategori(array $input): array
    {
        $result = [];
        $katList = DummyData::kategoriList();

        foreach ($input as $item) {
            // Jika user memilih kategori level-1 (misal 'alam', 'budaya', 'seni')
            if (isset($katList[$item])) {
                foreach (array_keys($katList[$item]['subkategori']) as $subKey) {
                    $result[] = $subKey;
                }
            } else {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Memeriksa apakah destinasi cocok dengan subkategori pilihan.
     *
     * @param  array<string, mixed>  $dest
     * @param  string[]  $selectedSubkats
     */
    protected function isCocokKategori(array $dest, array $selectedSubkats): bool
    {
        if (empty($selectedSubkats)) {
            return true;
        }

        $destSubkats = (array) ($dest['subkategori'] ?? [$dest['kategori'] ?? '']);
        $katUtama = $dest['kategori_utama'] ?? '';

        return count(array_intersect($destSubkats, $selectedSubkats)) > 0
            || in_array($katUtama, $selectedSubkats);
    }

    /**
     * Hitung skor dasar destinasi berdasarkan kecocokan subkategori, profil traveler, dan tingkat fisik.
     *
     * @param  array<string, mixed>  $dest
     * @param  string[]  $selectedSubkats
     */
    protected function hitungSkorDasar(array $dest, array $selectedSubkats, string $tipeTraveler, string $tingkatFisik): float
    {
        $skor = 10.0; // Base score

        // 1. Kesesuaian Kategori (Bobot ~35-40)
        if ($this->isCocokKategori($dest, $selectedSubkats)) {
            $skor += (float) config('gianyar.itinerary.bobot.kategori', 35);
        }

        // 2. Kesesuaian Tipe Traveler (solo, couple, family, group)
        $cocokTraveler = (array) ($dest['cocok_untuk'] ?? []);
        if (in_array($tipeTraveler, $cocokTraveler)) {
            $skor += (float) config('gianyar.itinerary.bobot.cocok_traveler', 10);
        }

        // 3. Kesesuaian Tingkat Fisik
        $destFisik = $dest['tingkat_fisik'] ?? 'sedang';
        $bobotFisik = (float) config('gianyar.itinerary.bobot.fisik', 10);
        if ($tingkatFisik === 'santai') {
            if ($destFisik === 'mudah') {
                $skor += $bobotFisik;
            } elseif ($destFisik === 'sulit') {
                $skor -= 8;
            }
        } elseif ($tingkatFisik === 'aktif') {
            if ($destFisik === 'sedang' || $destFisik === 'sulit') {
                $skor += $bobotFisik;
            }
        } else {
            // sedang
            if ($destFisik === 'sedang' || $destFisik === 'mudah') {
                $skor += ($bobotFisik * 0.8);
            }
        }

        // 4. Popularitas (1-10) -> skala hingga 10 poin
        $pop = (float) ($dest['popularitas'] ?? 5);
        $skor += ($pop / 10.0) * (float) config('gianyar.itinerary.bobot.popularitas', 10);

        return $skor;
    }

    /**
     * Weighted Random Selection: Memilih satu kandidat secara proporsional terhadap skornya.
     *
     * @param  array<int, array{destinasi: array, skor: float, is_primary: bool}>  $candidates
     * @return array{destinasi: array, skor: float, is_primary: bool}
     */
    protected function pilihWeightedRandom(array $candidates): array
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $totalWeight = array_sum(array_column($candidates, 'skor'));
        if ($totalWeight <= 0) {
            return $candidates[array_rand($candidates)];
        }

        $rand = (mt_rand(1, 10000) / 10000.0) * $totalWeight;
        $cumulative = 0.0;

        foreach ($candidates as $cand) {
            $cumulative += $cand['skor'];
            if ($rand <= $cumulative) {
                return $cand;
            }
        }

        return $candidates[count($candidates) - 1];
    }

    /**
     * Hitung jarak Haversine antara dua koordinat geografis (dalam km).
     */
    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Merangkai kalimat alasan rekomendasi yang natural untuk UI itinerary.
     *
     * @param  array<string, mixed>  $dest
     */
    protected function buatAlasanRekomendasi(
        array $dest,
        string $slot,
        ?float $jarakKm,
        string $tipeTraveler,
        bool $isPrimary
    ): string {
        $nama = $dest['nama_destinasi'] ?? $dest['nama'];

        if ($slot === 'pagi') {
            if ($dest['kategori_utama'] === 'alam' || in_array('sawah', (array) ($dest['subkategori'] ?? []))) {
                return "Suasana pagi hari yang sejuk dan cerah memberikan pencahayaan alami terbaik di {$nama}.";
            }
            if (in_array('melukat', (array) ($dest['subkategori'] ?? []))) {
                return 'Pagi hari adalah waktu paling hening dan sakral untuk prosesi pembersihan diri (melukat).';
            }

            return 'Waktu pagi ideal untuk memulai hari sebelum lokasi dipadati pengunjung.';
        }

        if ($slot === 'siang') {
            if ($jarakKm !== null && $jarakKm <= 8.0) {
                return "Hanya {$jarakKm} km dari destinasi sebelumnya, efisien rute dan cocok untuk rehat siang.";
            }
            if ($dest['kategori_utama'] === 'kuliner') {
                return 'Pilihan santap siang istimewa dengan cita rasa autentik khas Gianyar.';
            }

            return 'Lokasi nyaman untuk menikmati siang hari dengan keteduhan pepohonan tropis.';
        }

        // Sore
        if (in_array('sunset', (array) ($dest['tags'] ?? [])) || in_array('pantai', (array) ($dest['subkategori'] ?? []))) {
            return 'Spot sempurna menyambut senja dan pemandangan matahari terbenam yang memukau.';
        }

        if ($jarakKm !== null && $jarakKm <= 10.0) {
            return "Rute searah perjalanan sore (berjarak {$jarakKm} km), menutup petualangan hari ini dengan santai.";
        }

        return 'Penutup perjalanan hari ini yang berkesan untuk rileks bersama.';
    }
}
