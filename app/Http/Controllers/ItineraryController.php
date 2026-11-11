<?php

namespace App\Http\Controllers;

use App\Services\ItineraryGenerator;
use App\Support\DummyData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    public function show(ItineraryGenerator $generator): View|RedirectResponse
    {
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

        // TODO: ganti dengan Eloquent
        $guides = collect(DummyData::tourGuide())->where('is_available', true)->values();
        $gears = collect(DummyData::travelGear())->values();
        $bundleDiscount = config('gianyar.bundle_discount', 0.10);
        $selectedGuide = session('selected_guide');
        $selectedGears = session('selected_gears', []);
        $isBundle = session('is_bundle', false);

        return view('itinerary.show', compact(
            'itinerary',
            'preferensi',
            'guides',
            'gears',
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

    public function saveAddons(Request $request): RedirectResponse
    {
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
            if (! session('selected_guide')) {
                $first = collect(DummyData::tourGuide())->firstWhere('is_available', true);
                if ($first) {
                    session(['selected_guide' => $first['id']]);
                }
            }
            $bundleGears = [];
            foreach (array_slice(DummyData::travelGear(), 0, 3) as $gear) {
                $bundleGears[$gear['id']] = 1;
            }
            session(['selected_gears' => $bundleGears]);
        }

        return redirect()->route('itinerary.show')->with('success', 'Add-ons diperbarui.');
    }
}
