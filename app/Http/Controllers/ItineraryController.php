<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Services\GearRecommendationService;
use App\Services\GuideMatchingService;
use App\Services\ItineraryEditService;
use App\Services\ItineraryGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    public function show(
        ItineraryGenerator $generator,
        GuideMatchingService $guideMatcher,
        GearRecommendationService $gearService,
        ItineraryEditService $editService
    ): View|RedirectResponse {
        $preferensi = session('preferensi');
        if (! $preferensi) {
            return redirect()->route('preferensi.create')->with('error', 'Silakan isi preferensi terlebih dahulu.');
        }

        $itineraryId = session('itinerary_id');
        $itineraryModel = null;
        if ($itineraryId) {
            $itineraryModel = Itinerary::with(['items.destinasi', 'exclusions.destinasi'])->find($itineraryId);
        }

        if (! $itineraryModel) {
            $raw = $generator->generate($preferensi);

            $itineraryModel = Itinerary::create([
                'user_id' => Auth::id(),
                'tgl_berangkat' => $raw['tgl_berangkat'],
                'tgl_pulang' => $raw['tgl_pulang'],
                'durasi' => $raw['durasi'],
                'jumlah_orang' => $raw['jumlah_orang'],
                'kategori' => $raw['kategori'],
                'tipe_traveler' => $raw['tipe_traveler'],
                'tingkat_fisik' => $raw['tingkat_fisik'],
                'status' => 'draft',
                'seed' => $raw['seed'],
                'seed_dasar' => $raw['seed_dasar'] ?? $raw['seed'],
                'versi' => 1,
                'jumlah_edit' => 0,
            ]);

            foreach ($raw['jadwal'] as $day) {
                foreach ($day['items'] as $item) {
                    ItineraryItem::create([
                        'itinerary_id' => $itineraryModel->id,
                        'destinasi_id' => $item['destinasi_id'] ?? $item['id'],
                        'hari_ke' => $day['hari_ke'],
                        'urutan' => $item['urutan'] ?? 1,
                        'slot' => $item['slot'],
                        'slot_label' => $item['slot_label'],
                        'jam_mulai' => $item['jam_mulai'],
                        'jam_selesai' => $item['jam_selesai'],
                        'durasi_menit' => $item['durasi_menit'],
                        'jarak_dari_sebelumnya_km' => $item['jarak_dari_sebelumnya_km'] ?? null,
                        'waktu_tempuh_menit' => $item['waktu_tempuh_menit'] ?? null,
                        'alasan' => $item['alasan'] ?? null,
                        'is_locked' => false,
                        'sumber' => 'generated',
                    ]);
                }
            }

            $editService->recordSnapshot($itineraryModel, 'generate', 'Pembangkitan jadwal otomatis awal');
            session(['itinerary_id' => $itineraryModel->id]);
        }

        // Format data untuk view dan simpan di session
        $itinerary = $editService->formatItineraryForService($itineraryModel);
        $itinerary['id'] = $itineraryModel->id;
        $itinerary['versi'] = $itineraryModel->versi;
        $itinerary['jumlah_edit'] = $itineraryModel->jumlah_edit;
        $itinerary['is_locked'] = $itineraryModel->isLocked();
        $itinerary['status'] = $itineraryModel->status;
        $itinerary['items_raw'] = $itineraryModel->items()->with('destinasi')->get();
        session(['itinerary' => $itinerary]);

        // Rekomendasi Tour Guide dinamis berdasarkan itinerary & ketersediaan tanggal
        $guides = $guideMatcher->getRecommendations($itinerary, $preferensi, 6);

        // Rekomendasi Travel Gear dinamis berdasarkan konteks destinasi
        $gearRecommendations = $gearService->getRecommendations($itinerary, $preferensi);
        $gears = collect($gearRecommendations['all_items'])->pluck('gear')->unique('id')->values();

        $bundleDiscount = config('gianyar.bundle_discount', 0.10);
        $selectedGuide = session('selected_guide');
        $selectedGears = session('selected_gears', []);
        $isBundle = session('is_bundle', false);

        return view('itinerary.show', compact(
            'itinerary',
            'itineraryModel',
            'preferensi',
            'guides',
            'gears',
            'gearRecommendations',
            'bundleDiscount',
            'selectedGuide',
            'selectedGears',
            'isBundle',
        ));
    }

    /**
     * Acak ulang itinerary dengan preferensi yang sama untuk variasi alternatif rute.
     */
    public function regenerate(ItineraryGenerator $generator): RedirectResponse
    {
        $preferensi = session('preferensi');
        if (! $preferensi) {
            return redirect()->route('preferensi.create')->with('error', 'Silakan isi preferensi terlebih dahulu.');
        }

        session()->forget(['itinerary', 'itinerary_id']);

        return redirect()->route('itinerary.show')->with('success', 'Itinerary berhasil diacak ulang dengan alternatif rute baru.');
    }

    public function save(): RedirectResponse
    {
        $itinerary = session('itinerary');
        if ($itinerary) {
            $itinerary['status'] = 'tersimpan';
            session(['itinerary' => $itinerary]);
        }

        return redirect()->route('itinerary.show')->with('success', 'Itinerary berhasil disimpan.');
    }

    public function saveAddons(
        Request $request,
        GuideMatchingService $guideMatcher,
        GearRecommendationService $gearService
    ): RedirectResponse {
        $itinerary = session('itinerary', []);
        $preferensi = session('preferensi', []);

        if ($request->filled('guide_id')) {
            session(['selected_guide' => (int) $request->guide_id]);
        }

        $gears = [];
        if ($request->has('gears')) {
            foreach ($request->gears as $gearId => $qty) {
                $qty = (int) $qty;
                if ($qty > 0) {
                    $gears[(int) $gearId] = $qty;
                }
            }
        }
        session(['selected_gears' => $gears]);

        if ($request->has('bundle')) {
            session(['is_bundle' => true]);

            // Pilih guide terbaik jika belum dipilih
            if (! session('selected_guide')) {
                $recommendedGuides = $guideMatcher->getRecommendations($itinerary, $preferensi, 1);
                if ($recommendedGuides->isNotEmpty()) {
                    session(['selected_guide' => $recommendedGuides->first()->id]);
                }
            }

            // Pilih 3 gear rekomendasi utama
            $gearRec = $gearService->getRecommendations($itinerary, $preferensi);
            $bundleGears = [];
            foreach (array_slice($gearRec['all_items'], 0, 3) as $item) {
                $bundleGears[$item['gear']->id] = $item['qty'];
            }
            if (! empty($bundleGears)) {
                session(['selected_gears' => $bundleGears]);
            }
        }

        return redirect()->route('itinerary.show')->with('success', 'Add-ons diperbarui.');
    }
}
