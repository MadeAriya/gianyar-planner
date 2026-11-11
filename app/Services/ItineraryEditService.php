<?php

namespace App\Services;

use App\Models\Destinasi;
use App\Models\Itinerary;
use App\Models\ItineraryExclusion;
use App\Models\ItineraryItem;
use App\Models\ItineraryRevision;
use App\Services\Concerns\ItineraryMathAndScoring;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ItineraryEditService
{
    use ItineraryMathAndScoring;

    public function __construct(
        protected GearRecommendationService $gearService,
        protected GuideMatchingService $guideMatcher
    ) {}

    /**
     * Hitung ulang seluruh jadwal waktu, estimasi jarak, dan durasi secara murni dan deterministik.
     *
     * @param  array<int, array<string, mixed>>  $items  Daftar item kunjungan
     * @param  array<string, mixed>  $config  Konfigurasi jadwal
     * @param  string  $jamMulaiHari  Jam keberangkatan harian (default 08:00)
     * @return array<int, array<string, mixed>> Items yang telah dihitung ulang
     */
    public function recalculateSchedule(array $items, array $config = [], string $jamMulaiHari = '08:00'): array
    {
        if (empty($items)) {
            return [];
        }

        // Kelompokkan item berdasarkan hari_ke dan urutkan
        $speed = (float) ($config['kecepatan_rata_rata_kmh'] ?? config('gianyar.itinerary.kecepatan_rata_rata_kmh', 30));
        $waktuMakanMenit = (int) ($config['waktu_makan_menit'] ?? config('gianyar.itinerary.waktu_makan_menit', 60));

        // Kelompokkan per hari
        $byDay = [];
        foreach ($items as $item) {
            $hariKe = (int) ($item['hari_ke'] ?? 1);
            $byDay[$hariKe][] = $item;
        }
        ksort($byDay);

        $recalculated = [];

        foreach ($byDay as $hariKe => $dayItems) {
            // Urutkan item pada hari ini berdasarkan urutan
            usort($dayItems, fn ($a, $b) => ($a['urutan'] ?? 1) <=> ($b['urutan'] ?? 1));

            $currentTime = Carbon::createFromFormat('H:i', $jamMulaiHari);
            $previousDest = null;
            $hadLunch = false;

            foreach ($dayItems as $idx => $it) {
                $dest = $it['destinasi'] ?? null;
                if (! $dest && isset($it['destinasi_id'])) {
                    $dest = Destinasi::find($it['destinasi_id'])?->toArray();
                }

                $jarakKm = null;
                $waktuTempuhMenit = 0;

                if ($previousDest !== null && $dest !== null) {
                    $jarakKm = round($this->haversineKm(
                        (float) $previousDest['lat'],
                        (float) $previousDest['lng'],
                        (float) $dest['lat'],
                        (float) $dest['lng']
                    ), 1);
                    $waktuTempuhMenit = max(10, (int) round(($jarakKm / $speed) * 60));
                }

                // Tambahkan waktu tempuh perjalanan
                $arrivalTime = $currentTime->copy()->addMinutes($waktuTempuhMenit);

                // Istirahat makan siang otomatis jika jadwal melintasi rentang 11:45 - 13:00
                if (! $hadLunch && $arrivalTime->format('H:i') >= '11:45' && $idx > 0) {
                    $arrivalTime->addMinutes($waktuMakanMenit);
                    $hadLunch = true;
                }

                // Tentukan durasi kunjungan (hormati durasi_override_menit jika ada)
                $durasiEfektif = (int) ($it['durasi_override_menit'] ?? $dest['durasi_kunjungan_menit'] ?? $it['durasi_menit'] ?? 90);
                $departureTime = $arrivalTime->copy()->addMinutes($durasiEfektif);

                // Tentukan label slot berdasarkan jam tiba
                $arrHour = (int) $arrivalTime->format('H');
                if ($arrHour < 11) {
                    $slot = 'pagi';
                    $slotLabel = 'Pagi';
                } elseif ($arrHour < 15) {
                    $slot = 'siang';
                    $slotLabel = 'Siang';
                } else {
                    $slot = 'sore';
                    $slotLabel = 'Sore';
                }

                $alasan = $this->buatAlasanRekomendasi($dest ?: [], $slot, $jarakKm, 'couple', true);

                $updatedItem = array_merge($it, [
                    'hari_ke' => $hariKe,
                    'urutan' => $idx + 1,
                    'slot' => $slot,
                    'slot_label' => $slotLabel,
                    'jam_mulai' => $arrivalTime->format('H:i'),
                    'jam_selesai' => $departureTime->format('H:i'),
                    'durasi_menit' => $durasiEfektif,
                    'jarak_dari_sebelumnya_km' => $jarakKm,
                    'waktu_tempuh_menit' => $waktuTempuhMenit,
                    'alasan' => $it['alasan'] ?? $alasan,
                    'destinasi' => $dest,
                ]);

                $recalculated[] = $updatedItem;
                $currentTime = $departureTime;
                $previousDest = $dest;
            }
        }

        return $recalculated;
    }

    /**
     * Validasi komprehensif dua level (Hard Errors & Soft Warnings).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $config
     * @return array{is_valid: bool, hard_errors: string[], soft_warnings: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function validateItinerary(array $items, array $config = [], ?Itinerary $itinerary = null): array
    {
        $hardErrors = [];
        $softWarnings = [];

        // 1. HARD CHECK: Status Itinerary Terkunci
        if ($itinerary && $itinerary->isLocked()) {
            $hardErrors[] = 'Itinerary berstatus terkunci karena pesanan telah dibayar/lunas dan tidak dapat diubah.';
        }

        $seenDestIds = [];
        $totalJarak = 0.0;
        $totalTiket = 0;
        $maxJamSelesai = '00:00';
        $subkategoriPerHari = [];

        $durasiMaks = $itinerary ? (int) $itinerary->durasi : 7;
        $jamAkhirBatas = $config['jam_akhir'] ?? config('gianyar.itinerary.jam_akhir', '18:30');

        foreach ($items as $idx => $it) {
            $destId = $it['destinasi_id'] ?? $it['id'] ?? null;
            $hariKe = (int) ($it['hari_ke'] ?? 1);
            $dest = $it['destinasi'] ?? null;
            if (! $dest && $destId) {
                $dest = Destinasi::find($destId)?->toArray();
            }

            // Hard Check 2: Destinasi Duplikat
            if ($destId) {
                if (in_array($destId, $seenDestIds)) {
                    $nama = $dest['nama_destinasi'] ?? $dest['nama'] ?? "ID {$destId}";
                    $hardErrors[] = "Destinasi ganda terdeteksi: '{$nama}' sudah dijadwalkan di hari lain.";
                }
                $seenDestIds[] = $destId;
            }

            // Hard Check 3: Hari di luar durasi
            if ($hariKe <= 0 || $hariKe > $durasiMaks) {
                $hardErrors[] = "Hari ke-{$hariKe} berada di luar durasi perjalanan ({$durasiMaks} hari).";
            }

            if ($dest) {
                $totalTiket += (int) ($dest['harga_tiket'] ?? 0);
                $jamBuka = $dest['jam_buka'] ?? '08:00';
                $jamTutup = $dest['jam_tutup'] ?? '18:00';

                // Hard Check 4: Destinasi tutup seharian pada jadwal kunjungan
                $jamMulai = $it['jam_mulai'] ?? '09:00';
                $jamSelesai = $it['jam_selesai'] ?? '10:30';

                if ($jamMulai > $jamTutup) {
                    $nama = $dest['nama_destinasi'] ?? $dest['nama'];
                    $softWarnings[] = [
                        'item_index' => $idx,
                        'destinasi_id' => $destId,
                        'tipe' => 'jam_tutup',
                        'pesan' => "'{$nama}' tutup pada {$jamTutup}, namun dijadwalkan tiba pukul {$jamMulai}. Disarankan memindahkan ke slot lebih awal.",
                    ];
                }

                // Soft Warning: Jarak antar destinasi jauh (> 18 km)
                $jarak = (float) ($it['jarak_dari_sebelumnya_km'] ?? 0);
                $totalJarak += $jarak;
                if ($jarak > 18.0) {
                    $softWarnings[] = [
                        'item_index' => $idx,
                        'destinasi_id' => $destId,
                        'tipe' => 'jarak_jauh',
                        'pesan' => "Perjalanan ke '{$dest['nama']}' berjarak cukup jauh ({$jarak} km). Estimasi waktu tempuh ±".($it['waktu_tempuh_menit'] ?? 40).' menit.',
                    ];
                }

                // Soft Warning: Lebih dari 2 subkategori sama dalam 1 hari
                $subs = (array) ($dest['subkategori'] ?? [$dest['kategori'] ?? '']);
                foreach ($subs as $s) {
                    $subkategoriPerHari[$hariKe][$s] = ($subkategoriPerHari[$hariKe][$s] ?? 0) + 1;
                    if ($subkategoriPerHari[$hariKe][$s] > 2) {
                        $softWarnings[] = [
                            'item_index' => $idx,
                            'destinasi_id' => $destId,
                            'tipe' => 'repetisi_kategori',
                            'pesan' => "Hari ke-{$hariKe} memiliki lebih dari 2 destinasi bertema sama ('{$s}'). Pertimbangkan variasi aktivitas.",
                        ];
                    }
                }

                // Lacak jam selesai maksimum
                if ($jamSelesai > $maxJamSelesai) {
                    $maxJamSelesai = $jamSelesai;
                }

                // Soft Warning: Selesai melewati jam santai harian
                if ($jamSelesai > $jamAkhirBatas) {
                    $softWarnings[] = [
                        'item_index' => $idx,
                        'destinasi_id' => $destId,
                        'tipe' => 'kemalaman',
                        'pesan' => "Aktivitas di '{$dest['nama']}' selesai pukul {$jamSelesai} (melewati jam santai sore {$jamAkhirBatas}).",
                    ];
                }
            }
        }

        return [
            'is_valid' => empty($hardErrors),
            'hard_errors' => array_values(array_unique($hardErrors)),
            'soft_warnings' => $softWarnings,
            'metrics' => [
                'total_destinasi' => count($items),
                'total_jarak_km' => round($totalJarak, 1),
                'total_tiket_per_orang' => $totalTiket,
                'jam_selesai_terakhir' => $maxJamSelesai,
            ],
        ];
    }

    /**
     * Rekomendasi 3-5 kandidat pengganti cerdas (Smart Swap) untuk suatu slot kunjungan.
     *
     * @param  string|null  $alasan  Alasan penolakan (terlalu_jauh, terlalu_mahal, terlalu_berat, kurang_menarik, dll.)
     * @return array<int, array<string, mixed>>
     */
    public function suggestReplacements(
        Itinerary $itinerary,
        int|ItineraryItem $itemToReplace,
        ?string $alasan = null,
        int $limit = 4,
        ?int $seed = null
    ): array {
        $item = is_int($itemToReplace) ? ItineraryItem::findOrFail($itemToReplace) : $itemToReplace;
        $destOld = Destinasi::find($item->destinasi_id);
        if (! $destOld) {
            return [];
        }

        // Kumpulkan ID destinasi yang dilarang (ada di itinerary saat ini ATAU ada di blacklist/exclusions)
        $currentItinDestIds = $itinerary->items()->pluck('destinasi_id')->all();
        $excludedDestIds = $itinerary->exclusions()->pluck('destinasi_id')->all();
        $forbiddenIds = array_unique(array_merge($currentItinDestIds, $excludedDestIds));

        // Tentukan tetangga sebelum dan sesudah slot ini
        $dayItems = $itinerary->items()->where('hari_ke', $item->hari_ke)->orderBy('urutan')->get();
        $prevItem = $dayItems->firstWhere('urutan', $item->urutan - 1);
        $nextItem = $dayItems->firstWhere('urutan', $item->urutan + 1);

        $prevDest = $prevItem ? Destinasi::find($prevItem->destinasi_id) : null;
        $nextDest = $nextItem ? Destinasi::find($nextItem->destinasi_id) : null;

        // Ambil kandidat aktif yang belum digunakan
        $candidatesQuery = Destinasi::where('is_active', true)->whereNotIn('id', $forbiddenIds);

        // Filter fisik jika alasan terlalu_berat
        if ($alasan === 'terlalu_berat') {
            $candidatesQuery->whereIn('tingkat_fisik', ['mudah', 'sedang']);
        }

        // Filter harga jika alasan terlalu_mahal
        if ($alasan === 'terlalu_mahal') {
            $candidatesQuery->where('harga_tiket', '<=', max(0, $destOld->harga_tiket - 5000));
        }

        $candidates = $candidatesQuery->get();

        // Fallback relaksasi jika kandidat kurang dari 3
        if ($candidates->count() < 3) {
            $candidates = Destinasi::where('is_active', true)
                ->whereNotIn('id', $currentItinDestIds)
                ->get();
        }

        $tipeTraveler = $itinerary->tipe_traveler ?? 'couple';
        $tingkatFisik = $itinerary->tingkat_fisik ?? 'sedang';
        $selectedSubkats = (array) ($itinerary->kategori ?? []);

        $scoredCandidates = [];
        $seedVal = $seed ?? ($itinerary->seed_dasar + $itinerary->jumlah_edit + $item->id);

        foreach ($candidates as $cand) {
            $d = $cand->toArray();
            $score = $this->hitungSkorDasar($d, $selectedSubkats, $tipeTraveler, $tingkatFisik);

            // Kedekatan dengan previous destination
            $jarakPrev = null;
            if ($prevDest) {
                $jarakPrev = $this->haversineKm((float) $prevDest->lat, (float) $prevDest->lng, (float) $cand->lat, (float) $cand->lng);
                $kedekatanBonus = max(0, 25 - ($jarakPrev * 1.5));
                $score += $kedekatanBonus;
            }

            // Kedekatan dengan next destination (meminimalkan detour rute)
            if ($nextDest) {
                $jarakNext = $this->haversineKm((float) $cand->lat, (float) $cand->lng, (float) $nextDest->lat, (float) $nextDest->lng);
                $score += max(0, 15 - ($jarakNext * 1.2));
            }

            // Pembobotan Berdasarkan Alasan
            if ($alasan === 'terlalu_jauh') {
                if ($jarakPrev !== null) {
                    $score += max(0, 35 - ($jarakPrev * 2.5)); // Bobot kedekatan dilipatgandakan
                }
            } elseif ($alasan === 'kurang_menarik') {
                // Jangan pilih subkategori yang sama dengan yang ditolak
                $oldSubs = (array) ($destOld->subkategori ?? []);
                $candSubs = (array) ($cand->subkategori ?? []);
                if (count(array_intersect($oldSubs, $candSubs)) > 0) {
                    $score -= 20.0;
                } else {
                    $score += 15.0;
                }
            } elseif ($alasan === 'terlalu_mahal') {
                if ($cand->harga_tiket === 0) {
                    $score += 25.0; // Bonus gratis tiket
                } else {
                    $score += max(0, 20 - ($cand->harga_tiket / 10000));
                }
            }

            // Bonus waktu slot
            $targetSlot = $item->slot ?: 'siang';
            if (in_array($targetSlot, (array) ($cand->waktu_terbaik ?? []))) {
                $score += 15.0;
            }

            // Pseudo-random reproducible jitter berbasis seed
            $hash = crc32($seedVal.'_'.$cand->id);
            $jitter = (($hash % 15) - 7);
            $finalScore = max(1.0, $score + $jitter);

            // Hitung dampak rute (perubahan jarak dan estimasi jam selesai)
            $oldJarak = (float) ($item->jarak_dari_sebelumnya_km ?? 5.0);
            $deltaJarak = $jarakPrev !== null ? round($jarakPrev - $oldJarak, 1) : 0.0;
            $deltaTeks = ($deltaJarak > 0 ? "+{$deltaJarak} km" : ($deltaJarak < 0 ? "{$deltaJarak} km" : 'Sama'));

            // Buat alasan rekomendasi transparan
            $alasanText = [];
            if ($jarakPrev !== null) {
                $alasanText[] = round($jarakPrev, 1).' km dari stop sebelumnya';
            }
            $alasanText[] = 'Tiket Rp '.number_format($cand->harga_tiket, 0, ',', '.');
            $alasanText[] = 'Buka s/d '.($cand->jam_tutup ?: '18:00');

            $scoredCandidates[] = [
                'destinasi' => $cand,
                'skor' => $finalScore,
                'jarak_km' => $jarakPrev !== null ? round($jarakPrev, 1) : null,
                'delta_jarak_km' => $deltaJarak,
                'alasan' => implode(' · ', $alasanText),
                'dampak' => "Rute: {$deltaTeks} · Estimasi durasi: {$cand->durasi_kunjungan_menit} mnt",
            ];
        }

        // Urutkan skor tertinggi dan ambil Top-K
        usort($scoredCandidates, fn ($a, $b) => $b['skor'] <=> $a['skor']);

        return array_slice($scoredCandidates, 0, max(3, min(5, $limit)));
    }

    /**
     * Ganti satu destinasi dengan destinasi baru (Smart Swap) secara atomik.
     */
    public function replaceItem(
        Itinerary $itinerary,
        int $itemId,
        int $newDestinasiId,
        ?string $alasan = null,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $itemId, $newDestinasiId, $alasan) {
            $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);
            $oldDest = Destinasi::findOrFail($item->destinasi_id);
            $newDest = Destinasi::findOrFail($newDestinasiId);

            // Blacklist destinasi lama pada itinerary ini jika ada alasan
            if ($alasan) {
                ItineraryExclusion::updateOrCreate(
                    ['itinerary_id' => $itinerary->id, 'destinasi_id' => $oldDest->id],
                    ['alasan' => $alasan]
                );
            }

            // Update item
            $item->update([
                'destinasi_id' => $newDest->id,
                'sumber' => 'diganti',
                'durasi_menit' => $newDest->durasi_kunjungan_menit ?: 90,
                'durasi_override_menit' => null,
            ]);

            $ringkasan = "Mengganti '{$oldDest->nama}' dengan '{$newDest->nama}'".($alasan ? " (alasan: {$alasan})" : '');

            return $this->finalizeEditOperation($itinerary, 'ganti', $ringkasan);
        });
    }

    /**
     * Hapus destinasi dari itinerary dan hitung ulang jadwal sisa.
     */
    public function removeItem(
        Itinerary $itinerary,
        int $itemId,
        ?string $alasan = null,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $itemId, $alasan) {
            $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);
            $dest = Destinasi::find($item->destinasi_id);
            $destNama = $dest ? $dest->nama : 'Destinasi';

            if ($alasan && $dest) {
                ItineraryExclusion::updateOrCreate(
                    ['itinerary_id' => $itinerary->id, 'destinasi_id' => $dest->id],
                    ['alasan' => $alasan]
                );
            }

            $item->delete();

            // Re-order urutan item pada hari tersebut
            $dayItems = ItineraryItem::where('itinerary_id', $itinerary->id)
                ->where('hari_ke', $item->hari_ke)
                ->orderBy('urutan')
                ->get();

            foreach ($dayItems as $idx => $di) {
                $di->update(['urutan' => $idx + 1]);
            }

            $ringkasan = "Menghapus '{$destNama}' dari Hari ke-{$item->hari_ke}";

            return $this->finalizeEditOperation($itinerary, 'hapus', $ringkasan);
        });
    }

    /**
     * Tambah destinasi baru ke hari tertentu dengan algoritma Cheapest Insertion.
     */
    public function addItem(
        Itinerary $itinerary,
        int $destinasiId,
        int $hariKe,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $destinasiId, $hariKe) {
            $newDest = Destinasi::findOrFail($destinasiId);
            $dayItems = ItineraryItem::where('itinerary_id', $itinerary->id)
                ->where('hari_ke', $hariKe)
                ->orderBy('urutan')
                ->get();

            // Cari posisi sisipan termurah (Cheapest Insertion)
            $bestPosition = $dayItems->count() + 1;
            $minExtraDistance = PHP_FLOAT_MAX;

            if ($dayItems->isEmpty()) {
                $bestPosition = 1;
            } else {
                // Coba setiap celah penyisipan i (1 s/d N+1)
                for ($pos = 1; $pos <= $dayItems->count() + 1; $pos++) {
                    $prev = $pos > 1 ? Destinasi::find($dayItems[$pos - 2]->destinasi_id) : null;
                    $next = $pos <= $dayItems->count() ? Destinasi::find($dayItems[$pos - 1]->destinasi_id) : null;

                    $extraDistance = 0.0;
                    if ($prev && $next) {
                        $origDist = $this->haversineKm((float) $prev->lat, (float) $prev->lng, (float) $next->lat, (float) $next->lng);
                        $newDist = $this->haversineKm((float) $prev->lat, (float) $prev->lng, (float) $newDest->lat, (float) $newDest->lng)
                            + $this->haversineKm((float) $newDest->lat, (float) $newDest->lng, (float) $next->lat, (float) $next->lng);
                        $extraDistance = max(0, $newDist - $origDist);
                    } elseif ($prev) {
                        $extraDistance = $this->haversineKm((float) $prev->lat, (float) $prev->lng, (float) $newDest->lat, (float) $newDest->lng);
                    } elseif ($next) {
                        $extraDistance = $this->haversineKm((float) $newDest->lat, (float) $newDest->lng, (float) $next->lat, (float) $next->lng);
                    }

                    if ($extraDistance < $minExtraDistance) {
                        $minExtraDistance = $extraDistance;
                        $bestPosition = $pos;
                    }
                }
            }

            // Geser item setelahnya ke urutan berikutnya
            foreach ($dayItems as $di) {
                if ($di->urutan >= $bestPosition) {
                    $di->increment('urutan');
                }
            }

            // Buat item baru
            ItineraryItem::create([
                'itinerary_id' => $itinerary->id,
                'destinasi_id' => $newDest->id,
                'hari_ke' => $hariKe,
                'urutan' => $bestPosition,
                'slot' => 'siang',
                'slot_label' => 'Siang',
                'jam_mulai' => '12:00',
                'jam_selesai' => '13:30',
                'durasi_menit' => $newDest->durasi_kunjungan_menit ?: 90,
                'is_locked' => false,
                'sumber' => 'manual',
            ]);

            $ringkasan = "Menambahkan '{$newDest->nama}' ke Hari ke-{$hariKe} (posisi ke-{$bestPosition})";

            return $this->finalizeEditOperation($itinerary, 'tambah', $ringkasan);
        });
    }

    /**
     * Urutkan ulang (reorder) atau pindahkan hari item secara fleksibel.
     *
     * @param  array<int, array{id: int, hari_ke: int, urutan: int}>  $itemsOrder
     */
    public function reorderItems(
        Itinerary $itinerary,
        array $itemsOrder,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $itemsOrder) {
            foreach ($itemsOrder as $orderData) {
                ItineraryItem::where('itinerary_id', $itinerary->id)
                    ->where('id', $orderData['id'])
                    ->update([
                        'hari_ke' => (int) $orderData['hari_ke'],
                        'urutan' => (int) $orderData['urutan'],
                        'sumber' => 'pindah',
                    ]);
            }

            $ringkasan = 'Menata ulang urutan rute perjalanan';

            return $this->finalizeEditOperation($itinerary, 'pindah', $ringkasan);
        });
    }

    /**
     * Kunci atau lepas kunci (pin/lock) destinasi agar kebal dari regenerate.
     */
    public function toggleLockItem(
        Itinerary $itinerary,
        int $itemId,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $itemId) {
            $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);
            $newLockState = ! $item->is_locked;
            $item->update(['is_locked' => $newLockState]);

            $destNama = $item->destinasi?->nama ?? 'Destinasi';
            $ringkasan = ($newLockState ? 'Mengunci' : 'Membuka kunci')." '{$destNama}'";

            return $this->finalizeEditOperation($itinerary, 'kunci', $ringkasan);
        });
    }

    /**
     * Ubah durasi kunjungan khusus (durasi_override_menit) pada satu destinasi.
     */
    public function updateDuration(
        Itinerary $itinerary,
        int $itemId,
        int $durationMinutes,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $itemId, $durationMinutes) {
            $item = ItineraryItem::where('itinerary_id', $itinerary->id)->findOrFail($itemId);
            $durationMinutes = max(30, min(360, $durationMinutes));
            $item->update([
                'durasi_override_menit' => $durationMinutes,
                'durasi_menit' => $durationMinutes,
            ]);

            $ringkasan = "Mengubah durasi kunjungan '{$item->destinasi?->nama}' menjadi {$durationMinutes} menit";

            return $this->finalizeEditOperation($itinerary, 'durasi', $ringkasan);
        });
    }

    /**
     * Regenerate sebagian: (a) Hari ini saja, atau (b) Semua kecuali yang dikunci.
     *
     * @param  string  $scope  'hari' atau 'semua_kecuali_terkunci'
     */
    public function regeneratePartial(
        Itinerary $itinerary,
        string $scope,
        ?int $hariKe = null,
        ?int $clientVersion = null
    ): array {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary, $scope, $hariKe) {
            $newSeed = $itinerary->seed_dasar + $itinerary->jumlah_edit + 1;
            mt_srand($newSeed);

            // Dapatkan item yang dikunci (TIDAK BOLEH BERUBAH)
            $lockedItems = $itinerary->items()->where('is_locked', true)->get();
            $lockedDestIds = $lockedItems->pluck('destinasi_id')->all();
            $blacklistIds = $itinerary->exclusions()->pluck('destinasi_id')->all();

            $forbiddenIds = array_unique(array_merge($lockedDestIds, $blacklistIds));

            // Ambil kandidat destinasi yang belum dipakai oleh item terkunci atau blacklist
            $availableDest = Destinasi::where('is_active', true)->whereNotIn('id', $forbiddenIds)->get();

            $itemsToReplaceQuery = $itinerary->items()->where('is_locked', false);
            if ($scope === 'hari' && $hariKe !== null) {
                $itemsToReplaceQuery->where('hari_ke', $hariKe);
            }

            $itemsToReplace = $itemsToReplaceQuery->get();
            $usedIdsInRegen = $forbiddenIds;

            foreach ($itemsToReplace as $it) {
                // Pilih pengganti acak berbobot
                $candidates = $availableDest->whereNotIn('id', $usedIdsInRegen)->values();
                if ($candidates->isEmpty()) {
                    continue;
                }

                $scoredPool = [];
                foreach ($candidates as $cand) {
                    $score = $this->hitungSkorDasar(
                        $cand->toArray(),
                        (array) ($itinerary->kategori ?? []),
                        $itinerary->tipe_traveler ?? 'couple',
                        $itinerary->tingkat_fisik ?? 'sedang'
                    );
                    $scoredPool[] = ['destinasi' => $cand->toArray(), 'skor' => $score];
                }

                usort($scoredPool, fn ($a, $b) => $b['skor'] <=> $a['skor']);
                $topK = array_slice($scoredPool, 0, min(4, count($scoredPool)));
                $picked = $this->pilihWeightedRandom($topK);

                $newDest = $picked['destinasi'];
                $usedIdsInRegen[] = $newDest['id'];

                $it->update([
                    'destinasi_id' => $newDest['id'],
                    'sumber' => 'regenerate_sebagian',
                    'durasi_menit' => $newDest['durasi_kunjungan_menit'] ?: 90,
                    'durasi_override_menit' => null,
                ]);
            }

            $ringkasan = ($scope === 'hari')
                ? "Regenerate ulang Hari ke-{$hariKe} (menjaga destinasi terkunci)"
                : 'Regenerate seluruh destinasi yang tidak dikunci';

            return $this->finalizeEditOperation($itinerary, 'regenerate_sebagian', $ringkasan);
        });
    }

    /**
     * Kembalikan ke snapshot versi sebelumnya (Undo).
     */
    public function undo(Itinerary $itinerary, ?int $clientVersion = null): array
    {
        $this->ensureCanEdit($itinerary, $clientVersion);

        if ($itinerary->versi <= 1) {
            throw new HttpException(400, 'Sudah berada pada versi awal itinerary. Tidak ada aksi yang dapat di-undo.');
        }

        return DB::transaction(function () use ($itinerary) {
            $targetVersion = $itinerary->versi - 1;
            $revision = ItineraryRevision::where('itinerary_id', $itinerary->id)
                ->where('versi', $targetVersion)
                ->first();

            if (! $revision) {
                throw new HttpException(404, "Snapshot versi {$targetVersion} tidak ditemukan.");
            }

            $this->restoreSnapshot($itinerary, $revision->snapshot);
            $itinerary->update([
                'versi' => $targetVersion,
                'jumlah_edit' => $itinerary->jumlah_edit + 1,
            ]);

            return $this->buildEditResponse($itinerary, "Undo berhasil: Kembali ke versi {$targetVersion}");
        });
    }

    /**
     * Maju ke snapshot versi berikutnya (Redo).
     */
    public function redo(Itinerary $itinerary, ?int $clientVersion = null): array
    {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary) {
            $targetVersion = $itinerary->versi + 1;
            $revision = ItineraryRevision::where('itinerary_id', $itinerary->id)
                ->where('versi', $targetVersion)
                ->first();

            if (! $revision) {
                throw new HttpException(400, 'Sudah berada pada versi perubahan terbaru. Tidak ada aksi yang dapat di-redo.');
            }

            $this->restoreSnapshot($itinerary, $revision->snapshot);
            $itinerary->update([
                'versi' => $targetVersion,
                'jumlah_edit' => $itinerary->jumlah_edit + 1,
            ]);

            return $this->buildEditResponse($itinerary, "Redo berhasil: Maju ke versi {$targetVersion}");
        });
    }

    /**
     * Reset itinerary kembali ke versi awal (Versi 1).
     */
    public function resetToInitial(Itinerary $itinerary, ?int $clientVersion = null): array
    {
        $this->ensureCanEdit($itinerary, $clientVersion);

        return DB::transaction(function () use ($itinerary) {
            $initialRevision = ItineraryRevision::where('itinerary_id', $itinerary->id)
                ->where('versi', 1)
                ->first();

            if (! $initialRevision) {
                throw new HttpException(404, 'Snapshot versi awal tidak ditemukan.');
            }

            $this->restoreSnapshot($itinerary, $initialRevision->snapshot);
            $itinerary->update([
                'versi' => 1,
                'jumlah_edit' => $itinerary->jumlah_edit + 1,
            ]);

            // Hapus revisi setelah versi 1
            ItineraryRevision::where('itinerary_id', $itinerary->id)->where('versi', '>', 1)->delete();

            return $this->buildEditResponse($itinerary, 'Itinerary berhasil di-reset ke jadwal awal.');
        });
    }

    /**
     * Duplikat itinerary terkunci menjadi draft baru yang dapat diedit bebas.
     */
    public function duplicateItinerary(Itinerary $originalItinerary, $user): Itinerary
    {
        return DB::transaction(function () use ($originalItinerary, $user) {
            $newItinerary = Itinerary::create([
                'user_id' => $user->id,
                'tgl_berangkat' => $originalItinerary->tgl_berangkat,
                'tgl_pulang' => $originalItinerary->tgl_pulang,
                'durasi' => $originalItinerary->durasi,
                'jumlah_orang' => $originalItinerary->jumlah_orang,
                'kategori' => $originalItinerary->kategori,
                'tipe_traveler' => $originalItinerary->tipe_traveler,
                'tingkat_fisik' => $originalItinerary->tingkat_fisik,
                'status' => 'draft', // Draft baru bebas diedit
                'seed' => mt_rand(100000, 999999),
                'seed_dasar' => $originalItinerary->seed_dasar,
                'versi' => 1,
                'jumlah_edit' => 0,
            ]);

            foreach ($originalItinerary->items as $it) {
                ItineraryItem::create([
                    'itinerary_id' => $newItinerary->id,
                    'destinasi_id' => $it->destinasi_id,
                    'hari_ke' => $it->hari_ke,
                    'urutan' => $it->urutan,
                    'slot' => $it->slot,
                    'slot_label' => $it->slot_label,
                    'jam_mulai' => $it->jam_mulai,
                    'jam_selesai' => $it->jam_selesai,
                    'durasi_menit' => $it->durasi_menit,
                    'durasi_override_menit' => $it->durasi_override_menit,
                    'jarak_dari_sebelumnya_km' => $it->jarak_dari_sebelumnya_km,
                    'waktu_tempuh_menit' => $it->waktu_tempuh_menit,
                    'alasan' => $it->alasan,
                    'is_locked' => false,
                    'sumber' => 'duplikat',
                ]);
            }

            // Simpan snapshot awal versi 1
            $this->recordSnapshot($newItinerary, 'generate', 'Duplikasi dari itinerary #'.$originalItinerary->id);

            return $newItinerary;
        });
    }

    /**
     * Hitung sinkronisasi gear & kompatibilitas guide setelah terjadi edit.
     *
     * @return array{gear_diff: array, guide_warning: ?string}
     */
    public function syncGearAndGuideImpact(Itinerary $itinerary): array
    {
        $itinArray = $this->formatItineraryForService($itinerary);
        $newGearRec = $this->gearService->getRecommendations($itinArray);

        // Cek kompatibilitas guide terpilih jika ada pesanan aktif
        $guideWarning = null;
        if ($itinerary->pesanan && $itinerary->pesanan->pesananGuide) {
            $guide = $itinerary->pesanan->pesananGuide->guide;
            if ($guide) {
                // Periksa apakah kecamatan destinasi baru berada di luar area_layanan guide
                $allDestKecamatan = $itinerary->items()->with('destinasi')->get()
                    ->pluck('destinasi.kecamatan')->filter()->unique()->all();

                $destOutsideArea = array_filter($allDestKecamatan, fn ($k) => strcasecmp((string) $k, (string) $guide->area_layanan) !== 0);
                if (! empty($destOutsideArea)) {
                    $guideWarning = "Peringatan: Itinerary kini memuat destinasi di luar area layanan utama Tour Guide Anda ({$guide->area_layanan}). Harap diskusikan penyesuaian rute dengan pemandu.";
                }
            }
        }

        return [
            'gear_diff' => $newGearRec,
            'new_gear_recommendations' => $newGearRec,
            'guide_warning' => $guideWarning,
        ];
    }

    /**
     * Finalisasi operasi edit: hitung ulang jadwal, simpan snapshot, dan validasi.
     */
    protected function finalizeEditOperation(Itinerary $itinerary, string $aksi, string $ringkasan): array
    {
        // 1. Hitung ulang jadwal seluruh item
        $currentItems = $itinerary->items()->with('destinasi')->get()->toArray();
        $recalculated = $this->recalculateSchedule($currentItems);

        foreach ($recalculated as $rc) {
            ItineraryItem::where('id', $rc['id'])->update([
                'hari_ke' => $rc['hari_ke'],
                'urutan' => $rc['urutan'],
                'slot' => $rc['slot'],
                'slot_label' => $rc['slot_label'],
                'jam_mulai' => $rc['jam_mulai'],
                'jam_selesai' => $rc['jam_selesai'],
                'durasi_menit' => $rc['durasi_menit'],
                'jarak_dari_sebelumnya_km' => $rc['jarak_dari_sebelumnya_km'],
                'waktu_tempuh_menit' => $rc['waktu_tempuh_menit'],
                'alasan' => $rc['alasan'],
            ]);
        }

        // 2. Increment versi & jumlah_edit
        $newVersion = $itinerary->versi + 1;
        $itinerary->update([
            'versi' => $newVersion,
            'jumlah_edit' => $itinerary->jumlah_edit + 1,
        ]);

        // 3. Simpan snapshot revisi
        $this->recordSnapshot($itinerary, $aksi, $ringkasan);

        return $this->buildEditResponse($itinerary, $ringkasan);
    }

    /**
     * Simpan snapshot state revisi (maksimal 20 snapshot terakhir).
     */
    public function recordSnapshot(Itinerary $itinerary, string $aksi, string $ringkasan): ItineraryRevision
    {
        $itemsSnapshot = $itinerary->items()->with('destinasi')->orderBy('hari_ke')->orderBy('urutan')->get()->toArray();

        $revision = ItineraryRevision::create([
            'itinerary_id' => $itinerary->id,
            'versi' => $itinerary->versi,
            'snapshot' => [
                'metadata' => [
                    'versi' => $itinerary->versi,
                    'durasi' => $itinerary->durasi,
                    'jumlah_orang' => $itinerary->jumlah_orang,
                    'seed' => $itinerary->seed,
                ],
                'items' => $itemsSnapshot,
            ],
            'aksi' => $aksi,
            'ringkasan_perubahan' => $ringkasan,
        ]);

        // Pertahankan maksimal 20 revisi terakhir
        $oldRevisions = ItineraryRevision::where('itinerary_id', $itinerary->id)
            ->orderBy('versi', 'desc')
            ->skip(20)
            ->take(50)
            ->pluck('id');

        if ($oldRevisions->isNotEmpty()) {
            ItineraryRevision::whereIn('id', $oldRevisions)->delete();
        }

        return $revision;
    }

    /**
     * Kembalikan state dari JSON snapshot revisi.
     */
    protected function restoreSnapshot(Itinerary $itinerary, array $snapshot): void
    {
        $itemsData = $snapshot['items'] ?? [];

        // Hapus item saat ini dan buat ulang sesuai snapshot
        $itinerary->items()->delete();

        foreach ($itemsData as $it) {
            ItineraryItem::create([
                'itinerary_id' => $itinerary->id,
                'destinasi_id' => $it['destinasi_id'],
                'hari_ke' => $it['hari_ke'],
                'urutan' => $it['urutan'],
                'slot' => $it['slot'] ?? 'pagi',
                'slot_label' => $it['slot_label'] ?? 'Pagi',
                'jam_mulai' => $it['jam_mulai'] ?? '08:30',
                'jam_selesai' => $it['jam_selesai'] ?? '10:00',
                'durasi_menit' => $it['durasi_menit'] ?? 90,
                'durasi_override_menit' => $it['durasi_override_menit'] ?? null,
                'jarak_dari_sebelumnya_km' => $it['jarak_dari_sebelumnya_km'] ?? null,
                'waktu_tempuh_menit' => $it['waktu_tempuh_menit'] ?? null,
                'alasan' => $it['alasan'] ?? null,
                'is_locked' => $it['is_locked'] ?? false,
                'sumber' => $it['sumber'] ?? 'undo',
                'catatan_user' => $it['catatan_user'] ?? null,
            ]);
        }
    }

    /**
     * Pastikan hak akses edit: tolak jika terkunci atau versi usang (Optimistic Locking).
     */
    public function ensureCanEdit(Itinerary $itinerary, ?int $clientVersion = null): void
    {
        if ($itinerary->isLocked()) {
            throw new HttpException(403, 'Akses ditolak: Itinerary ini berstatus terkunci karena pesanan telah lunas. Anda dapat menduplikatnya sebagai draft baru.');
        }

        if ($clientVersion !== null && (int) $clientVersion !== (int) $itinerary->versi) {
            throw new HttpException(409, "Konflik versi: Itinerary telah diperbarui di sesi lain (Versi saat ini: {$itinerary->versi}, Versi Anda: {$clientVersion}). Harap muat ulang halaman.");
        }
    }

    /**
     * Bangun response standar JSON pasca-edit.
     */
    protected function buildEditResponse(Itinerary $itinerary, string $pesanSukses): array
    {
        $items = $itinerary->items()->with('destinasi')->orderBy('hari_ke')->orderBy('urutan')->get()->toArray();
        $validation = $this->validateItinerary($items, [], $itinerary);
        $sync = $this->syncGearAndGuideImpact($itinerary);

        return [
            'success' => true,
            'message' => $pesanSukses,
            'versi' => $itinerary->versi,
            'jumlah_edit' => $itinerary->jumlah_edit,
            'can_undo' => $itinerary->versi > 1,
            'can_redo' => ItineraryRevision::where('itinerary_id', $itinerary->id)->where('versi', '>', $itinerary->versi)->exists(),
            'validation' => $validation,
            'items' => $items,
            'sync' => $sync,
        ];
    }

    /**
     * Konversi model Itinerary ke format array yang diharapkan oleh Gear & Guide services.
     */
    public function formatItineraryForService(Itinerary $itinerary): array
    {
        $itemsByDay = [];
        foreach ($itinerary->items()->with('destinasi')->orderBy('hari_ke')->orderBy('urutan')->get() as $it) {
            $hariKe = $it->hari_ke;
            $dest = $it->destinasi;
            $destArray = $dest ? $dest->toArray() : [];
            if ($dest) {
                $destArray['nama_destinasi'] = $dest->nama;
            }

            $itemsByDay[$hariKe][] = [
                'id' => $it->id,
                'item_id' => $it->id,
                'destinasi_id' => $dest?->id,
                'hari_ke' => $it->hari_ke,
                'urutan' => $it->urutan,
                'slot' => $it->slot,
                'slot_label' => $it->slot_label,
                'jam_mulai' => $it->jam_mulai,
                'jam_selesai' => $it->jam_selesai,
                'durasi_menit' => $it->durasi_menit,
                'durasi_override_menit' => $it->durasi_override_menit,
                'jarak_dari_sebelumnya_km' => $it->jarak_dari_sebelumnya_km,
                'waktu_tempuh_menit' => $it->waktu_tempuh_menit,
                'alasan' => $it->alasan,
                'is_locked' => (bool) $it->is_locked,
                'sumber' => $it->sumber,
                'catatan_user' => $it->catatan_user,
                'destinasi' => $destArray,
                'nama' => $dest?->nama,
                'subkategori' => $dest?->subkategori ?? [],
                'tags' => $dest?->tags ?? [],
                'kecamatan' => $dest?->kecamatan ?? 'Gianyar',
            ];
        }

        $jadwal = [];
        $startDate = Carbon::parse($itinerary->tgl_berangkat);
        for ($d = 1; $d <= $itinerary->durasi; $d++) {
            $jadwal[] = [
                'hari_ke' => $d,
                'tanggal' => $startDate->copy()->addDays($d - 1)->toDateString(),
                'items' => $itemsByDay[$d] ?? [],
            ];
        }

        return [
            'id' => $itinerary->id,
            'durasi' => $itinerary->durasi,
            'jumlah_orang' => $itinerary->jumlah_orang,
            'tipe_traveler' => $itinerary->tipe_traveler,
            'tingkat_fisik' => $itinerary->tingkat_fisik,
            'kategori' => $itinerary->kategori,
            'jadwal' => $jadwal,
        ];
    }
}
