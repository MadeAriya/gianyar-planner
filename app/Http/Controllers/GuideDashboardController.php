<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GuideAvailability;
use App\Models\Notification;
use App\Models\PesananGuide;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuideDashboardController extends Controller
{
    protected function getGuide()
    {
        $user = Auth::user();
        $guide = $user->tourGuide;
        if (! $guide) {
            abort(404, 'Profil tour guide tidak ditemukan.');
        }

        return $guide;
    }

    public function dashboard(): View
    {
        $guide = $this->getGuide();

        $metrics = [
            'rating_avg' => $guide->rating_avg,
            'jumlah_ulasan' => $guide->jumlah_ulasan,
            'job_selesai' => $guide->jumlah_job_selesai,
            'acceptance_rate' => $guide->acceptance_rate,
            'pending_requests' => PesananGuide::where('guide_id', $guide->id)
                ->where('status', 'menunggu_konfirmasi_guide')->count(),
            'upcoming_jobs' => PesananGuide::where('guide_id', $guide->id)
                ->where('status', 'terkonfirmasi')
                ->where('tanggal_mulai', '>=', now()->toDateString())
                ->count(),
        ];

        $pendingBookings = PesananGuide::with('pesanan.user')
            ->where('guide_id', $guide->id)
            ->where('status', 'menunggu_konfirmasi_guide')
            ->latest()
            ->take(5)
            ->get();

        $upcomingSchedules = PesananGuide::with('pesanan.user')
            ->where('guide_id', $guide->id)
            ->where('status', 'terkonfirmasi')
            ->where('tanggal_mulai', '>=', now()->toDateString())
            ->orderBy('tanggal_mulai')
            ->take(5)
            ->get();

        return view('guide.dashboard', compact('guide', 'metrics', 'pendingBookings', 'upcomingSchedules'));
    }

    public function kalender(Request $request): View
    {
        $guide = $this->getGuide();

        // Rentang 30 hari ke depan
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(29);
        $period = CarbonPeriod::create($startDate, $endDate);

        $availabilities = GuideAvailability::where('guide_id', $guide->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($item) => $item->tanggal->toDateString());

        $days = [];
        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $status = $availabilities[$dateStr]->status ?? 'tersedia';
            $days[] = [
                'date' => $date,
                'date_str' => $dateStr,
                'day_name' => $date->translatedFormat('D'),
                'day_num' => $date->format('d'),
                'month_name' => $date->translatedFormat('M'),
                'status' => $status,
            ];
        }

        return view('guide.kalender', compact('guide', 'days'));
    }

    public function toggleTanggal(Request $request): RedirectResponse
    {
        $request->validate([
            'tanggal' => 'required|date|after_or_equal:today',
            'action' => 'required|in:blokir,buka',
        ]);

        $guide = $this->getGuide();
        $tanggal = $request->input('tanggal');
        $action = $request->input('action');

        $record = GuideAvailability::where('guide_id', $guide->id)
            ->where('tanggal', $tanggal)
            ->first();

        if ($action === 'blokir') {
            if ($record && in_array($record->status, ['terpesan', 'ditahan'])) {
                return back()->with('error', "Tanggal {$tanggal} sedang ada pesanan aktif dan tidak dapat diblokir manual.");
            }

            GuideAvailability::updateOrCreate(
                ['guide_id' => $guide->id, 'tanggal' => $tanggal],
                ['status' => 'diblokir_guide']
            );

            return back()->with('success', "Tanggal {$tanggal} berhasil diblokir.");
        }

        // Buka kembali menjadi tersedia
        if ($record && in_array($record->status, ['terpesan', 'ditahan'])) {
            return back()->with('error', "Tanggal {$tanggal} sedang terikat booking aktif.");
        }

        GuideAvailability::updateOrCreate(
            ['guide_id' => $guide->id, 'tanggal' => $tanggal],
            ['status' => 'tersedia']
        );

        return back()->with('success', "Tanggal {$tanggal} dibuka kembali untuk pemesanan.");
    }

    public function bookings(Request $request): View
    {
        $guide = $this->getGuide();
        $status = $request->query('status', 'all');

        $query = PesananGuide::with('pesanan.user')
            ->where('guide_id', $guide->id)
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $bookings = $query->paginate(15);

        return view('guide.bookings', compact('guide', 'bookings', 'status'));
    }

    public function respondBooking(Request $request, int $id): RedirectResponse
    {
        $guide = $this->getGuide();
        $action = $request->input('action'); // 'terima' | 'tolak'
        $alasan = $request->input('alasan_penolakan');

        $booking = PesananGuide::with('pesanan.user')
            ->where('guide_id', $guide->id)
            ->findOrFail($id);

        if ($booking->status !== 'menunggu_konfirmasi_guide') {
            return back()->with('error', 'Status pesanan ini sudah tidak dalam status menunggu konfirmasi.');
        }

        if ($action === 'terima' || $action === 'konfirmasi') {
            DB::transaction(function () use ($booking, $guide) {
                $booking->update([
                    'status' => 'dikonfirmasi_guide',
                    'responded_at' => now(),
                    'expires_at' => now()->addHours(24), // batas bayar user 24 jam
                ]);

                // Update tanggal availability menjadi 'terpesan'
                $period = CarbonPeriod::create($booking->tanggal_mulai, $booking->tanggal_selesai);
                foreach ($period as $date) {
                    GuideAvailability::updateOrCreate(
                        ['guide_id' => $guide->id, 'tanggal' => $date->toDateString()],
                        ['status' => 'terpesan', 'booking_ref' => (string) $booking->pesanan_id]
                    );
                }

                // Update acceptance rate
                $totalResponded = PesananGuide::where('guide_id', $guide->id)->whereNotNull('responded_at')->count();
                $totalAccepted = PesananGuide::where('guide_id', $guide->id)->whereIn('status', ['dikonfirmasi_guide', 'terkonfirmasi', 'berlangsung', 'selesai'])->count();
                $rate = $totalResponded > 0 ? round(($totalAccepted / $totalResponded) * 100, 2) : 100.00;
                $guide->update(['acceptance_rate' => $rate]);
            });

            Notification::create([
                'user_id' => $booking->pesanan->user_id,
                'tipe' => 'booking_guide',
                'judul' => 'Tour Guide Menerima Pesanan Anda!',
                'isi' => "Tour Guide {$guide->user->nama} telah menyetujui pesanan Anda untuk tanggal {$booking->tanggal_mulai} s/d {$booking->tanggal_selesai}. Silakan selesaikan pembayaran sebelum batas waktu.",
            ]);

            AuditLog::record($guide->user_id, 'guide_accept_booking', 'PesananGuide', $booking->id);

            return back()->with('success', 'Pesanan berhasil Anda terima. Menunggu pembayaran wisatawan.');
        }

        // Jika Ditolak
        if (empty($alasan)) {
            return back()->with('error', 'Alasan penolakan wajib diisi untuk wisatawan.');
        }

        DB::transaction(function () use ($booking, $guide, $alasan) {
            $booking->update([
                'status' => 'ditolak',
                'alasan_penolakan' => $alasan,
                'responded_at' => now(),
            ]);

            // Kembalikan availability tanggal ke 'tersedia'
            $period = CarbonPeriod::create($booking->tanggal_mulai, $booking->tanggal_selesai);
            foreach ($period as $date) {
                GuideAvailability::updateOrCreate(
                    ['guide_id' => $guide->id, 'tanggal' => $date->toDateString()],
                    ['status' => 'tersedia', 'booking_ref' => null]
                );
            }

            // Turunkan acceptance rate
            $totalResponded = PesananGuide::where('guide_id', $guide->id)->whereNotNull('responded_at')->count();
            $totalAccepted = PesananGuide::where('guide_id', $guide->id)->whereIn('status', ['dikonfirmasi_guide', 'terkonfirmasi', 'berlangsung', 'selesai'])->count();
            $rate = $totalResponded > 0 ? round(($totalAccepted / $totalResponded) * 100, 2) : 100.00;
            $guide->update(['acceptance_rate' => $rate]);
        });

        Notification::create([
            'user_id' => $booking->pesanan->user_id,
            'tipe' => 'booking_guide',
            'judul' => 'Pemberitahuan Pesanan Tour Guide',
            'isi' => "Tour Guide berhalangan menerima pesanan Anda: {$alasan}. Sistem kami siap mencarikan pemandu pengganti yang setara.",
        ]);

        AuditLog::record($guide->user_id, 'guide_reject_booking', 'PesananGuide', $booking->id, [
            'alasan' => $alasan,
        ]);

        return back()->with('success', 'Pesanan telah ditolak.');
    }
}
