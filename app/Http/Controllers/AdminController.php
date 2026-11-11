<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GuideAvailability;
use App\Models\Mitra;
use App\Models\Notification;
use App\Models\Pesanan;
use App\Models\TourGuide;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $metrics = [
            'total_users' => User::count(),
            'pending_guides' => TourGuide::where('status_verifikasi', 'pending')->count(),
            'approved_guides' => TourGuide::where('status_verifikasi', 'approved')->count(),
            'pending_mitras' => Mitra::where('status_verifikasi', 'pending')->count(),
            'approved_mitras' => Mitra::where('status_verifikasi', 'approved')->count(),
            'menunggu_bayar_count' => Pesanan::where('status_pembayaran', 'menunggu_verifikasi')->count(),
            'pesanan_lunas_count' => Pesanan::where('status_pembayaran', 'lunas')->count(),
        ];

        $recentPendingGuides = TourGuide::with('user')->where('status_verifikasi', 'pending')->latest()->take(5)->get();
        $recentPendingMitras = Mitra::with('user')->where('status_verifikasi', 'pending')->latest()->take(5)->get();
        $recentPayments = Pesanan::with('user')->where('status_pembayaran', 'menunggu_verifikasi')->latest()->take(5)->get();
        $recentAuditLogs = AuditLog::with('actor')->latest('created_at')->take(8)->get();

        return view('admin.dashboard', compact('metrics', 'recentPendingGuides', 'recentPendingMitras', 'recentPayments', 'recentAuditLogs'));
    }

    public function guides(Request $request): View
    {
        $status = $request->query('status', 'all');

        $query = TourGuide::with('user')->latest();
        if ($status !== 'all') {
            $query->where('status_verifikasi', $status);
        }

        $guides = $query->paginate(15);

        return view('admin.guides.index', compact('guides', 'status'));
    }

    public function guideDetail(int $id): View
    {
        $guide = TourGuide::with(['user', 'verifiedBy'])->findOrFail($id);

        return view('admin.guides.show', compact('guide'));
    }

    public function verifyGuide(Request $request, int $id): RedirectResponse
    {
        $action = $request->input('action'); // approve | reject | suspend | reactivate
        $catatan = $request->input('catatan_verifikasi');

        $guide = TourGuide::with('user')->findOrFail($id);

        if ($action === 'reject' && empty($catatan)) {
            return back()->with('error', 'Alasan penolakan wajib diisi agar tour guide dapat memperbaiki berkas.');
        }

        $adminId = Auth::id();

        if ($action === 'approve') {
            $guide->status_verifikasi = 'approved';
            $guide->verified_by = $adminId;
            $guide->verified_at = now();
            $guide->catatan_verifikasi = null;
            $guide->user->update(['status' => 'active']);

            Notification::create([
                'user_id' => $guide->user_id,
                'tipe' => 'verifikasi_akun',
                'judul' => 'Pengajuan Tour Guide Disetujui!',
                'isi' => 'Selamat, profil tour guide Anda telah diverifikasi oleh admin. Anda sekarang aktif dan dapat menerima pesanan wisatawan.',
            ]);
        } elseif ($action === 'reject') {
            $guide->status_verifikasi = 'rejected';
            $guide->verified_by = $adminId;
            $guide->verified_at = now();
            $guide->catatan_verifikasi = $catatan;
            $guide->user->update(['status' => 'rejected']);

            Notification::create([
                'user_id' => $guide->user_id,
                'tipe' => 'verifikasi_akun',
                'judul' => 'Pengajuan Tour Guide Ditolak',
                'isi' => "Pengajuan Anda belum disetujui. Catatan admin: {$catatan}. Silakan ajukan ulang dokumen perbaikan.",
            ]);
        } elseif ($action === 'suspend') {
            $guide->status_verifikasi = 'suspended';
            $guide->catatan_verifikasi = $catatan ?? 'Ditangguhkan oleh Super Admin.';
            $guide->user->update(['status' => 'suspended']);

            Notification::create([
                'user_id' => $guide->user_id,
                'tipe' => 'verifikasi_akun',
                'judul' => 'Akun Ditangguhkan',
                'isi' => 'Akun tour guide Anda ditangguhkan sementara oleh admin.',
            ]);
        } elseif ($action === 'reactivate') {
            $guide->status_verifikasi = 'approved';
            $guide->catatan_verifikasi = null;
            $guide->user->update(['status' => 'active']);
        }

        $guide->save();

        AuditLog::record($adminId, "guide_{$action}", 'TourGuide', $guide->id, [
            'guide_nama' => $guide->user->nama,
            'catatan' => $catatan,
        ]);

        return redirect()->route('admin.guides.show', $guide->id)->with('success', "Status Tour Guide berhasil diperbarui ({$action}).");
    }

    public function mitras(Request $request): View
    {
        $status = $request->query('status', 'all');

        $query = Mitra::with('user')->latest();
        if ($status !== 'all') {
            $query->where('status_verifikasi', $status);
        }

        $mitras = $query->paginate(15);

        return view('admin.mitras.index', compact('mitras', 'status'));
    }

    public function mitraDetail(int $id): View
    {
        $mitra = Mitra::with(['user', 'verifiedBy', 'gears'])->findOrFail($id);

        return view('admin.mitras.show', compact('mitra'));
    }

    public function verifyMitra(Request $request, int $id): RedirectResponse
    {
        $action = $request->input('action'); // approve | reject | suspend | reactivate
        $catatan = $request->input('catatan_verifikasi');

        $mitra = Mitra::with('user')->findOrFail($id);

        if ($action === 'reject' && empty($catatan)) {
            return back()->with('error', 'Alasan penolakan wajib diisi agar mitra dapat memperbaiki berkas.');
        }

        $adminId = Auth::id();

        if ($action === 'approve') {
            $mitra->status_verifikasi = 'approved';
            $mitra->verified_by = $adminId;
            $mitra->verified_at = now();
            $mitra->catatan_verifikasi = null;
            $mitra->user->update(['status' => 'active']);

            Notification::create([
                'user_id' => $mitra->user_id,
                'tipe' => 'verifikasi_akun',
                'judul' => 'Pengajuan Mitra Gear Disetujui!',
                'isi' => 'Selamat, tempat usaha Anda telah diverifikasi oleh admin. Anda sekarang dapat menambahkan barang ke katalog gear.',
            ]);
        } elseif ($action === 'reject') {
            $mitra->status_verifikasi = 'rejected';
            $mitra->verified_by = $adminId;
            $mitra->verified_at = now();
            $mitra->catatan_verifikasi = $catatan;
            $mitra->user->update(['status' => 'rejected']);

            Notification::create([
                'user_id' => $mitra->user_id,
                'tipe' => 'verifikasi_akun',
                'judul' => 'Pengajuan Mitra Gear Ditolak',
                'isi' => "Pengajuan Anda belum disetujui. Catatan admin: {$catatan}. Silakan ajukan ulang dokumen perbaikan.",
            ]);
        } elseif ($action === 'suspend') {
            $mitra->status_verifikasi = 'suspended';
            $mitra->catatan_verifikasi = $catatan ?? 'Ditangguhkan oleh Super Admin.';
            $mitra->user->update(['status' => 'suspended']);
        } elseif ($action === 'reactivate') {
            $mitra->status_verifikasi = 'approved';
            $mitra->catatan_verifikasi = null;
            $mitra->user->update(['status' => 'active']);
        }

        $mitra->save();

        AuditLog::record($adminId, "mitra_{$action}", 'Mitra', $mitra->id, [
            'nama_usaha' => $mitra->nama_usaha,
            'catatan' => $catatan,
        ]);

        return redirect()->route('admin.mitras.show', $mitra->id)->with('success', "Status Mitra berhasil diperbarui ({$action}).");
    }

    public function users(Request $request): View
    {
        $role = $request->query('role', 'all');
        $status = $request->query('status', 'all');

        $query = User::latest();
        if ($role !== 'all') {
            $query->where('role', $role);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users', 'role', 'status'));
    }

    public function toggleUserStatus(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menangguhkan akun Anda sendiri.');
        }

        $targetStatus = $user->isSuspended() ? 'active' : 'suspended';
        $user->update(['status' => $targetStatus]);

        AuditLog::record(Auth::id(), "user_status_{$targetStatus}", 'User', $user->id, [
            'email' => $user->email,
        ]);

        return back()->with('success', "Status akun {$user->email} diubah menjadi {$targetStatus}.");
    }

    public function pembayaran(Request $request): View
    {
        $status = $request->query('status', 'menunggu_verifikasi');

        $query = Pesanan::with(['user', 'pesananGuide.guide.user', 'items.gear'])->latest('tgl_pesan');
        if ($status !== 'all') {
            $query->where('status_pembayaran', $status);
        }

        $pesanans = $query->paginate(15);

        return view('admin.pembayaran.index', compact('pesanans', 'status'));
    }

    public function verifikasiPembayaran(Request $request, int $id): RedirectResponse
    {
        $action = $request->input('action'); // 'setujui' | 'tolak'
        $catatan = $request->input('catatan_admin');

        $pesanan = Pesanan::with(['user', 'pesananGuide', 'items.reservation', 'itinerary'])->findOrFail($id);

        if ($action === 'setujui') {
            $pesanan->status_pembayaran = 'lunas';
            $pesanan->status_pesanan = 'terkonfirmasi';
            $pesanan->catatan_admin = $catatan;
            if (! $pesanan->kode_tiket) {
                $pesanan->kode_tiket = 'GNY-'.strtoupper(substr(uniqid(), -6));
            }
            $pesanan->save();

            // Kunci itinerary agar tidak berubah setelah lunas
            if ($pesanan->itinerary) {
                $pesanan->itinerary->update(['status' => 'terkunci']);
            }

            // Update status guide & jadikan ketersediaan 'terpesan'
            if ($pesanan->pesananGuide) {
                $pesanan->pesananGuide->update(['status' => 'terkonfirmasi']);
                GuideAvailability::where('guide_id', $pesanan->pesananGuide->guide_id)
                    ->where('booking_ref', 'PESANAN-'.$pesanan->id)
                    ->update(['status' => 'terpesan']);
            }

            // Update status items gear & konfirmasi reservasi stok
            foreach ($pesanan->items as $item) {
                $item->update(['status' => 'dikonfirmasi_mitra']);
                if ($item->reservation) {
                    $item->reservation->update(['status' => 'dikonfirmasi']);
                }
            }

            Notification::create([
                'user_id' => $pesanan->user_id,
                'tipe' => 'pembayaran',
                'judul' => 'Pembayaran Terverifikasi!',
                'isi' => "Pembayaran pesanan #{$pesanan->id} telah disetujui. Kode tiket: {$pesanan->kode_tiket}.",
            ]);

            AuditLog::record(Auth::id(), 'approve_payment', 'Pesanan', $pesanan->id, [
                'total_bayar' => $pesanan->total_bayar,
                'kode_tiket' => $pesanan->kode_tiket,
            ]);

            return back()->with('success', "Pembayaran pesanan #{$pesanan->id} disetujui. Tiket {$pesanan->kode_tiket} diterbitkan.");
        }

        // Jika Ditolak
        $pesanan->status_pembayaran = 'ditolak';
        $pesanan->catatan_admin = $catatan ?? 'Bukti pembayaran tidak valid atau transfer belum masuk.';
        $pesanan->save();

        // Lepas ketersediaan guide yang ditahan
        if ($pesanan->pesananGuide) {
            $pesanan->pesananGuide->update(['status' => 'dibatalkan']);
            GuideAvailability::where('guide_id', $pesanan->pesananGuide->guide_id)
                ->where('booking_ref', 'PESANAN-'.$pesanan->id)
                ->update(['status' => 'tersedia', 'booking_ref' => null]);
        }

        // Lepas reservasi stok
        foreach ($pesanan->items as $item) {
            if ($item->reservation) {
                $item->reservation->update(['status' => 'dibatalkan']);
            }
        }

        Notification::create([
            'user_id' => $pesanan->user_id,
            'tipe' => 'pembayaran',
            'judul' => 'Pembayaran Ditolak',
            'isi' => "Pembayaran pesanan #{$pesanan->id} ditolak: {$pesanan->catatan_admin}.",
        ]);

        AuditLog::record(Auth::id(), 'reject_payment', 'Pesanan', $pesanan->id, [
            'alasan' => $pesanan->catatan_admin,
        ]);

        return back()->with('success', "Pembayaran pesanan #{$pesanan->id} ditolak.");
    }

    public function auditLogs(): View
    {
        $logs = AuditLog::with('actor')->latest('created_at')->paginate(30);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
