@extends('layouts.app')
@section('title', 'Atur Preferensi Perjalanan — GianyarPlanner')

@section('content')
<x-stepper :current="1" :total="2" next_label="itinerary-mu" />

{{-- Header --}}
<div class="mb-10">
    <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-4">LANGKAH 1 DARI 2 · ATUR PREFERENSI</p>
    <h1 class="font-playfair text-4xl sm:text-5xl text-text tracking-tight leading-tight">Beritahu Kami Liburan Impianmu.</h1>
    <p class="text-muted text-[17px] mt-3">Tentukan waktu, gaya perjalanan, dan minat wisatamu. Sistem pintar kami akan menyusun itinerary yang variatif dan rute yang efisien.</p>
</div>

@if($errors->any())
    <x-alert type="error" class="mb-6">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

<form method="POST" action="{{ route('preferensi.store') }}" class="flex flex-col gap-10" id="preferensi-form">
    @csrf

    {{-- Tanggal, Durasi & Profil Perjalanan --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
        {{-- Tanggal & Jumlah Orang Card --}}
        <x-card class="p-8">
            <h2 class="font-playfair text-xl text-text mb-6">Waktu & Jumlah Wisatawan</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="flex flex-col gap-2">
                    <label for="tgl_berangkat" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Tanggal Check-in (Mulai)</label>
                    <input type="date" name="tgl_berangkat" id="tgl_berangkat"
                        value="{{ old('tgl_berangkat', $preferensi['tgl_berangkat'] ?? date('Y-m-d')) }}"
                        min="{{ date('Y-m-d') }}" required
                        class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none" />
                    @error('tgl_berangkat')
                        <p class="text-red-600 text-xs">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="tgl_pulang" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Tanggal Check-out (Selesai)</label>
                    <input type="date" name="tgl_pulang" id="tgl_pulang"
                        value="{{ old('tgl_pulang', $preferensi['tgl_pulang'] ?? date('Y-m-d', strtotime('+2 days'))) }}"
                        min="{{ date('Y-m-d', strtotime('+1 day')) }}" required
                        class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none" />
                    @error('tgl_pulang')
                        <p class="text-red-600 text-xs">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Profil Tambahan: Tipe Traveler & Tingkat Fisik --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-6 pt-6 border-t border-line/70">
                <div class="flex flex-col gap-2">
                    <label for="jumlah_orang" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Jumlah Orang</label>
                    <input type="number" name="jumlah_orang" id="jumlah_orang" min="1" max="50"
                        value="{{ old('jumlah_orang', $preferensi['jumlah_orang'] ?? 2) }}" required
                        class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="tipe_traveler" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Tipe Traveler</label>
                    <select name="tipe_traveler" id="tipe_traveler"
                        class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none">
                        @php
                            $currentTipe = old('tipe_traveler', $preferensi['tipe_traveler'] ?? 'couple');
                        @endphp
                        <option value="couple" {{ $currentTipe === 'couple' ? 'selected' : '' }}>Pasangan (Couple)</option>
                        <option value="solo" {{ $currentTipe === 'solo' ? 'selected' : '' }}>Solo Traveler</option>
                        <option value="family" {{ $currentTipe === 'family' ? 'selected' : '' }}>Keluarga (Family)</option>
                        <option value="group" {{ $currentTipe === 'group' ? 'selected' : '' }}>Rombongan (Group)</option>
                    </select>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="tingkat_fisik" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Intensitas Fisik</label>
                    <select name="tingkat_fisik" id="tingkat_fisik"
                        class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none">
                        @php
                            $currentFisik = old('tingkat_fisik', $preferensi['tingkat_fisik'] ?? 'sedang');
                        @endphp
                        <option value="santai" {{ $currentFisik === 'santai' ? 'selected' : '' }}>Santai (Jalur Ringan)</option>
                        <option value="sedang" {{ $currentFisik === 'sedang' ? 'selected' : '' }}>Sedang (Jalan Santai & Tangga)</option>
                        <option value="aktif" {{ $currentFisik === 'aktif' ? 'selected' : '' }}>Aktif / Petualang (Trekking / Rafting)</option>
                    </select>
                </div>
            </div>
        </x-card>

        {{-- Durasi Liburan Card --}}
        <x-card class="p-8 flex flex-col items-center justify-center text-center">
            <h2 class="font-playfair text-xl text-text mb-6">Durasi Liburan</h2>
            <div class="flex items-center gap-6">
                <button type="button" id="durasi-minus"
                    class="w-10 h-10 rounded-full border border-line flex items-center justify-center text-text text-xl hover:border-forest cursor-pointer select-none">−</button>
                <div>
                    <p id="durasi-value" class="font-inter font-bold text-2xl text-text">{{ old('durasi', $preferensi['durasi'] ?? 2) }} Hari</p>
                    <p id="malam-label" class="text-muted text-sm mt-1">{{ max(0, (old('durasi', $preferensi['durasi'] ?? 2)) - 1) }} Malam</p>
                </div>
                <button type="button" id="durasi-plus"
                    class="w-10 h-10 rounded-full bg-forest text-white flex items-center justify-center text-xl cursor-pointer hover:bg-forest-dark select-none">+</button>
            </div>
            <p class="text-xs text-muted mt-5">Sistem membagi 3 slot kunjungan optimal per hari</p>
            <input type="hidden" name="durasi" id="durasi-input" value="{{ old('durasi', $preferensi['durasi'] ?? 2) }}">
        </x-card>
    </div>

    {{-- Kategori Wisata Detail (2-Level Hierarchy) --}}
    <div>
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2 mb-6">
            <div>
                <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-2">PILIH MINAT ANDA</p>
                <h2 class="font-playfair text-3xl sm:text-4xl text-text">Kategori Wisata Gianyar</h2>
            </div>
            <p class="text-muted text-sm">Pilih satu atau beberapa subkategori yang Anda minati</p>
        </div>

        @error('kategori')
            <p class="text-red-600 text-sm mb-4 font-medium">{{ $message }}</p>
        @enderror

        @php
            $oldKategori = old('kategori', $preferensi['kategori'] ?? ['air_terjun', 'sawah', 'pura_candi']);
            $categories = $kategoriList ?? \App\Support\DummyData::kategoriList();
        @endphp

        <div class="flex flex-col gap-10">
            @foreach($categories as $katKey => $utama)
                <div class="border border-line rounded-lg p-6 bg-white" data-category-group="{{ $katKey }}">
                    {{-- Category Header & Select All Button --}}
                    <div class="flex items-center justify-between pb-4 mb-6 border-b border-line">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ $utama['icon'] }}</span>
                            <div>
                                <h3 class="font-playfair text-xl font-bold text-text">{{ $utama['label'] }}</h3>
                                <p class="text-xs text-muted">{{ count($utama['subkategori']) }} pilihan destinasi</p>
                            </div>
                        </div>
                        <button type="button" data-toggle-group="{{ $katKey }}"
                            class="text-xs font-inter font-semibold px-3 py-1.5 rounded border border-line text-muted hover:text-text hover:border-forest transition-colors cursor-pointer">
                            Pilih Semua
                        </button>
                    </div>

                    {{-- Subcategory Cards Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @foreach($utama['subkategori'] as $subKey => $sub)
                            @php
                                $isSelected = in_array($subKey, $oldKategori) || in_array($katKey, $oldKategori);
                            @endphp
                            <label data-kategori="{{ $subKey }}"
                                class="relative cursor-pointer block rounded-md overflow-hidden h-[200px] border-2 transition-all duration-200 group {{ $isSelected ? 'border-forest ring-1 ring-forest' : 'border-line hover:border-muted' }}">
                                <input type="checkbox" name="kategori[]" value="{{ $subKey }}" class="hidden" {{ $isSelected ? 'checked' : '' }}>
                                <img src="{{ $sub['foto'] }}" alt="{{ $sub['label'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>

                                {{-- Check Badge --}}
                                <div class="kategori-check absolute top-3 right-3 w-7 h-7 rounded-full bg-forest text-white flex items-center justify-center {{ $isSelected ? '' : 'hidden' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>

                                {{-- Text Info --}}
                                <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                                    <h4 class="font-playfair text-lg font-semibold leading-snug">{{ $sub['label'] }}</h4>
                                    <p class="text-white/80 text-xs mt-1 leading-relaxed line-clamp-2">{{ $sub['deskripsi'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Submit Button --}}
    <div class="pt-4">
        <x-button type="submit" class="w-full rounded-full text-base py-4">
            Generate Itinerary Sekarang
            <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </x-button>
    </div>
</form>

<div class="mt-8 pb-12">
    <a href="/" class="inline-flex items-center gap-2 text-muted text-sm hover:text-text">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Kembali ke Beranda
    </a>
</div>
@endsection
