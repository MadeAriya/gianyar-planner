<?php

namespace App\Services;

use App\Models\GearCategory;
use App\Models\TravelGear;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GearRecommendationService
{
    /**
     * Menghasilkan rekomendasi travel gear yang relevan dengan itinerary pengguna.
     *
     * @param  array  $itinerary  Data itinerary
     * @param  array  $preferensi  Data preferensi pengguna
     * @return array{
     *     wajib: array,
     *     disarankan: array,
     *     opsional: array,
     *     all_items: array,
     *     summary_mitra: array,
     *     total_estimasi: int,
     *     total_deposit: int
     * }
     */
    public function getRecommendations(array $itinerary, array $preferensi = []): array
    {
        $dates = $this->extractDates($itinerary);
        $startDate = $dates[0] ?? Carbon::today()->toDateString();
        $endDate = end($dates) ?: $startDate;
        $durasiHari = max(1, count($dates));
        $jumlahOrang = (int) ($itinerary['jumlah_orang'] ?? $preferensi['jumlah_orang'] ?? 2);
        $tipeTraveler = strtolower($itinerary['tipe_traveler'] ?? $preferensi['tipe_traveler'] ?? 'solo');

        // 1. Ekstrak sinyal aktivitas, subkategori, dan tags dari itinerary
        $signals = $this->extractItinerarySignals($itinerary, $tipeTraveler);

        // 2. Evaluasi aturan pemetaan kebutuhan gear (Rule Engine)
        $neededRules = $this->evaluateGearRules($signals);

        // 3. Load semua kategori & katalog gear dari Mitra yang Approved
        $categories = GearCategory::all()->keyBy('kode');
        $approvedGears = TravelGear::with(['category', 'mitra'])
            ->where('is_active', true)
            ->whereHas('mitra', function ($q) {
                $q->where('status_verifikasi', 'approved');
            })
            ->get();

        // 4. Hitung skor mitra untuk Greedy Set Cover (Mitra Bundling Optimization)
        $mitraCoverageScores = $this->calculateMitraCoverage($neededRules, $approvedGears, $startDate, $endDate);
        $preferredMitraId = array_key_first($mitraCoverageScores);

        // 5. Match ke barang riil di katalog dengan ketersediaan stok
        $results = [
            'wajib' => [],
            'disarankan' => [],
            'opsional' => [],
            'all_items' => [],
            'summary_mitra' => [],
            'total_estimasi' => 0,
            'total_deposit' => 0,
        ];

        foreach ($neededRules as $ruleKey => $rule) {
            $catCode = $rule['kategori_kode'];
            $catModel = $categories[$catCode] ?? null;
            if (! $catModel) {
                continue;
            }

            // Hitung kuantitas
            $qty = ($catModel->sifat === 'per_orang')
                ? max(1, $jumlahOrang)
                : (int) max(1, ceil($jumlahOrang / max(1, (int) $catModel->kelompok_per_unit)));

            // Filter kandidat gear yang cocok kategori & tags, dan stok tersedia
            $candidates = $approvedGears->filter(function (TravelGear $gear) use ($catModel, $rule, $startDate, $endDate, $qty) {
                if ($gear->gear_category_id !== $catModel->id) {
                    return false;
                }
                // Cek tags jika ada spesifik
                if (! empty($rule['tags_filter'])) {
                    $hasMatchingTag = ! empty(array_intersect((array) $gear->tags_aktivitas, $rule['tags_filter']));
                    if (! $hasMatchingTag) {
                        return false;
                    }
                }

                // Cek ketersediaan stok
                return $gear->stokTersedia($startDate, $endDate) >= $qty;
            });

            if ($candidates->isEmpty()) {
                // Fallback: coba tanpa filter tags ketat asal kategori sama & stok cukup
                $candidates = $approvedGears->filter(function (TravelGear $gear) use ($catModel, $startDate, $endDate, $qty) {
                    return $gear->gear_category_id === $catModel->id && $gear->stokTersedia($startDate, $endDate) >= $qty;
                });
            }

            if ($candidates->isEmpty()) {
                continue;
            }

            // Urutkan kandidat: Utamakan mitra pilihan bundling, lalu rating tertinggi
            $sortedCandidates = $candidates->sortByDesc(function (TravelGear $gear) use ($preferredMitraId) {
                $score = 0;
                if ($gear->mitra_id === $preferredMitraId) {
                    $score += 50;
                }
                $score += ($gear->mitra->rating_avg ?? 4.5) * 5;
                $score -= ($gear->tipe === 'sewa' ? ($gear->harga / 10000) : ($gear->harga / 20000));

                return $score;
            })->values();

            $primaryGear = $sortedCandidates->first();
            $alternatives = $sortedCandidates->slice(1, 2)->values();

            // Hitung estimasi biaya item
            $unitCost = ($primaryGear->tipe === 'sewa') ? ($primaryGear->harga * $durasiHari) : $primaryGear->harga;
            $subtotal = $unitCost * $qty;
            $depositSubtotal = ($primaryGear->tipe === 'sewa') ? ($primaryGear->deposit * $qty) : 0;

            $itemData = [
                'rule_key' => $ruleKey,
                'priority' => $rule['priority'],
                'category' => $catModel,
                'gear' => $primaryGear,
                'alternatives' => $alternatives,
                'qty' => $qty,
                'durasi_hari' => $durasiHari,
                'unit_cost' => $unitCost,
                'subtotal' => $subtotal,
                'deposit' => $depositSubtotal,
                'alasan' => $rule['reason'],
                'terkait_hari' => $rule['hari_ke'] ?? [1],
            ];

            $results[$rule['priority']][] = $itemData;
            $results['all_items'][] = $itemData;

            $results['total_estimasi'] += $subtotal;
            $results['total_deposit'] += $depositSubtotal;

            // Summary per Mitra
            $mitraId = $primaryGear->mitra_id;
            if (! isset($results['summary_mitra'][$mitraId])) {
                $results['summary_mitra'][$mitraId] = [
                    'mitra' => $primaryGear->mitra,
                    'items_count' => 0,
                    'subtotal' => 0,
                    'deposit' => 0,
                    'metode_serah' => $primaryGear->mitra->metode_serah,
                ];
            }
            $results['summary_mitra'][$mitraId]['items_count']++;
            $results['summary_mitra'][$mitraId]['subtotal'] += $subtotal;
            $results['summary_mitra'][$mitraId]['deposit'] += $depositSubtotal;
        }

        return $results;
    }

    /**
     * Ekstrak sinyal konteks wisata dari itinerary.
     *
     * @return array{
     *     subkategori: array<string>,
     *     tags: array<string>,
     *     has_water: bool,
     *     has_pura: bool,
     *     has_trekking: bool,
     *     has_sunrise: bool,
     *     has_sawah: bool,
     *     has_beach: bool,
     *     has_kids: bool,
     *     days_with_water: array<int>,
     *     days_with_pura: array<int>,
     *     days_with_trekking: array<int>
     * }
     */
    protected function extractItinerarySignals(array $itinerary, string $tipeTraveler): array
    {
        $signals = [
            'subkategori' => [],
            'tags' => [],
            'has_water' => false,
            'has_pura' => false,
            'has_trekking' => false,
            'has_sunrise' => false,
            'has_sawah' => false,
            'has_beach' => false,
            'has_kids' => in_array($tipeTraveler, ['family', 'keluarga']),
            'days_with_water' => [],
            'days_with_pura' => [],
            'days_with_trekking' => [],
        ];

        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                $dayNum = (int) ($day['hari_ke'] ?? 1);
                if (! empty($day['items']) && is_array($day['items'])) {
                    foreach ($day['items'] as $item) {
                        $subs = (array) ($item['subkategori'] ?? []);
                        $tags = (array) ($item['tags'] ?? []);

                        foreach ($subs as $s) {
                            $signals['subkategori'][] = $s;
                            if (in_array($s, ['air_terjun', 'sungai_rafting', 'melukat'])) {
                                $signals['has_water'] = true;
                                $signals['days_with_water'][] = $dayNum;
                            }
                            if (in_array($s, ['pura_candi', 'melukat', 'situs_sejarah'])) {
                                $signals['has_pura'] = true;
                                $signals['days_with_pura'][] = $dayNum;
                            }
                            if (in_array($s, ['bukit_trekking', 'sawah', 'cycling'])) {
                                $signals['has_trekking'] = true;
                                $signals['days_with_trekking'][] = $dayNum;
                            }
                            if ($s === 'sawah') {
                                $signals['has_sawah'] = true;
                            }
                            if ($s === 'pantai') {
                                $signals['has_beach'] = true;
                            }
                            if (in_array($s, ['satwa_edukasi', 'keluarga'])) {
                                $signals['has_kids'] = true;
                            }
                        }

                        foreach ($tags as $t) {
                            $signals['tags'][] = $t;
                            if (in_array($t, ['sunrise', 'pagi_buta'])) {
                                $signals['has_sunrise'] = true;
                            }
                            if (in_array($t, ['ramah-anak', 'keluarga'])) {
                                $signals['has_kids'] = true;
                            }
                        }
                    }
                }
            }
        }

        $signals['subkategori'] = array_values(array_unique($signals['subkategori']));
        $signals['tags'] = array_values(array_unique($signals['tags']));
        $signals['days_with_water'] = array_values(array_unique($signals['days_with_water']));
        $signals['days_with_pura'] = array_values(array_unique($signals['days_with_pura']));
        $signals['days_with_trekking'] = array_values(array_unique($signals['days_with_trekking']));

        return $signals;
    }

    /**
     * Evaluasi aturan rekomendasi gear berdasarkan sinyal yang terdeteksi.
     *
     * @return array<string, array{kategori_kode: string, priority: string, reason: string, tags_filter: array, hari_ke: array}>
     */
    protected function evaluateGearRules(array $signals): array
    {
        $rules = [];

        // 1. ATURAN PURA & TEMPAT SUCI -> Pakaian Adat (WAJIB)
        if ($signals['has_pura']) {
            $rules['pakaian_adat_pura'] = [
                'kategori_kode' => 'pakaian_adat',
                'priority' => 'wajib',
                'reason' => 'Aturan adat Bali mewajibkan kain kamen dan selendang/senteng yang rapi saat memasuki kawasan pura dan situs petirtaan suci.',
                'tags_filter' => ['pura_candi', 'melukat', 'situs_sejarah'],
                'hari_ke' => ! empty($signals['days_with_pura']) ? $signals['days_with_pura'] : [1],
            ];
        }

        // 2. ATURAN AIR TERJUN & RAFTING -> Sandal Trekking / Water Shoes & Dry Bag (WAJIB)
        if ($signals['has_water']) {
            $rules['alas_kaki_water'] = [
                'kategori_kode' => 'alas_kaki',
                'priority' => 'wajib',
                'reason' => 'Medan bebatuan basah, berlumut, dan licin di air terjun memerlukan alas kaki anti-selip demi keamanan kaki Anda.',
                'tags_filter' => ['air_terjun', 'sungai_rafting'],
                'hari_ke' => ! empty($signals['days_with_water']) ? $signals['days_with_water'] : [1],
            ];

            $rules['dry_bag_water'] = [
                'kategori_kode' => 'perlengkapan_air',
                'priority' => 'wajib',
                'reason' => 'Melindungi smartphone, dompet, dan baju ganti agar tetap kering dari percikan air terjun dan jeram sungai.',
                'tags_filter' => ['air_terjun', 'sungai_rafting', 'pantai'],
                'hari_ke' => ! empty($signals['days_with_water']) ? $signals['days_with_water'] : [1],
            ];
        }

        // 3. ATURAN TREKKING BUKIT & SAWAH -> Trekking Pole & Topi Rimba (DISARANKAN)
        if ($signals['has_trekking']) {
            $rules['trekking_pole'] = [
                'kategori_kode' => 'trekking',
                'priority' => 'disarankan',
                'reason' => 'Membantu menopang keseimbangan lutut pada tanjakan dan turunan galengan sawah berundak atau perbukitan.',
                'tags_filter' => ['bukit_trekking', 'sawah', 'outdoor'],
                'hari_ke' => ! empty($signals['days_with_trekking']) ? $signals['days_with_trekking'] : [1],
            ];

            $rules['pelindung_kepala'] = [
                'kategori_kode' => 'perlindungan',
                'priority' => 'disarankan',
                'reason' => 'Jalur pematang sawah dan bukit terbuka tanpa naungan pohon membutuhkan pelindung dari sengatan sinar UV.',
                'tags_filter' => ['bukit_trekking', 'sawah', 'pantai'],
                'hari_ke' => ! empty($signals['days_with_trekking']) ? $signals['days_with_trekking'] : [1],
            ];
        }

        // 4. ATURAN SUNRISE / PAGI BUTA -> Headlamp & Windbreaker (DISARANKAN)
        if ($signals['has_sunrise']) {
            $rules['headlamp_sunrise'] = [
                'kategori_kode' => 'trekking',
                'priority' => 'disarankan',
                'reason' => 'Penerangan bebas genggaman tangan untuk memulai jalan pagi sebelum matahari terbit di perbukitan Campuhan.',
                'tags_filter' => ['sunrise', 'situs_sejarah'],
                'hari_ke' => [1],
            ];

            $rules['jaket_windbreaker'] = [
                'kategori_kode' => 'pakaian_cuaca',
                'priority' => 'disarankan',
                'reason' => 'Menahan terpaan hembusan angin dingin bukit pada waktu subuh menjelang terbitnya mentari.',
                'tags_filter' => ['sunrise', 'bukit_trekking'],
                'hari_ke' => [1],
            ];
        }

        // 5. ATURAN KELUARGA / ANAK -> Perlengkapan Bayi / Pelampung Anak (DISARANKAN)
        if ($signals['has_kids']) {
            $rules['carrier_bayi'] = [
                'kategori_kode' => 'anak',
                'priority' => 'disarankan',
                'reason' => 'Menjaga kenyamanan dan keamanan si kecil saat menjelajah taman satwa atau jalur pedesaan Gianyar.',
                'tags_filter' => ['keluarga', 'ramah_anak'],
                'hari_ke' => [1],
            ];
        }

        // 6. ATURAN CUACA TROPIS UMUM -> Jas Hujan Ponco (DISARANKAN)
        $rules['jas_hujan_umum'] = [
            'kategori_kode' => 'pakaian_cuaca',
            'priority' => 'disarankan',
            'reason' => 'Antisipasi perubahan cuaca tropis Gianyar dan hujan gerimis mendadak di area perbukitan dan lembah.',
            'tags_filter' => ['outdoor', 'hujan', 'air_terjun'],
            'hari_ke' => [1],
        ];

        // 7. ATURAN DOKUMENTASI & FOTOGRAFI -> Action Cam 4K / Stabilizer (OPSIONAL)
        if ($signals['has_water'] || $signals['has_trekking']) {
            $rules['action_cam_doc'] = [
                'kategori_kode' => 'foto',
                'priority' => 'opsional',
                'reason' => 'Kamera aksi waterproof berkualitas tinggi untuk mengabadikan momen berharga di air terjun tanpa khawatir rusak.',
                'tags_filter' => ['air_terjun', 'fotografi', 'sungai_rafting', 'atv_swing'],
                'hari_ke' => [1],
            ];
        }

        // 8. DAYA EKSTRA & POWER BANK (OPSIONAL)
        $rules['power_bank_travel'] = [
            'kategori_kode' => 'umum',
            'priority' => 'opsional',
            'reason' => 'Menjaga baterai smartphone dan navigasi maps tetap terisi penuh seharian berpetualang.',
            'tags_filter' => ['fotografi', 'outdoor', 'keluarga'],
            'hari_ke' => [1],
        ];

        return $rules;
    }

    /**
     * Hitung skor kelengkapan persediaan mitra untuk optimasi Greedy Set Cover (Bundling).
     *
     * @return array<int, int> [mitra_id => score]
     */
    protected function calculateMitraCoverage(array $rules, Collection $gears, string $startDate, string $endDate): array
    {
        $scores = [];
        $requiredCatCodes = array_column($rules, 'kategori_kode');

        foreach ($gears as $gear) {
            $mitraId = $gear->mitra_id;
            if (! isset($scores[$mitraId])) {
                $scores[$mitraId] = 0;
            }

            if (in_array($gear->category->kode, $requiredCatCodes)) {
                if ($gear->stokTersedia($startDate, $endDate) > 0) {
                    $scores[$mitraId] += 10;
                }
            }
        }

        arsort($scores);

        return $scores;
    }

    /**
     * Ekstrak tanggal dari itinerary.
     *
     * @return array<string>
     */
    public function extractDates(array $itinerary): array
    {
        $dates = [];
        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                if (! empty($day['tanggal'])) {
                    try {
                        $dates[] = Carbon::parse($day['tanggal'])->toDateString();
                    } catch (\Exception $e) {
                    }
                }
            }
        }
        if (empty($dates)) {
            $start = Carbon::today();
            $durasi = (int) ($itinerary['durasi'] ?? 1);
            for ($i = 0; $i < $durasi; $i++) {
                $dates[] = $start->copy()->addDays($i)->toDateString();
            }
        }

        return array_values(array_unique($dates));
    }
}
