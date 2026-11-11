<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ItineraryGenerator;

$generator = new ItineraryGenerator();

$preferensi = [
    'tgl_berangkat' => '2026-11-20',
    'tgl_pulang' => '2026-11-22',
    'durasi' => 2,
    'jumlah_orang' => 2,
    'kategori' => ['sawah', 'air_terjun', 'pura_candi'],
    'tipe_traveler' => 'couple',
    'tingkat_fisik' => 'sedang',
];

echo "=======================================================\n";
echo "   PENGUJIAN VARIASI GENERATE ITINERARY (100 ITERASI)  \n";
echo "=======================================================\n";
echo "Input:\n";
echo " - Durasi: 2 Hari (6 slot aktivitas)\n";
echo " - Kategori Pilihan: Sawah, Air Terjun, Pura & Candi\n";
echo " - Tipe Traveler: couple\n";
echo " - Tingkat Fisik: sedang\n\n";

$firstSlotFrequency = [];
$uniqueSequences = [];
$totalRuns = 100;
$allRuns = [];

for ($i = 0; $i < $totalRuns; $i++) {
    $result = $generator->generate($preferensi);
    $allRuns[] = $result;

    $allIdsInRun = [];
    foreach ($result['jadwal'] as $day) {
        foreach ($day['items'] as $item) {
            $allIdsInRun[] = $item['destinasi']['id'];
        }
    }

    $firstDest = $result['jadwal'][0]['items'][0]['destinasi'];
    $firstDestName = $firstDest['nama_destinasi'];
    $firstSlotFrequency[$firstDestName] = ($firstSlotFrequency[$firstDestName] ?? 0) + 1;

    $seqKey = implode(' -> ', array_map(function ($item) {
        return $item['destinasi']['nama_destinasi'];
    }, array_merge($result['jadwal'][0]['items'], $result['jadwal'][1]['items'])));

    $uniqueSequences[$seqKey] = ($uniqueSequences[$seqKey] ?? 0) + 1;
}

echo "HASIL ANALISIS STATISTIK DARI 100 KALI GENERATE:\n";
echo "-------------------------------------------------------\n";
echo "1. Distribusi Destinasi Pertama (Slot Pagi Hari 1):\n";
arsort($firstSlotFrequency);
$maxFreq = 0;
$maxName = '';
foreach ($firstSlotFrequency as $name => $count) {
    $pct = ($count / $totalRuns) * 100;
    if ($count > $maxFreq) {
        $maxFreq = $count;
        $maxName = $name;
    }
    printf("   - %-32s : %2d kali (%.1f%%)\n", $name, $count, $pct);
}

echo "\n2. Keragaman Rute (Unique Combinations):\n";
echo "   - Total Urutan Rute Unik: " . count($uniqueSequences) . " kombinasi berbeda dari 100 kali jalan.\n";

$maxPct = ($maxFreq / $totalRuns) * 100;
echo "\n3. Evaluasi Kriteria Penerimaan:\n";
$target1 = $maxPct <= 35.0 ? "LULUS (<= 35%)" : "GAGAL (> 35%)";
echo "   [Criteria 1] Frekuensi Terbanyak Destinasi #1 <= 35% : {$maxPct}% -> {$target1}\n";

$target2 = count($uniqueSequences) >= 15 ? "LULUS (>= 15 kombinasi)" : "GAGAL (< 15 kombinasi)";
echo "   [Criteria 2] Keragaman Urutan Keseluruhan >= 15      : " . count($uniqueSequences) . " kombinasi -> {$target2}\n";

// Sample 3 variasi rute berbeda
echo "\n4. Contoh 3 Variasi Itinerary yang Dihasilkan:\n";
$samples = [0, 1, 2];
foreach ($samples as $idx) {
    echo "   [Variasi #" . ($idx + 1) . " - Seed: " . $allRuns[$idx]['seed'] . "]\n";
    foreach ($allRuns[$idx]['jadwal'] as $day) {
        echo "     * Hari " . $day['hari_ke'] . " (" . $day['zona'] . "):\n";
        foreach ($day['items'] as $item) {
            $dest = $item['destinasi'];
            $km = $item['jarak_dari_sebelumnya_km'] ? " [{$item['jarak_dari_sebelumnya_km']} km]" : "";
            echo "       - {$item['jam_mulai']} : {$dest['nama_destinasi']} ({$dest['kategori']}){$km}\n";
            echo "         Alasan: {$item['alasan']}\n";
        }
    }
    echo "\n";
}

echo "=======================================================\n";
