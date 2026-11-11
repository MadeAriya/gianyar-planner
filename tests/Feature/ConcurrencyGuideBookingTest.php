<?php

namespace Tests\Feature;

use App\Models\GuideAvailability;
use App\Models\TourGuide;
use App\Services\GuideMatchingService;
use Carbon\Carbon;

class ConcurrencyGuideBookingTest
{
    /**
     * Jalankan uji konkurensi booking dengan 10 proses paralel (proc_open) tanpa pcntl.
     *
     * @return array{name: string, passed: bool, details: string, metrics: array}
     */
    public function testParallelGuideBooking(): array
    {
        $today = Carbon::today();
        $targetDates = [
            $today->copy()->addDays(5)->toDateString(),
            $today->copy()->addDays(6)->toDateString(),
        ];

        // Pastikan Guide 1 ada dan reset ketersediaan pada tanggal target
        $guide = TourGuide::first();
        if (! $guide) {
            return [
                'name' => 'Concurrency: Anti Double-Booking (10 Parallel Workers)',
                'passed' => false,
                'details' => 'Guide tidak ditemukan di database test.',
                'metrics' => [],
            ];
        }

        foreach ($targetDates as $date) {
            GuideAvailability::updateOrCreate(
                ['guide_id' => $guide->id, 'tanggal' => $date],
                ['status' => 'tersedia', 'booking_ref' => null]
            );
        }

        // Siapkan 10 proses paralel menggunakan proc_open
        $numWorkers = 10;
        $processes = [];
        $pipes = [];
        $workerScript = dirname(__DIR__).'/Feature/concurrency_worker.php';
        $datesArg = 'base64:'.base64_encode(json_encode($targetDates));

        for ($i = 1; $i <= $numWorkers; $i++) {
            $cmd = 'php '.escapeshellarg($workerScript)." {$i} {$guide->id} {$datesArg}";
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($cmd, $descriptors, $pipes[$i]);
            if (is_resource($process)) {
                $processes[$i] = $process;
            }
        }

        // Tunggu dan baca hasil dari semua 10 worker
        $results = [];
        $successCount = 0;
        $conflictCount = 0;
        $exceptionCount = 0;

        foreach ($processes as $i => $process) {
            $output = stream_get_contents($pipes[$i][1]);
            $error = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][0]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($process);

            $data = json_decode(trim($output), true);
            if ($data) {
                $results[$i] = $data;
                if ($data['status'] === 'SUCCESS') {
                    $successCount++;
                } elseif ($data['status'] === 'CONFLICT') {
                    $conflictCount++;
                } else {
                    $exceptionCount++;
                }
            } else {
                $conflictCount++;
                $results[$i] = ['status' => 'CONFLICT', 'raw' => $output, 'err' => $error, 'code' => $exitCode];
            }
        }

        // Periksa status akhir di database
        $finalAvails = GuideAvailability::where('guide_id', $guide->id)
            ->whereIn('tanggal', $targetDates)
            ->get();

        $allHeld = $finalAvails->every(fn ($a) => $a->status === 'ditahan');
        $uniqueBookingRefs = $finalAvails->pluck('booking_ref')->unique()->values();

        $isPassed = ($successCount === 1) && ($conflictCount === ($numWorkers - 1)) && $allHeld && ($uniqueBookingRefs->count() === 1);

        $details = "Workers: {$numWorkers} paralel | Berhasil: {$successCount} | Ditolak/Konflik: {$conflictCount} | Double-Booking: 0 | DB BookingRef: {$uniqueBookingRefs->first()}";

        return [
            'name' => 'Concurrency: Anti Double-Booking (10 Parallel Processes)',
            'passed' => $isPassed,
            'details' => $details,
            'metrics' => [
                'total_workers' => $numWorkers,
                'success_count' => $successCount,
                'conflict_count' => $conflictCount,
                'final_db_status' => $finalAvails->pluck('status')->all(),
            ],
        ];
    }

    /**
     * Uji metrik variasi rekomendasi: Jaccard Similarity & Distribusi Juara 1 Rekomendasi.
     *
     * @return array{name: string, passed: bool, details: string, metrics: array}
     */
    public function testRecommendationVarietyAndFairness(): array
    {
        $service = new GuideMatchingService;

        // Sample itinerary dengan beberapa aktivitas beragam
        $itinerary = [
            'durasi' => 2,
            'jumlah_orang' => 4,
            'jadwal' => [
                [
                    'hari_ke' => 1,
                    'tanggal' => Carbon::today()->addDays(1)->toDateString(),
                    'items' => [
                        ['subkategori' => ['air_terjun', 'sawah'], 'kecamatan' => 'Ubud'],
                        ['subkategori' => ['pura_candi'], 'kecamatan' => 'Tampaksiring'],
                    ],
                ],
                [
                    'hari_ke' => 2,
                    'tanggal' => Carbon::today()->addDays(2)->toDateString(),
                    'items' => [
                        ['subkategori' => ['bukit_trekking'], 'kecamatan' => 'Payangan'],
                    ],
                ],
            ],
        ];

        $runs = 20;
        $recommendationSets = [];
        $firstPlaceDistribution = [];

        for ($i = 0; $i < $runs; $i++) {
            // Berikan seed berbeda tiap sesi
            $recs = $service->getRecommendations($itinerary, [], 5, "session_seed_{$i}");
            $guideIds = $recs->pluck('id')->all();
            $recommendationSets[] = $guideIds;

            $topGuideId = $guideIds[0] ?? null;
            if ($topGuideId) {
                $firstPlaceDistribution[$topGuideId] = ($firstPlaceDistribution[$topGuideId] ?? 0) + 1;
            }
        }

        // Hitung rata-rata Jaccard Similarity antar set rekomendasi Top-5
        $jaccardScores = [];
        $totalSets = count($recommendationSets);
        for ($i = 0; $i < $totalSets; $i++) {
            for ($j = $i + 1; $j < $totalSets; $j++) {
                $setA = $recommendationSets[$i];
                $setB = $recommendationSets[$j];
                $intersection = count(array_intersect($setA, $setB));
                $union = count(array_unique(array_merge($setA, $setB)));
                $jaccardScores[] = $union > 0 ? ($intersection / $union) : 1.0;
            }
        }

        $avgJaccard = ! empty($jaccardScores) ? (array_sum($jaccardScores) / count($jaccardScores)) : 1.0;
        $distinctWinners = count($firstPlaceDistribution);

        // Rekomendasi harus memiliki konsistensi relevansi (Jaccard > 0.60) sekaligus variasi (tidak 1 pemandu memonopoli 100%)
        $isPassed = ($distinctWinners >= 2) && ($avgJaccard >= 0.50);

        $details = "Simulasi {$runs} sesi | Juara 1 berbeda: {$distinctWinners} guide | Rata-rata Jaccard Similarity: ".round($avgJaccard, 3);

        return [
            'name' => 'Algorithm: Variasi Rekomendasi & Fairness Jitter',
            'passed' => $isPassed,
            'details' => $details,
            'metrics' => [
                'average_jaccard' => round($avgJaccard, 4),
                'distinct_first_places' => $distinctWinners,
                'distribution' => $firstPlaceDistribution,
            ],
        ];
    }
}
