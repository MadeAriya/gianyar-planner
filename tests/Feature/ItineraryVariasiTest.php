<?php

namespace Tests\Feature;

use App\Services\ItineraryGenerator;
use Tests\TestCase;

class ItineraryVariasiTest extends TestCase
{
    /**
     * Membuktikan bahwa 100x iterasi generate dengan input preferensi yang sama
     * menghasilkan variasi dinamis, rute tidak kaku/statis, dan tidak ada
     * satu destinasi pun yang mendominasi posisi pertama > 35%.
     */
    public function test_itinerary_100_iterasi_menghasilkan_variasi_tinggi(): void
    {
        $generator = new ItineraryGenerator;

        $preferensi = [
            'tgl_berangkat' => '2026-11-20',
            'tgl_pulang' => '2026-11-22',
            'durasi' => 2,
            'jumlah_orang' => 2,
            'kategori' => ['sawah', 'air_terjun', 'pura_candi'],
            'tipe_traveler' => 'couple',
            'tingkat_fisik' => 'sedang',
        ];

        $firstSlotFrequency = [];
        $uniqueSequences = [];
        $totalRuns = 100;

        for ($i = 0; $i < $totalRuns; $i++) {
            $result = $generator->generate($preferensi);

            // Validasi struktur
            $this->assertEquals(2, $result['durasi']);
            $this->assertCount(2, $result['jadwal']);
            $this->assertCount(3, $result['jadwal'][0]['items']);
            $this->assertCount(3, $result['jadwal'][1]['items']);

            // Validasi anti-duplikasi dalam 1 itinerary
            $allIdsInRun = [];
            foreach ($result['jadwal'] as $day) {
                foreach ($day['items'] as $item) {
                    $this->assertNotEmpty($item['alasan']);
                    $this->assertNotEmpty($item['jam_mulai']);
                    $this->assertNotEmpty($item['jam_selesai']);
                    $allIdsInRun[] = $item['destinasi']['id'];
                }
            }
            $this->assertCount(6, array_unique($allIdsInRun), 'Destinasi dalam satu itinerary tidak boleh duplikat');

            // Ambil id destinasi pertama
            $firstDestId = $result['jadwal'][0]['items'][0]['destinasi']['id'];
            $firstSlotFrequency[$firstDestId] = ($firstSlotFrequency[$firstDestId] ?? 0) + 1;

            // Catat sequence unik
            $sequenceKey = implode('-', $allIdsInRun);
            $uniqueSequences[$sequenceKey] = true;
        }

        // 1. Verifikasi tidak ada destinasi yang mendominasi posisi pertama > 35%
        $maxFirstFreq = max($firstSlotFrequency);
        $maxFirstPercentage = ($maxFirstFreq / $totalRuns) * 100;

        $this->assertLessThanOrEqual(
            35.0,
            $maxFirstPercentage,
            "Destinasi pertama terlalu statis! Frekuensi maksimum mencapai {$maxFirstPercentage}% (batas: 35%)"
        );

        // 2. Verifikasi terdapat minimal 15 kombinasi urutan berbeda dari 100x run
        $uniqueCount = count($uniqueSequences);
        $this->assertGreaterThanOrEqual(
            15,
            $uniqueCount,
            "Variasi urutan terlalu sedikit: hanya {$uniqueCount} kombinasi unik dari {$totalRuns} run."
        );
    }

    /**
     * Uji fallback: Bila user memilih kategori yang sedikit destinasi,
     * sistem tetap melengkapi hingga slot penuh tanpa crash atau duplikat.
     */
    public function test_itinerary_menangani_kategori_terbatas_dengan_fallback_cerdas(): void
    {
        $generator = new ItineraryGenerator;

        $preferensi = [
            'tgl_berangkat' => '2026-11-20',
            'tgl_pulang' => '2026-11-23',
            'durasi' => 3, // butuh 3 x 3 = 9 destinasi
            'jumlah_orang' => 4,
            'kategori' => ['cycling'], // cycling hanya punya 1-2 destinasi
            'tipe_traveler' => 'group',
            'tingkat_fisik' => 'aktif',
        ];

        $result = $generator->generate($preferensi);

        $this->assertCount(3, $result['jadwal']);
        $totalDest = 0;
        $ids = [];
        foreach ($result['jadwal'] as $day) {
            foreach ($day['items'] as $item) {
                $totalDest++;
                $ids[] = $item['destinasi']['id'];
            }
        }

        $this->assertEquals(9, $totalDest);
        $this->assertCount(9, array_unique($ids), 'Fallback harus mengisi slot tanpa duplikasi.');
    }

    /**
     * Uji reproducibility: Jika diberikan seed yang sama, hasil harus identik.
     */
    public function test_itinerary_reproducible_dengan_seed(): void
    {
        $generator = new ItineraryGenerator;

        $preferensi = [
            'tgl_berangkat' => '2026-11-20',
            'tgl_pulang' => '2026-11-22',
            'durasi' => 2,
            'jumlah_orang' => 2,
            'kategori' => ['sawah', 'air_terjun'],
            'tipe_traveler' => 'couple',
            'tingkat_fisik' => 'sedang',
        ];

        $runA = $generator->generate($preferensi, seed: 424242);
        $runB = $generator->generate($preferensi, seed: 424242);

        $idsA = [];
        foreach ($runA['jadwal'] as $day) {
            foreach ($day['items'] as $item) {
                $idsA[] = $item['destinasi']['id'];
            }
        }

        $idsB = [];
        foreach ($runB['jadwal'] as $day) {
            foreach ($day['items'] as $item) {
                $idsB[] = $item['destinasi']['id'];
            }
        }

        $this->assertSame($idsA, $idsB, 'Itinerary dengan seed yang sama harus menghasilkan urutan yang persis identik');
    }
}
