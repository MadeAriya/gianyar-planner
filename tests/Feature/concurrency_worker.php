<?php

use App\Models\GuideAvailability;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$workerId = $argv[1] ?? 'unknown';
$guideId = (int) ($argv[2] ?? 1);
$rawDates = $argv[3] ?? '';
if (str_starts_with($rawDates, 'base64:')) {
    $dates = json_decode(base64_decode(substr($rawDates, 7)), true);
} elseif (str_contains($rawDates, ',')) {
    $dates = array_map('trim', explode(',', $rawDates));
} else {
    $dates = json_decode($rawDates, true) ?: [$rawDates];
}

$dates = array_filter((array) $dates);

if (empty($dates)) {
    echo json_encode(['status' => 'ERROR', 'worker' => $workerId, 'message' => 'No dates provided']);
    exit(2);
}

try {
    $result = DB::transaction(function () use ($guideId, $dates, $workerId) {
        // Pessimistic row locking
        $availabilities = GuideAvailability::where('guide_id', $guideId)
            ->whereIn('tanggal', $dates)
            ->lockForUpdate()
            ->get();

        $allTersedia = ($availabilities->count() === count($dates)) &&
            $availabilities->every(fn ($a) => $a->status === 'tersedia');

        if (! $allTersedia) {
            return [
                'status' => 'CONFLICT',
                'worker' => $workerId,
                'message' => 'Guide tidak lagi tersedia pada tanggal tersebut (terkunci oleh transaksi lain).',
            ];
        }

        // Tahan tanggal
        foreach ($availabilities as $a) {
            $a->update([
                'status' => 'ditahan',
                'booking_ref' => 'CONCURRENCY-'.$workerId,
            ]);
        }

        // Delay 15ms untuk memastikan overlap jendela waktu transaksi kompetitor
        usleep(15000);

        return [
            'status' => 'SUCCESS',
            'worker' => $workerId,
            'message' => 'Berhasil mengunci ketersediaan guide tanpa konflik.',
        ];
    });

    echo json_encode($result);
    exit($result['status'] === 'SUCCESS' ? 0 : 1);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'EXCEPTION',
        'worker' => $workerId,
        'message' => $e->getMessage(),
    ]);
    exit(3);
}
