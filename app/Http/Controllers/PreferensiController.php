<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreferensiRequest;
use App\Support\DummyData;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PreferensiController extends Controller
{
    public function create(): View
    {
        // TODO: ganti dengan Eloquent
        $preferensi = session('preferensi', []);
        $kategoriList = DummyData::kategoriList();

        return view('preferensi.create', compact('preferensi', 'kategoriList'));
    }

    public function store(PreferensiRequest $request): RedirectResponse
    {
        $data = $request->validated();
        session(['preferensi' => $data]);
        session()->forget('itinerary');

        return redirect()->route('itinerary.show');
    }
}
