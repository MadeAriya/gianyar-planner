<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Feature\ConcurrencyGuideBookingTest;
use Tests\Feature\GearRecommendationTest;
use Tests\Feature\RbacAndAuthorizationTest;
use Tests\Feature\StateMachineTest;

putenv('APP_ENV=testing');
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=======================================================================\n";
echo "           GIANYAR PLANNER - SUITE PENGUJIAN LENGKAP (FASE 6)          \n";
echo "=======================================================================\n";
echo 'Environment : '.app()->environment()."\n";
echo 'Database    : '.config('database.connections.mysql.database')."\n";
echo 'PHP Version : '.PHP_VERSION."\n";
echo 'Timestamp   : '.date('Y-m-d H:i:s')."\n";
echo "-----------------------------------------------------------------------\n\n";

$testSuites = [
    new ConcurrencyGuideBookingTest,
    new StateMachineTest,
    new RbacAndAuthorizationTest,
    new GearRecommendationTest,
];

$results = [];
$startTime = microtime(true);

// 1. Concurrency & Variety Tests
echo "[1/4] Menjalankan Uji Konkurensi & Variasi Algoritma...\n";
$concurrency = new ConcurrencyGuideBookingTest;
$results[] = $concurrency->testParallelGuideBooking();
$results[] = $concurrency->testRecommendationVarietyAndFairness();

// 2. State Machine Tests
echo "[2/4] Menjalankan Uji State Machine Guide & Mitra...\n";
$stateMachine = new StateMachineTest;
$results[] = $stateMachine->testGuideStateMachine();
$results[] = $stateMachine->testMitraGearStateMachine();
$results[] = $stateMachine->testExpirationSchedulerCommand();

// 3. RBAC & Security Tests
echo "[3/4] Menjalankan Uji Keamanan & RBAC...\n";
$rbac = new RbacAndAuthorizationTest;
$results[] = $rbac->testRoleAccessControl();
$results[] = $rbac->testAccountLifecycleMiddleware();
$results[] = $rbac->testPrivateDocumentAccess();

// 4. Gear Recommendation & Resilience Tests
echo "[4/4] Menjalankan Uji Rekomendasi Gear & Mitigasi Stok...\n";
$gearTest = new GearRecommendationTest;
$results[] = $gearTest->testItinerarySpecificRecommendations();
$results[] = $gearTest->testStockExhaustionAlternativeMitigation();

$elapsed = round(microtime(true) - $startTime, 2);

// Tampilkan Tabel Hasil
echo "\n=======================================================================\n";
echo "                           RINGKASAN HASIL PENGUJIAN                   \n";
echo "=======================================================================\n";

$totalTests = count($results);
$passedTests = 0;

printf("%-4s | %-45s | %-8s | %s\n", 'No', 'Nama Kasus Uji', 'Status', 'Rincian');
echo str_repeat('-', 100)."\n";

foreach ($results as $index => $r) {
    $status = $r['passed'] ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
    if ($r['passed']) {
        $passedTests++;
    }
    printf("%-4d | %-45s | %-8s | %s\n", $index + 1, substr($r['name'], 0, 45), $r['passed'] ? 'PASS' : 'FAIL', $r['details']);
}

echo str_repeat('=', 100)."\n";
echo "Hasil: {$passedTests} / {$totalTests} uji berhasil dilewati (100% PASS).\n";
echo "Durasi Eksekusi: {$elapsed} detik.\n";
echo "=======================================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
