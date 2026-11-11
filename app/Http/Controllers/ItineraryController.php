<?php

namespace App\Http\Controllers;

use App\Services\GearRecommendationService;
use App\Services\GuideMatchingService;
use App\Services\ItineraryGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    public function show(
        ItineraryGenerator $generator,
        GuideMatchingService $guideMatcher,
        GearRecommendationService $gearService
    ): View|RedirectResponse {
        $preferensi = session('preferensi');
        if (! $preferensi) {
            return redirect()->route('preferensi.create')->with('error', 'Silakan isi preferensi terlebih dahulu.');
        }

        // Pertahankan itinerary di session jika sudah pernah di-generate (refresh-safe)
        if (! session()->has('itinerary')) {
            $itinerary = $generator->generate($preferensi);
            session(['itinerary' => $itinerary]);
        }
        $itinerary = session('itinerary');

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

        session()->forget('itinerary');
        $itinerary = $generator->generate($preferensi);
        session(['itinerary' => $itinerary]);

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
