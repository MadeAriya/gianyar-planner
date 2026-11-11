<?php

namespace Tests\Feature;

use App\Http\Controllers\GuideDashboardController;
use App\Http\Controllers\MitraDashboardController;
use App\Models\GearReservation;
use App\Models\GuideAvailability;
use App\Models\Mitra;
use App\Models\Pesanan;
use App\Models\PesananGuide;
use App\Models\PesananItem;
use App\Models\TourGuide;
use App\Models\TravelGear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class StateMachineTest
{
    /**
     * Uji Siklus Hidup Pesanan Tour Guide (menunggu -> dikonfirmasi / ditolak -> terkonfirmasi).
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testGuideStateMachine(): array
    {
        $today = Carbon::today();
        $targetDate = $today->copy()->addDays(12)->toDateString();

        $user = User::where('role', 'user')->first() ?? User::factory()->create();
        $guide = TourGuide::with('user')->where('status_verifikasi', 'approved')->first();

        // 1. Inisialisasi booking
        $pesanan = Pesanan::create([
            'user_id' => $user->id,
            'tgl_pesan' => $today,
            'total_bayar' => 450000,
            'status_pembayaran' => 'belum_bayar',
            'status_pesanan' => 'menunggu_konfirmasi',
        ]);

        $pg = PesananGuide::create([
            'pesanan_id' => $pesanan->id,
            'guide_id' => $guide->id,
            'tanggal_mulai' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'tarif_snapshot' => $guide->tarif_per_hari,
            'status' => 'menunggu_konfirmasi_guide',
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $avail = GuideAvailability::updateOrCreate(
            ['guide_id' => $guide->id, 'tanggal' => $targetDate],
            ['status' => 'ditahan', 'booking_ref' => 'PESANAN-'.$pesanan->id]
        );

        // 2. Guide menerima pesanan (Action: konfirmasi)
        Auth::login($guide->user);
        $guideController = new GuideDashboardController;
        $requestAccept = Request::create("/guide/bookings/{$pg->id}/respond", 'POST', ['action' => 'konfirmasi']);
        $guideController->respondBooking($requestAccept, $pg->id);

        $pg->refresh();
        $avail->refresh();
        $acceptPassed = ($pg->status === 'dikonfirmasi_guide') && ($avail->status === 'terpesan');

        // 3. Skenario penolakan pesanan (Action: tolak)
        $targetDateReject = $today->copy()->addDays(13)->toDateString();
        $pgReject = PesananGuide::create([
            'pesanan_id' => $pesanan->id,
            'guide_id' => $guide->id,
            'tanggal_mulai' => $targetDateReject,
            'tanggal_selesai' => $targetDateReject,
            'tarif_snapshot' => $guide->tarif_per_hari,
            'status' => 'menunggu_konfirmasi_guide',
            'expires_at' => Carbon::now()->addHours(2),
        ]);
        $availReject = GuideAvailability::updateOrCreate(
            ['guide_id' => $guide->id, 'tanggal' => $targetDateReject],
            ['status' => 'ditahan', 'booking_ref' => 'PESANAN-'.$pesanan->id]
        );

        $requestReject = Request::create("/guide/bookings/{$pgReject->id}/respond", 'POST', [
            'action' => 'tolak',
            'alasan_penolakan' => 'Jadwal bentrok dengan acara keluarga adat.',
        ]);
        $guideController->respondBooking($requestReject, $pgReject->id);

        $pgReject->refresh();
        $availReject->refresh();
        $rejectPassed = ($pgReject->status === 'ditolak') && ($availReject->status === 'tersedia') && ($availReject->booking_ref === null);

        $isPassed = $acceptPassed && $rejectPassed;
        $details = 'Konfirmasi Guide: '.($acceptPassed ? 'OK (status=terpesan)' : 'GAGAL').' | Penolakan Guide: '.($rejectPassed ? 'OK (status=tersedia kembali)' : 'GAGAL');

        return [
            'name' => 'State Machine: Siklus Pesanan Tour Guide',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }

    /**
     * Uji Siklus Hidup Pesanan Perlengkapan Gear (menunggu -> konfirmasi -> siap_diambil -> dipakai -> dikembalikan).
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testMitraGearStateMachine(): array
    {
        $today = Carbon::today();
        $targetDate = $today->copy()->addDays(15)->toDateString();

        $user = User::where('role', 'user')->first() ?? User::factory()->create();
        $mitra = Mitra::with('user')->where('status_verifikasi', 'approved')->first();
        $gear = TravelGear::where('mitra_id', $mitra->id)->first();

        $pesanan = Pesanan::create([
            'user_id' => $user->id,
            'tgl_pesan' => $today,
            'total_bayar' => 50000,
            'status_pembayaran' => 'belum_bayar',
            'status_pesanan' => 'menunggu_konfirmasi',
        ]);

        $item = PesananItem::create([
            'pesanan_id' => $pesanan->id,
            'gear_id' => $gear->id,
            'mitra_id' => $mitra->id,
            'qty' => 2,
            'harga_snapshot' => $gear->harga,
            'deposit_snapshot' => $gear->deposit,
            'subtotal' => $gear->harga * 2,
            'deposit_total' => $gear->deposit * 2,
            'durasi_hari' => 1,
            'tanggal_mulai' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'metode_serah' => 'ambil_di_tempat',
            'status' => 'menunggu_konfirmasi_mitra',
        ]);

        $reservation = GearReservation::create([
            'gear_id' => $gear->id,
            'pesanan_item_id' => $item->id,
            'tanggal_mulai' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'qty' => 2,
            'status' => 'ditahan',
        ]);

        Auth::login($mitra->user);
        $mitraController = new MitraDashboardController;

        // 1. Konfirmasi ketersediaan
        $req1 = Request::create("/mitra/orders/{$item->id}/respond", 'POST', ['action' => 'konfirmasi']);
        $mitraController->respondOrder($req1, $item->id);
        $item->refresh();
        $step1 = ($item->status === 'dikonfirmasi_mitra');

        // 2. Siap diambil
        $req2 = Request::create("/mitra/orders/{$item->id}/respond", 'POST', ['action' => 'siap_diambil']);
        $mitraController->respondOrder($req2, $item->id);
        $item->refresh();
        $step2 = ($item->status === 'siap_diambil');

        // 3. Dipakai
        $req3 = Request::create("/mitra/orders/{$item->id}/respond", 'POST', ['action' => 'dipakai']);
        $mitraController->respondOrder($req3, $item->id);
        $item->refresh();
        $step3 = ($item->status === 'dipakai');

        // 4. Dikembalikan / Selesai
        $req4 = Request::create("/mitra/orders/{$item->id}/respond", 'POST', ['action' => 'dikembalikan']);
        $mitraController->respondOrder($req4, $item->id);
        $item->refresh();
        $reservation->refresh();
        $step4 = in_array($item->status, ['dikembalikan', 'selesai']) && ($reservation->status === 'selesai');

        $isPassed = $step1 && $step2 && $step3 && $step4;
        $details = 'Transisi: konfirmasi('.($step1 ? 'OK' : 'X').') -> siap_diambil('.($step2 ? 'OK' : 'X').') -> dipakai('.($step3 ? 'OK' : 'X').') -> dikembalikan('.($step4 ? 'OK' : 'X').')';

        return [
            'name' => 'State Machine: Siklus Serah Terima Travel Gear',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }

    /**
     * Uji Pelepasan Otomatis Slot Kedaluwarsa oleh Scheduler Command.
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testExpirationSchedulerCommand(): array
    {
        $today = Carbon::today();
        $targetDate = $today->copy()->addDays(20)->toDateString();

        $user = User::where('role', 'user')->first() ?? User::factory()->create();
        $guide = TourGuide::first();

        // Buat pesanan guide yang sudah expired (lewat batas waktu)
        $pesanan = Pesanan::create([
            'user_id' => $user->id,
            'tgl_pesan' => $today,
            'total_bayar' => 400000,
            'status_pembayaran' => 'belum_bayar',
            'status_pesanan' => 'menunggu_konfirmasi',
        ]);

        $pg = PesananGuide::create([
            'pesanan_id' => $pesanan->id,
            'guide_id' => $guide->id,
            'tanggal_mulai' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'tarif_snapshot' => $guide->tarif_per_hari,
            'status' => 'menunggu_konfirmasi_guide',
            'expires_at' => Carbon::now()->subMinutes(15), // Kedaluwarsa 15 menit lalu
        ]);

        $avail = GuideAvailability::updateOrCreate(
            ['guide_id' => $guide->id, 'tanggal' => $targetDate],
            ['status' => 'ditahan', 'booking_ref' => 'BOOK-PG-'.$pg->id]
        );

        // Jalankan Artisan Command
        Artisan::call('bookings:process-expirations');

        $pg->refresh();
        $avail->refresh();

        $isPassed = ($pg->status === 'kedaluwarsa') && ($avail->status === 'tersedia') && ($avail->booking_ref === null);
        $details = "Pesanan status: {$pg->status} | Slot kalender status: {$avail->status} (booking_ref: ".($avail->booking_ref ?? 'null').')';

        return [
            'name' => 'Automation: Scheduler Pelepasan Booking Kedaluwarsa',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }
}
