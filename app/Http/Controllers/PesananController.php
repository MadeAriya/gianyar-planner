<?php

namespace App\Http\Controllers;

use App\Support\DummyData;
use App\Support\JsonStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PesananController extends Controller
{
    public function ringkasan(): View|RedirectResponse
    {
        $itinerary = session('itinerary');
        $preferensi = session('preferensi');
        if (! $itinerary || ! $preferensi) {
            return redirect()->route('preferensi.create');
        }

        // TODO: ganti dengan Eloquent
        $selectedGuide = session('selected_guide');
        $selectedGears = session('selected_gears', []);
        $isBundle = session('is_bundle', false);
        $bundleDiscount = config('gianyar.bundle_discount', 0.10);

        $guide = null;
        $guideCost = 0;
        if ($selectedGuide) {
            $guide = collect(DummyData::tourGuide())->firstWhere('id', $selectedGuide);
            if ($guide) {
                $guideCost = $guide['tarif'] * $itinerary['durasi'];
            }
        }

        $gearCost = 0;
        $gearDetails = [];
        foreach ($selectedGears as $gearId => $qty) {
            $gear = collect(DummyData::travelGear())->firstWhere('id', (int) $gearId);
            if ($gear && $qty > 0) {
                $subtotal = $gear['harga'] * $qty * $itinerary['durasi'];
                $gearCost += $subtotal;
                $gearDetails[] = array_merge($gear, ['jumlah' => $qty, 'subtotal' => $subtotal]);
            }
        }

        $totalBayar = $guideCost + $gearCost;
        if ($isBundle && $totalBayar > 0) {
            $totalBayar = (int) round($totalBayar * (1 - $bundleDiscount));
        }

        return view('pesanan.ringkasan', compact(
            'itinerary',
            'preferensi',
            'guide',
            'guideCost',
            'gearDetails',
            'gearCost',
            'totalBayar',
            'isBundle',
            'bundleDiscount',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'bukti_upload' => 'required|file|mimes:jpg,png,pdf|max:2048',
        ], [
            'bukti_upload.required' => 'Bukti pembayaran wajib diunggah.',
            'bukti_upload.mimes' => 'File harus berformat JPG, PNG, atau PDF.',
            'bukti_upload.max' => 'Ukuran file maksimal 2MB.',
        ]);

        $buktiPath = $request->file('bukti_upload')->store('bukti', 'public');

        // TODO: ganti dengan Eloquent
        $pesanan = [
            'id' => time(),
            'email' => session('user.email'),
            'jenis_paket' => session('is_bundle') ? 'bundle' : 'custom',
            'tgl_pesan' => now()->toDateString(),
            'total_bayar' => $request->input('total_bayar', 0),
            'bukti_upload' => $buktiPath,
            'status_pesanan' => 'menunggu_validasi',
            'kode_tiket' => null,
            'itinerary' => session('itinerary'),
            'preferensi' => session('preferensi'),
        ];

        JsonStore::append('pesanan.json', $pesanan);

        // Clear session data
        session()->forget(['itinerary', 'preferensi', 'selected_guide', 'selected_gears', 'is_bundle']);

        return redirect()->route('pesanan.index')->with('success', 'Pesanan berhasil dibuat! Menunggu validasi admin.');
    }

    public function index(): View
    {
        // TODO: ganti dengan Eloquent
        $email = session('user.email');
        $allPesanan = JsonStore::read('pesanan.json');
        $pesanan = collect($allPesanan)->where('email', $email)->values();

        return view('pesanan.index', compact('pesanan'));
    }
}
