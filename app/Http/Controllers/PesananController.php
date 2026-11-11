<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Destinasi;
use App\Models\GearReservation;
use App\Models\GuideAvailability;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Notification;
use App\Models\Pesanan;
use App\Models\PesananGuide;
use App\Models\PesananItem;
use App\Models\TourGuide;
use App\Models\TravelGear;
use App\Support\JsonStore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Pastikan user login untuk melanjutkan ke pesanan
        if (! Auth::check()) {
            return redirect()->route('login')->with('info', 'Silakan masuk atau buat akun terlebih dahulu untuk melanjutkan pemesanan.');
        }

        $selectedGuideId = session('selected_guide');
        $selectedGears = session('selected_gears', []);
        $isBundle = session('is_bundle', false);
        $bundleDiscount = (float) config('gianyar.bundle_discount', 0.10);

        // Ambil data Guide terpilih dari database
        $guide = null;
        $guideCost = 0;
        if ($selectedGuideId) {
            $guideModel = TourGuide::with('user')->find($selectedGuideId);
            if ($guideModel) {
                $durasi = (int) ($itinerary['durasi'] ?? 1);
                $guideCost = $guideModel->tarif_per_hari * $durasi;

                // Format kompatibel array untuk blade view
                $guide = [
                    'id' => $guideModel->id,
                    'nama' => $guideModel->user->nama ?? $guideModel->user->name,
                    'foto' => $guideModel->foto,
                    'rating' => $guideModel->rating_avg,
                    'bahasa' => implode(', ', (array) $guideModel->bahasa),
                    'tarif' => $guideModel->tarif_per_hari,
                    'area_layanan' => $guideModel->area_layanan,
                ];
            }
        }

        // Ambil data Gears terpilih dari database
        $gearCost = 0;
        $depositCost = 0;
        $gearDetails = [];
        if (! empty($selectedGears)) {
            $durasi = (int) ($itinerary['durasi'] ?? 1);
            $gearsList = TravelGear::with(['category', 'mitra'])->whereIn('id', array_keys($selectedGears))->get();

            foreach ($gearsList as $gearModel) {
                $qty = (int) ($selectedGears[$gearModel->id] ?? 0);
                if ($qty > 0) {
                    $unitPrice = ($gearModel->tipe === 'sewa') ? ($gearModel->harga * $durasi) : $gearModel->harga;
                    $subtotal = $unitPrice * $qty;
                    $itemDeposit = ($gearModel->tipe === 'sewa') ? ($gearModel->deposit * $qty) : 0;

                    $gearCost += $subtotal;
                    $depositCost += $itemDeposit;

                    $gearDetails[] = [
                        'id' => $gearModel->id,
                        'nama_barang' => $gearModel->nama_barang,
                        'tipe' => $gearModel->tipe,
                        'harga' => $gearModel->harga,
                        'deposit' => $gearModel->deposit,
                        'jumlah' => $qty,
                        'subtotal' => $subtotal,
                        'deposit_total' => $itemDeposit,
                        'mitra_nama' => $gearModel->mitra->nama_usaha ?? 'Mitra Gianyar',
                    ];
                }
            }
        }

        $totalBayar = $guideCost + $gearCost + $depositCost;
        if ($isBundle && ($guideCost + $gearCost) > 0) {
            $diskon = (int) round(($guideCost + $gearCost) * $bundleDiscount);
            $totalBayar = max(0, $totalBayar - $diskon);
        }

        return view('pesanan.ringkasan', compact(
            'itinerary',
            'preferensi',
            'guide',
            'guideCost',
            'gearDetails',
            'gearCost',
            'depositCost',
            'totalBayar',
            'isBundle',
            'bundleDiscount',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'bukti_upload' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], [
            'bukti_upload.required' => 'Bukti pembayaran wajib diunggah.',
            'bukti_upload.mimes' => 'File bukti pembayaran harus berupa format JPG, PNG, atau PDF.',
            'bukti_upload.max' => 'Ukuran file bukti pembayaran maksimal 2MB.',
        ]);

        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        $user = Auth::user();
        $itinerary = session('itinerary');
        $preferensi = session('preferensi');

        if (! $itinerary || ! $preferensi) {
            return redirect()->route('preferensi.create')->with('error', 'Data itinerary tidak ditemukan. Silakan buat itinerary baru.');
        }

        $selectedGuideId = session('selected_guide');
        $selectedGears = session('selected_gears', []);
        $isBundle = session('is_bundle', false);
        $totalBayarInput = (int) $request->input('total_bayar', 0);

        // Upload bukti pembayaran
        $buktiPath = $request->file('bukti_upload')->store('bukti', 'public');

        // Tanggal-tanggal perjalanan
        $dates = $this->extractDatesFromItinerary($itinerary);
        $startDate = $dates[0] ?? Carbon::today()->toDateString();
        $endDate = end($dates) ?: $startDate;
        $durasi = max(1, count($dates));

        try {
            $pesanan = DB::transaction(function () use (
                $user, $itinerary, $selectedGuideId, $selectedGears,
                $dates, $startDate, $endDate, $durasi, $buktiPath, $totalBayarInput
            ) {
                // 1. Simpan Header Itinerary ke Database
                $itineraryModel = Itinerary::create([
                    'user_id' => $user->id,
                    'tgl_berangkat' => $startDate,
                    'tgl_pulang' => $endDate,
                    'durasi' => $durasi,
                    'jumlah_orang' => (int) ($itinerary['jumlah_orang'] ?? 2),
                    'kategori' => $itinerary['kategori'] ?? ['alam'],
                    'tipe_traveler' => $itinerary['tipe_traveler'] ?? 'couple',
                    'tingkat_fisik' => $itinerary['tingkat_fisik'] ?? 'sedang',
                    'status' => 'terkunci',
                    'seed' => $itinerary['seed'] ?? crc32(uniqid()),
                    'seed_dasar' => $itinerary['seed_dasar'] ?? crc32(session()->getId()),
                    'versi' => 1,
                    'jumlah_edit' => 0,
                ]);

                // 2. Simpan Item Destinasi Itinerary
                if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
                    foreach ($itinerary['jadwal'] as $day) {
                        $hariKe = (int) ($day['hari_ke'] ?? 1);
                        if (! empty($day['items']) && is_array($day['items'])) {
                            foreach ($day['items'] as $idx => $item) {
                                // Cari id destinasi riil di DB
                                $destinasiId = $item['id'] ?? null;
                                if (! Destinasi::where('id', $destinasiId)->exists()) {
                                    $destinasi = Destinasi::where('slug', $item['slug'] ?? '')->first();
                                    $destinasiId = $destinasi ? $destinasi->id : Destinasi::value('id');
                                }

                                ItineraryItem::create([
                                    'itinerary_id' => $itineraryModel->id,
                                    'destinasi_id' => $destinasiId,
                                    'hari_ke' => $hariKe,
                                    'urutan' => $idx + 1,
                                    'slot' => $item['slot'] ?? 'pagi',
                                    'slot_label' => $item['slot_label'] ?? ucfirst($item['slot'] ?? 'Pagi'),
                                    'jam_mulai' => $item['jam_mulai'] ?? '08:30',
                                    'jam_selesai' => $item['jam_selesai'] ?? '10:00',
                                    'durasi_menit' => (int) ($item['durasi_menit'] ?? 90),
                                    'alasan' => $item['alasan'] ?? null,
                                    'is_locked' => false,
                                    'sumber' => 'generator',
                                ]);
                            }
                        }
                    }
                }

                // 3. Simpan Header Pesanan
                $pesanan = Pesanan::create([
                    'user_id' => $user->id,
                    'itinerary_id' => $itineraryModel->id,
                    'kode_tiket' => null, // Dibuat otomatis saat admin validasi pembayaran
                    'tgl_pesan' => Carbon::today(),
                    'total_bayar' => $totalBayarInput,
                    'status_pembayaran' => 'menunggu_verifikasi',
                    'bukti_upload' => $buktiPath,
                    'status_pesanan' => 'menunggu_konfirmasi',
                    'expires_at' => Carbon::now()->addHours(24),
                ]);

                // 4. PESANAN TOUR GUIDE (DENGAN ROW-LOCK PESSIMISTIC)
                if ($selectedGuideId) {
                    $guide = TourGuide::with('user')->find($selectedGuideId);
                    if ($guide) {
                        // Kunci baris ketersediaan guide pada tanggal-tanggal perjalanan (Anti double-booking)
                        $availabilities = GuideAvailability::where('guide_id', $guide->id)
                            ->whereIn('tanggal', $dates)
                            ->lockForUpdate()
                            ->get();

                        // Validasi ketersediaan seluruh tanggal
                        $isFullyAvailable = ($availabilities->count() === count($dates)) &&
                            $availabilities->every(fn ($a) => $a->status === 'tersedia');

                        if (! $isFullyAvailable) {
                            throw new \Exception("Pemandu wisata {$guide->user->nama} baru saja dipesan oleh pengguna lain untuk salah satu tanggal perjalanan Anda. Silakan pilih pemandu lain.");
                        }

                        // Kunci status menjadi 'ditahan'
                        foreach ($availabilities as $avail) {
                            $avail->update([
                                'status' => 'ditahan',
                                'booking_ref' => 'PESANAN-'.$pesanan->id,
                            ]);
                        }

                        // Buat entri PesananGuide
                        $pesananGuide = PesananGuide::create([
                            'pesanan_id' => $pesanan->id,
                            'guide_id' => $guide->id,
                            'tanggal_mulai' => $startDate,
                            'tanggal_selesai' => $endDate,
                            'tarif_snapshot' => $guide->tarif_per_hari,
                            'status' => 'menunggu_konfirmasi_guide',
                            'expires_at' => Carbon::now()->addMinutes(120),
                        ]);

                        // Notifikasi ke Guide
                        Notification::create([
                            'user_id' => $guide->user_id,
                            'tipe' => 'booking_guide',
                            'judul' => 'Permintaan Pemanduan Wisata Baru!',
                            'isi' => "Wisatawan {$user->nama} memesan jasa pemanduan Anda untuk tanggal {$startDate} s/d {$endDate} ({$durasi} hari).",
                        ]);
                    }
                }

                // 5. PESANAN TRAVEL GEAR (DENGAN ROW-LOCK PESSIMISTIC)
                if (! empty($selectedGears)) {
                    foreach ($selectedGears as $gearId => $qty) {
                        $qty = (int) $qty;
                        if ($qty <= 0) {
                            continue;
                        }

                        // Kunci baris travel gear untuk cek stok
                        $gear = TravelGear::with('mitra')->where('id', $gearId)->lockForUpdate()->first();
                        if (! $gear) {
                            continue;
                        }

                        if ($gear->stokTersedia($startDate, $endDate) < $qty) {
                            throw new \Exception("Stok barang '{$gear->nama_barang}' dari mitra {$gear->mitra->nama_usaha} tidak mencukupi untuk rentang tanggal tersebut.");
                        }

                        $itemDurasi = ($gear->tipe === 'sewa') ? $durasi : 1;
                        $itemSubtotal = $gear->harga * $itemDurasi * $qty;
                        $itemDeposit = ($gear->tipe === 'sewa') ? ($gear->deposit * $qty) : 0;

                        $pesananItem = PesananItem::create([
                            'pesanan_id' => $pesanan->id,
                            'gear_id' => $gear->id,
                            'mitra_id' => $gear->mitra_id,
                            'qty' => $qty,
                            'harga_snapshot' => $gear->harga,
                            'deposit_snapshot' => $gear->deposit,
                            'subtotal' => $itemSubtotal,
                            'deposit_total' => $itemDeposit,
                            'durasi_hari' => $itemDurasi,
                            'tanggal_mulai' => $startDate,
                            'tanggal_selesai' => $endDate,
                            'metode_serah' => $gear->mitra->metode_serah ?? 'keduanya',
                            'status' => 'menunggu_konfirmasi_mitra',
                        ]);

                        // Tahan stok reservasi
                        GearReservation::create([
                            'gear_id' => $gear->id,
                            'pesanan_item_id' => $pesananItem->id,
                            'tanggal_mulai' => $startDate,
                            'tanggal_selesai' => $endDate,
                            'qty' => $qty,
                            'status' => 'ditahan',
                        ]);

                        // Notifikasi ke Mitra
                        Notification::create([
                            'user_id' => $gear->mitra->user_id,
                            'tipe' => 'pesanan_gear',
                            'judul' => 'Pesanan Perlengkapan Masuk!',
                            'isi' => "Wisatawan {$user->nama} memesan {$gear->nama_barang} (x{$qty}) untuk periode {$startDate} s/d {$endDate}.",
                        ]);
                    }
                }

                // 6. Audit Log
                AuditLog::record($user->id, 'create_pesanan', 'Pesanan', $pesanan->id, [
                    'total_bayar' => $totalBayarInput,
                    'guide_id' => $selectedGuideId,
                    'gears_count' => count($selectedGears),
                ]);

                return $pesanan;
            });

            // Sinkronisasi kompatibilitas: tulis juga ke pesanan.json tanpa menghapus file lama
            $legacyPesanan = [
                'id' => $pesanan->id,
                'email' => $user->email,
                'jenis_paket' => $isBundle ? 'bundle' : 'custom',
                'tgl_pesan' => now()->toDateString(),
                'total_bayar' => $totalBayarInput,
                'bukti_upload' => $buktiPath,
                'status_pesanan' => 'menunggu_validasi',
                'kode_tiket' => null,
                'itinerary' => $itinerary,
                'preferensi' => $preferensi,
            ];
            JsonStore::append('pesanan.json', $legacyPesanan);

            // Bersihkan sesi
            session()->forget(['itinerary', 'preferensi', 'selected_guide', 'selected_gears', 'is_bundle']);

            return redirect()->route('pesanan.index')->with('success', 'Pesanan #'.$pesanan->id.' berhasil dibuat! Pembayaran Anda sedang diverifikasi admin.');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function index(): View
    {
        $user = Auth::user();

        if ($user) {
            $pesananEloquent = Pesanan::with(['itinerary', 'pesananGuide.guide.user', 'items.gear', 'items.mitra'])
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        } else {
            $pesananEloquent = collect();
        }

        // Mapping agar kompatibel penuh dengan blade view lama maupun baru
        $pesanan = $pesananEloquent->map(function (Pesanan $p) {
            return [
                'id' => $p->id,
                'email' => $p->user->email ?? '',
                'tgl_pesan' => $p->tgl_pesan ? $p->tgl_pesan->format('d M Y') : now()->format('d M Y'),
                'total_bayar' => $p->total_bayar,
                'status_pesanan' => $p->status_pembayaran === 'lunas' ? 'lunas' : ($p->status_pembayaran === 'ditolak' ? 'ditolak' : 'menunggu_validasi'),
                'status_pesanan_raw' => $p->status_pesanan,
                'status_pembayaran' => $p->status_pembayaran,
                'kode_tiket' => $p->kode_tiket,
                'jenis_paket' => $p->pesananGuide ? 'Guide + Wisata' : 'Wisata Mandiri',
                'items_count' => $p->items->count(),
            ];
        });

        // Jika DB kosong (misal belum ada pesanan baru), tampilkan data lama dari JsonStore
        if ($pesanan->isEmpty() && $user) {
            $allPesanan = JsonStore::read('pesanan.json');
            $pesanan = collect($allPesanan)->where('email', $user->email)->values();
        }

        return view('pesanan.index', compact('pesanan'));
    }

    /**
     * Ekstrak daftar tanggal unik dari jadwal itinerary.
     */
    protected function extractDatesFromItinerary(array $itinerary): array
    {
        $dates = [];
        if (! empty($itinerary['jadwal']) && is_array($itinerary['jadwal'])) {
            foreach ($itinerary['jadwal'] as $day) {
                if (! empty($day['tanggal'])) {
                    try {
                        $dates[] = Carbon::parse($day['tanggal'])->toDateString();
                    } catch (\Exception $e) {
                    }
                }
            }
        }
        if (empty($dates)) {
            $start = Carbon::today();
            $durasi = (int) ($itinerary['durasi'] ?? 1);
            for ($i = 0; $i < $durasi; $i++) {
                $dates[] = $start->copy()->addDays($i)->toDateString();
            }
        }

        return array_values(array_unique($dates));
    }
}
