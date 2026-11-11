<?php

namespace App\Console\Commands;

use App\Models\GearReservation;
use App\Models\GuideAvailability;
use App\Models\Notification;
use App\Models\Pesanan;
use App\Models\PesananGuide;
use App\Models\PesananItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessBookingExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:process-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa dan melepaskan reservasi guide dan gear yang kedaluwarsa secara otomatis.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $this->info("Menjalankan pemeriksaan kedaluwarsa reservasi pada [{$now->toDateTimeString()}]...");

        $expiredGuidesCount = 0;
        $expiredGearsCount = 0;
        $expiredPesananCount = 0;

        DB::transaction(function () use ($now, &$expiredGuidesCount, &$expiredGearsCount, &$expiredPesananCount) {
            // 1. Kedaluwarsa Pesanan Guide (menunggu_konfirmasi_guide dan melewati expires_at)
            $expiredGuideBookings = PesananGuide::with(['pesanan.user', 'guide'])
                ->where('status', 'menunggu_konfirmasi_guide')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->lockForUpdate()
                ->get();

            foreach ($expiredGuideBookings as $pg) {
                $pg->update(['status' => 'kedaluwarsa']);

                // Kembalikan ketersediaan guide tanggal tersebut menjadi 'tersedia'
                $bookingRef = 'BOOK-PG-'.$pg->id;
                GuideAvailability::where('guide_id', $pg->guide_id)
                    ->where('status', 'ditahan')
                    ->where(function ($q) use ($bookingRef, $pg) {
                        $q->where('booking_ref', $bookingRef)
                            ->orWhere('booking_ref', 'PESANAN-'.$pg->pesanan_id);
                    })
                    ->update([
                        'status' => 'tersedia',
                        'booking_ref' => null,
                    ]);

                // Notifikasi ke wisatawan
                if ($pg->pesanan && $pg->pesanan->user_id) {
                    Notification::create([
                        'user_id' => $pg->pesanan->user_id,
                        'tipe' => 'pesanan_guide',
                        'judul' => 'Batas Waktu Konfirmasi Pemandu Berakhir',
                        'isi' => 'Waktu konfirmasi untuk pesanan pemandu wisata telah kedaluwarsa. Jadwal pemandu telah dilepas kembali.',
                    ]);
                }

                $expiredGuidesCount++;
            }

            // 2. Kedaluwarsa Reservasi Gear (ditahan lebih dari 2 jam tanpa pembayaran)
            $expiredGearReservations = GearReservation::with(['gear'])
                ->where('status', 'ditahan')
                ->where('created_at', '<', $now->copy()->subHours(2))
                ->lockForUpdate()
                ->get();

            foreach ($expiredGearReservations as $gr) {
                $gr->update(['status' => 'dibatalkan']);
                if ($gr->pesanan_item_id) {
                    PesananItem::where('id', $gr->pesanan_item_id)
                        ->where('status', 'menunggu_konfirmasi_mitra')
                        ->update(['status' => 'dibatalkan']);
                }
                $expiredGearsCount++;
            }

            // 3. Kedaluwarsa Pesanan yang belum dibayar melewati batas waktu
            $expiredPesanans = Pesanan::where('status_pesanan', 'menunggu_pembayaran')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->lockForUpdate()
                ->get();

            foreach ($expiredPesanans as $p) {
                $p->update(['status_pesanan' => 'kedaluwarsa']);
                $expiredPesananCount++;
            }
        });

        $this->info("Pemeriksaan selesai: {$expiredGuidesCount} pesanan guide, {$expiredGearsCount} reservasi gear, dan {$expiredPesananCount} pesanan kedaluwarsa telah diproses.");

        return Command::SUCCESS;
    }
}
