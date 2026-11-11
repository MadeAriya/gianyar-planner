<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GearCategory;
use App\Models\Notification;
use App\Models\PesananItem;
use App\Models\TravelGear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MitraDashboardController extends Controller
{
    protected function getMitra()
    {
        $user = Auth::user();
        $mitra = $user->mitra;
        if (! $mitra) {
            abort(404, 'Profil mitra gear tidak ditemukan.');
        }

        return $mitra;
    }

    public function dashboard(): View
    {
        $mitra = $this->getMitra();

        $metrics = [
            'total_gears' => TravelGear::where('mitra_id', $mitra->id)->count(),
            'active_gears' => TravelGear::where('mitra_id', $mitra->id)->where('is_active', true)->count(),
            'pending_orders' => PesananItem::where('mitra_id', $mitra->id)
                ->where('status', 'menunggu_konfirmasi_mitra')->count(),
            'completed_orders' => PesananItem::where('mitra_id', $mitra->id)
                ->whereIn('status', ['selesai', 'dikembalikan'])->count(),
        ];

        $pendingOrders = PesananItem::with(['gear', 'pesanan.user'])
            ->where('mitra_id', $mitra->id)
            ->where('status', 'menunggu_konfirmasi_mitra')
            ->latest()
            ->take(5)
            ->get();

        return view('mitra.dashboard', compact('mitra', 'metrics', 'pendingOrders'));
    }

    public function gears(): View
    {
        $mitra = $this->getMitra();
        $gears = TravelGear::with('category')->where('mitra_id', $mitra->id)->latest()->paginate(15);

        return view('mitra.gears.index', compact('mitra', 'gears'));
    }

    public function createGear(): View
    {
        $mitra = $this->getMitra();
        $categories = GearCategory::all();

        return view('mitra.gears.form', [
            'mitra' => $mitra,
            'categories' => $categories,
            'gear' => new TravelGear,
            'isEdit' => false,
        ]);
    }

    public function storeGear(Request $request): RedirectResponse
    {
        $mitra = $this->getMitra();

        $validated = $request->validate([
            'gear_category_id' => 'required|exists:gear_categories,id',
            'nama_barang' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:sewa,beli',
            'harga' => 'required|integer|min:0',
            'deposit' => 'nullable|integer|min:0',
            'stok_total' => 'required|integer|min:0',
            'foto' => 'nullable|url|max:500',
            'tags_aktivitas' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        TravelGear::create([
            'mitra_id' => $mitra->id,
            'gear_category_id' => $validated['gear_category_id'],
            'nama_barang' => $validated['nama_barang'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'tipe' => $validated['tipe'],
            'harga' => $validated['harga'],
            'deposit' => $validated['deposit'] ?? 0,
            'stok_total' => $validated['stok_total'],
            'foto' => $validated['foto'] ?? 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=400&q=80',
            'tags_aktivitas' => $validated['tags_aktivitas'] ?? ['outdoor'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::record(Auth::id(), 'create_gear', 'TravelGear', null, [
            'nama_barang' => $validated['nama_barang'],
        ]);

        return redirect()->route('mitra.gears.index')->with('success', 'Barang perlengkapan baru berhasil ditambahkan.');
    }

    public function editGear(int $id): View
    {
        $mitra = $this->getMitra();
        $gear = TravelGear::where('mitra_id', $mitra->id)->findOrFail($id);
        $categories = GearCategory::all();

        return view('mitra.gears.form', [
            'mitra' => $mitra,
            'categories' => $categories,
            'gear' => $gear,
            'isEdit' => true,
        ]);
    }

    public function updateGear(Request $request, int $id): RedirectResponse
    {
        $mitra = $this->getMitra();
        $gear = TravelGear::where('mitra_id', $mitra->id)->findOrFail($id);

        $validated = $request->validate([
            'gear_category_id' => 'required|exists:gear_categories,id',
            'nama_barang' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe' => 'required|in:sewa,beli',
            'harga' => 'required|integer|min:0',
            'deposit' => 'nullable|integer|min:0',
            'stok_total' => 'required|integer|min:0',
            'foto' => 'nullable|url|max:500',
            'tags_aktivitas' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $gear->update([
            'gear_category_id' => $validated['gear_category_id'],
            'nama_barang' => $validated['nama_barang'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'tipe' => $validated['tipe'],
            'harga' => $validated['harga'],
            'deposit' => $validated['deposit'] ?? 0,
            'stok_total' => $validated['stok_total'],
            'foto' => $validated['foto'] ?? $gear->foto,
            'tags_aktivitas' => $validated['tags_aktivitas'] ?? $gear->tags_aktivitas,
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::record(Auth::id(), 'update_gear', 'TravelGear', $gear->id, [
            'nama_barang' => $gear->nama_barang,
        ]);

        return redirect()->route('mitra.gears.index')->with('success', "Barang '{$gear->nama_barang}' berhasil diperbarui.");
    }

    public function destroyGear(int $id): RedirectResponse
    {
        $mitra = $this->getMitra();
        $gear = TravelGear::where('mitra_id', $mitra->id)->findOrFail($id);

        $gear->delete();

        AuditLog::record(Auth::id(), 'delete_gear', 'TravelGear', $id);

        return redirect()->route('mitra.gears.index')->with('success', 'Barang berhasil dihapus.');
    }

    public function orders(Request $request): View
    {
        $mitra = $this->getMitra();
        $status = $request->query('status', 'all');

        $query = PesananItem::with(['gear', 'pesanan.user'])
            ->where('mitra_id', $mitra->id)
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15);

        return view('mitra.orders', compact('mitra', 'orders', 'status'));
    }

    public function respondOrder(Request $request, int $id): RedirectResponse
    {
        $mitra = $this->getMitra();
        $action = $request->input('action'); // 'konfirmasi' | 'tolak' | 'siap_diambil' | 'dipakai' | 'dikembalikan'
        $alasan = $request->input('alasan_penolakan');

        $orderItem = PesananItem::with(['gear', 'pesanan.user', 'reservation'])
            ->where('mitra_id', $mitra->id)
            ->findOrFail($id);

        if ($action === 'konfirmasi') {
            $orderItem->update(['status' => 'dikonfirmasi_mitra']);

            Notification::create([
                'user_id' => $orderItem->pesanan->user_id,
                'tipe' => 'pesanan_gear',
                'judul' => 'Pesanan Gear Dikonfirmasi Mitra!',
                'isi' => "Mitra {$mitra->nama_usaha} telah mengonfirmasi ketersediaan barang: {$orderItem->gear->nama_barang} (x{$orderItem->qty}).",
            ]);

            return back()->with('success', 'Pesanan barang berhasil dikonfirmasi.');
        }

        if ($action === 'tolak') {
            if (empty($alasan)) {
                return back()->with('error', 'Alasan penolakan barang wajib diisi.');
            }

            $orderItem->update([
                'status' => 'ditolak',
                'alasan_penolakan' => $alasan,
            ]);

            if ($orderItem->reservation) {
                $orderItem->reservation->update(['status' => 'dibatalkan']);
            }

            Notification::create([
                'user_id' => $orderItem->pesanan->user_id,
                'tipe' => 'pesanan_gear',
                'judul' => 'Pemberitahuan Ketersediaan Barang',
                'isi' => "Barang {$orderItem->gear->nama_barang} tidak dapat dipenuhi: {$alasan}. Sistem menawarkan alternatif lain.",
            ]);

            return back()->with('success', 'Pesanan barang telah ditolak.');
        }

        if ($action === 'siap_diambil') {
            $orderItem->update(['status' => 'siap_diambil']);

            Notification::create([
                'user_id' => $orderItem->pesanan->user_id,
                'tipe' => 'pesanan_gear',
                'judul' => 'Perlengkapan Siap Diambil / Diantar!',
                'isi' => "Barang {$orderItem->gear->nama_barang} (x{$orderItem->qty}) sudah disiapkan oleh {$mitra->nama_usaha}.",
            ]);

            return back()->with('success', 'Status diubah: Siap diambil/dikirim.');
        }

        if ($action === 'dipakai') {
            $orderItem->update(['status' => 'dipakai']);

            return back()->with('success', 'Status diubah: Sedang dipakai wisatawan.');
        }

        if ($action === 'dikembalikan') {
            $targetStatus = ($orderItem->gear->tipe === 'beli') ? 'selesai' : 'dikembalikan';
            $orderItem->update(['status' => $targetStatus]);
            if ($orderItem->reservation) {
                $orderItem->reservation->update(['status' => 'selesai']);
            }

            return back()->with('success', 'Status diubah: Perlengkapan telah dikembalikan/selesai.');
        }

        return back();
    }
}
