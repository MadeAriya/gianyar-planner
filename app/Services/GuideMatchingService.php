<?php

namespace App\Services;

use App\Models\GuideAvailability;
use App\Models\TourGuide;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GuideMatchingService
{
    /**
     * Merekomendasikan daftar Tour Guide terbaik dan terverifikasi untuk sebuah itinerary.
     *
     * @param  array  $itinerary  Data itinerary (jadwal, durasi, jumlah_orang, dll.)
     * @param  array  $preferensi  Data preferensi pengguna
     * @param  int  $limit  Jumlah rekomendasi yang diinginkan
     * @param  string|null  $seed  Seed pengacak deterministik untuk variasi sesi
     * @return Collection Kumpulan model TourGuide beserta 'match_score' dan 'alasan_rekomendasi'
     */
    public function getRecommendations(array $itinerary, array $preferensi = [], int $limit = 5, ?string $seed = null): Collection
    {
        $dates = $this->extractItineraryDates($itinerary);
        $subkategoriSet = $this->extractSubcategories($itinerary);
        $kecamatanList = $this->extractKecamatan($itinerary);
        $jumlahOrang = (int) ($itinerary['jumlah_orang'] ?? $preferensi['jumlah_orang'] ?? 2);
        $seedString = $seed ?? (session()->isStarted() ? session()->getId() : (string) ($itinerary['seed'] ?? 'gianyar_guide_seed'));

        // 1. HARD FILTER
        // - status_verifikasi approved
        // - user active
        // - max_peserta >= jumlah_orang
        $candidateGuides = TourGuide::with(['user'])
            ->where('status_verifikasi', 'approved')
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->where('max_peserta', '>=', $jumlahOrang)
            ->get();

        if ($candidateGuides->isEmpty()) {
            return collect();
        }

        // Filter ketersediaan tanggal
        $availableGuides = $candidateGuides->filter(function (TourGuide $guide) use ($dates) {
            return $this->isAvailableForDates($guide->id, $dates);
        });

        if ($availableGuides->isEmpty()) {
            return collect();
        }

        // 2. MULTI-FACTOR SCORING & TRANSPARENT REASONS
        $scoredGuides = $availableGuides->map(function (TourGuide $guide) use ($subkategoriSet, $kecamatanList, $seedString) {
            $scores = $this->calculateScores($guide, $subkategoriSet, $kecamatanList);

            // Pseudo-random reproducible jitter (+/- 8 poin) agar hasil variatif antar-sesi
            $hash = crc32($seedString.'_'.$guide->id);
            $jitter = (($hash % 17) - 8); // -8 sampai +8
            $finalScore = round(min(100, max(10, $scores['composite'] + $jitter)), 2);

            $guide->match_score = $finalScore;
            $guide->score_breakdown = $scores;
            $guide->alasan_rekomendasi = $this->generateReason($guide, $scores, $subkategoriSet);

            // Pastikan atribut kompatibel dengan view lama ($guide->tarif, $guide->rating)
            $guide->tarif = $guide->tarif_per_hari;
            $guide->rating = $guide->rating_avg;

            return $guide;
        });

        // 3. SORT & TOP-K
        return $scoredGuides->sortByDesc('match_score')->take($limit)->values();
    }

    /**
     * Memeriksa apakah guide berstatus 'tersedia' pada seluruh rentang tanggal itinerary.
     *
     * @param  array<string>  $dates
     */
    public function isAvailableForDates(int $guideId, array $dates): bool
    {
        if (empty($dates)) {
            return true;
        }

        $availabilities = GuideAvailability::where('guide_id', $guideId)
            ->whereIn('tanggal', $dates)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->tanggal)->toDateString();
            });

        foreach ($dates as $date) {
            if (! isset($availabilities[$date]) || $availabilities[$date]->status !== 'tersedia') {
                return false;
            }
        }

        return true;
    }

    /**
     * Hitung sub-skor multi-faktor.
     *
     * @return array{composite: float, spesialisasi: float, area: float, rating: float, acceptance: float, budget: float, fairness: float}
     */
    protected function calculateScores(TourGuide $guide, array $subkategoriSet, array $kecamatanList): array
    {
        // A. Spesialisasi vs Subkategori Itinerary (Bobot 35%)
        $guideSpecs = (array) ($guide->spesialisasi ?? []);
        $matchedSpecs = array_intersect($guideSpecs, $subkategoriSet);
        $specScore = 0.0;
        if (! empty($guideSpecs)) {
            $specScore = (count($matchedSpecs) / max(1, count($guideSpecs))) * 100;
        }

        // B. Area Layanan vs Kecamatan Destinasi (Bobot 20%)
        $areaScore = 30.0;
        if (! empty($kecamatanList)) {
            $guideArea = strtolower(trim($guide->area_layanan));
            $majorityKecamatan = $this->getMajorityValue($kecamatanList);
            if ($majorityKecamatan && strtolower(trim($majorityKecamatan)) === $guideArea) {
                $areaScore = 100.0;
            } elseif (in_array($guideArea, array_map('strtolower', $kecamatanList))) {
                $areaScore = 75.0;
            }
        }

        // C. Bayesian Smoothed Rating (Bobot 20%)
        // R_bayes = (v*R + m*C) / (v + m), prior m=5, C=4.8
        $v = (float) $guide->jumlah_ulasan;
        $R = (float) $guide->rating_avg;
        $m = 5.0;
        $C = 4.8;
        $rBayes = ($v * $R + $m * $C) / ($v + $m);
        $ratingScore = ($rBayes / 5.0) * 100.0;

        // D. Acceptance Rate (Bobot 10%)
        $acceptanceScore = (float) ($guide->acceptance_rate ?? 100.0);

        // E. Budget & Fleksibilitas Bahasa (Bobot 10%)
        // Tarif wajar Gianyar (Rp 350.000 - Rp 600.000 mendapat nilai maksimal)
        $tarif = $guide->tarif_per_hari;
        $budgetScore = 80.0;
        if ($tarif <= 450000) {
            $budgetScore = 100.0;
        } elseif ($tarif <= 550000) {
            $budgetScore = 90.0;
        }

        // F. Fairness Boost (Bobot 5%)
        // Pemandu dengan job lebih sedikit mendapat dorongan kesempatan agar rotasi adil
        $jobs = (int) $guide->jumlah_job_selesai;
        $fairnessScore = max(0.0, 100.0 - ($jobs * 0.7));

        // Total Komposit
        $composite = (0.35 * $specScore)
            + (0.20 * $areaScore)
            + (0.20 * $ratingScore)
            + (0.10 * $acceptanceScore)
            + (0.10 * $budgetScore)
            + (0.05 * $fairnessScore);

        return [
            'composite' => round($composite, 2),
            'spesialisasi' => round($specScore, 2),
            'area' => round($areaScore, 2),
            'rating' => round($ratingScore, 2),
            'acceptance' => round($acceptanceScore, 2),
            'budget' => round($budgetScore, 2),
            'fairness' => round($fairnessScore, 2),
            'matched_specs' => array_values($matchedSpecs),
        ];
    }

    /**
     * Buat kalimat alasan rekomendasi transparan yang mudah dipahami wisatawan.
     */
    protected function generateReason(TourGuide $guide, array $scores, array $subkategoriSet): string
    {
        $reasons = [];

        if (! empty($scores['matched_specs'])) {
            $specsHuman = array_map(function ($s) {
                return ucwords(str_replace('_', ' ', $s));
            }, array_slice($scores['matched_specs'], 0, 2));
            $reasons[] = 'Spesialis '.implode(' & ', $specsHuman);
        }

        if ($scores['area'] >= 75) {
            $reasons[] = 'Berpengalaman di wilayah '.$guide->area_layanan;
        }

        $reasons[] = 'Rating '.number_format($guide->rating_avg, 2).' ('.$guide->jumlah_ulasan.' ulasan)';

        if ($guide->acceptance_rate >= 95) {
            $reasons[] = 'Respon cepat ('.number_format($guide->acceptance_rate, 0).'%)';
        }

        return implode(' · ', $reasons);
    }

    /**
     * Ekstrak tanggal-tanggal unik perjalanan dari struktur itinerary.
     *
     * @return array<string>
     */
    public function extractItineraryDates(array $itinerary): array
    {
        $dates = [];
        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                if (! empty($day['tanggal'])) {
                    try {
                        $dates[] = Carbon::parse($day['tanggal'])->toDateString();
                    } catch (\Exception $e) {
                        // ignore unparseable
                    }
                }
            }
        }

        // Fallback jika tanggal jadwal belum ada
        if (empty($dates)) {
            $startDate = Carbon::today();
            $durasi = (int) ($itinerary['durasi'] ?? 1);
            for ($i = 0; $i < $durasi; $i++) {
                $dates[] = $startDate->copy()->addDays($i)->toDateString();
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Ekstrak himpunan subkategori dari destinasi yang ada di itinerary.
     *
     * @return array<string>
     */
    protected function extractSubcategories(array $itinerary): array
    {
        $subcategories = [];
        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                if (! empty($day['items']) && is_array($day['items'])) {
                    foreach ($day['items'] as $item) {
                        if (! empty($item['subkategori'])) {
                            foreach ((array) $item['subkategori'] as $sub) {
                                $subcategories[] = $sub;
                            }
                        }
                    }
                }
            }
        }

        // Tambahkan preferensi subkategori user jika ada
        if (! empty($itinerary['subkategori'])) {
            foreach ((array) $itinerary['subkategori'] as $sub) {
                $subcategories[] = $sub;
            }
        }

        return array_values(array_unique($subcategories));
    }

    /**
     * Ekstrak daftar kecamatan destinasi dari itinerary.
     *
     * @return array<string>
     */
    protected function extractKecamatan(array $itinerary): array
    {
        $kecamatan = [];
        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                if (! empty($day['items']) && is_array($day['items'])) {
                    foreach ($day['items'] as $item) {
                        if (! empty($item['kecamatan'])) {
                            $kecamatan[] = $item['kecamatan'];
                        }
                    }
                }
            }
        }

        return $kecamatan;
    }

    /**
     * Dapatkan nilai modus (mayoritas) dari sebuah array.
     */
    protected function getMajorityValue(array $items): ?string
    {
        if (empty($items)) {
            return null;
        }
        $counts = array_count_values($items);
        arsort($counts);

        return array_key_first($counts);
    }
}
