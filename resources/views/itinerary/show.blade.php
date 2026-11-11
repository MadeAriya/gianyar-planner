@extends('layouts.app')
@section('title', 'Jadwal Perjalanan Anda — GianyarPlanner')

@section('content')
<x-stepper :current="2" :total="2" />

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-8">
    <div>
        <h1 class="font-playfair text-4xl sm:text-5xl text-text tracking-tight leading-tight">Jadwal Perjalanan Anda</h1>
        <p class="text-muted text-[15px] mt-2">
            {{ $itinerary['durasi'] }} Hari {{ max(0, $itinerary['durasi'] - 1) }} Malam · {{ $itinerary['jumlah_orang'] }} Orang
            @if(!empty($itinerary['tipe_traveler']))
                · {{ ucfirst($itinerary['tipe_traveler']) }}
            @endif
            · Wisata {{ implode(', ', array_map(fn($k) => \App\Support\DummyData::kategoriLabel($k), (array) $itinerary['kategori'])) }}
        </p>
    </div>
    <div class="flex items-center gap-3 mt-1 sm:mt-2">
        {{-- Generate Ulang / Acak Rute Button --}}
        <form method="POST" action="{{ route('itinerary.regenerate') }}" class="inline">
            @csrf
            <button type="submit" title="Dapatkan rekomendasi variasi rute lain dengan preferensi yang sama"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-inter font-semibold rounded border border-forest text-forest hover:bg-forest hover:text-white transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Acak Ulang Rute
            </button>
        </form>
        <a href="{{ route('preferensi.create') }}" class="text-xs text-muted hover:text-text underline whitespace-nowrap">Edit Preferensi</a>
    </div>
</div>

@if(session('success'))
    <x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>
@endif

{{-- Main layout: left timeline + right sidebar --}}
<div class="flex flex-col lg:flex-row gap-10">

    {{-- LEFT: Day tabs + Timeline --}}
    <div class="flex-1 min-w-0">

        {{-- Day tabs --}}
        <div class="flex gap-6 border-b border-line mb-8 overflow-x-auto">
            @foreach($itinerary['jadwal'] as $day)
                <button type="button" data-day-tab="{{ $day['hari_ke'] }}"
                    class="pb-3 px-1 font-inter text-sm tracking-wide uppercase whitespace-nowrap cursor-pointer select-none {{ $loop->first ? 'border-b-2 border-forest text-forest font-semibold' : 'text-muted' }}">
                    HARI {{ $day['hari_ke'] }}
                </button>
            @endforeach
        </div>

        {{-- Day content panels --}}
        @foreach($itinerary['jadwal'] as $day)
            <div data-day-content="{{ $day['hari_ke'] }}" class="{{ $loop->first ? '' : 'hidden' }}">
                {{-- Day header --}}
                <div class="flex items-center justify-between mb-8 pb-3 border-b border-line/60">
                    <div>
                        <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider">HARI {{ $day['hari_ke'] }}</h2>
                        <span class="text-muted text-xs">{{ $day['tanggal'] }}</span>
                    </div>
                    @if(!empty($day['zona']))
                        <span class="text-xs px-2.5 py-1 rounded bg-sand text-muted font-medium">Wilayah: {{ $day['zona'] }}</span>
                    @endif
                </div>

                {{-- Timeline --}}
                <div class="relative pl-6 border-l border-line">
                    @foreach($day['items'] as $item)
                        <div class="relative mb-10 last:mb-0">
                            {{-- Orange dot --}}
                            <div class="absolute -left-[31px] top-1 w-3 h-3 rounded-full bg-terracotta"></div>

                            {{-- Time and Distance Bar --}}
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <p class="text-terracotta font-inter text-sm font-semibold">{{ $item['jam_mulai'] }} – {{ $item['jam_selesai'] }}</p>
                                <span class="text-xs text-muted/60">·</span>
                                <span class="text-xs font-inter text-muted uppercase tracking-wider font-medium">{{ $item['slot_label'] ?? ucfirst($item['slot']) }} (±{{ $item['durasi_menit'] ?? 90 }} mnt)</span>
                                @if(!empty($item['jarak_dari_sebelumnya_km']))
                                    <span class="text-xs text-muted/60">·</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-sand/80 text-muted font-medium font-inter">
                                        📍 ±{{ $item['jarak_dari_sebelumnya_km'] }} km ({{ $item['waktu_tempuh_menit'] }} mnt perjalanan)
                                    </span>
                                @endif
                            </div>

                            {{-- Content row: text + image --}}
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-playfair text-xl font-bold text-text">{{ $item['destinasi']['nama_destinasi'] ?? $item['destinasi']['nama'] }}</h3>
                                        @if(isset($item['destinasi']['kategori']))
                                            <span class="px-2 py-0.5 text-[11px] rounded bg-sand text-muted font-medium font-inter">
                                                {{ \App\Support\DummyData::kategoriLabel($item['destinasi']['kategori']) }}
                                            </span>
                                        @endif
                                        @if(isset($item['destinasi']['harga_tiket']))
                                            <span class="text-xs text-muted">
                                                {{ $item['destinasi']['harga_tiket'] > 0 ? 'Rp ' . number_format($item['destinasi']['harga_tiket'], 0, ',', '.') : 'Gratis' }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-muted text-sm mt-1.5 leading-relaxed">{{ $item['destinasi']['deskripsi'] }}</p>

                                    @if(isset($item['destinasi']['lokasi']))
                                        <p class="text-xs text-muted/80 mt-1.5 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-terracotta flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            {{ $item['destinasi']['lokasi'] }}
                                        </p>
                                    @endif

                                    {{-- Alasan Personalisasi --}}
                                    @if(!empty($item['alasan']))
                                        <div class="mt-2.5 text-xs text-forest/90 bg-[#F4F6F4] border border-[#E3EBE5] px-3 py-1.5 rounded flex items-start gap-1.5">
                                            <svg class="w-3.5 h-3.5 mt-0.5 text-forest flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="leading-relaxed"><strong>Saran Cerdas:</strong> {{ $item['alasan'] }}</span>
                                        </div>
                                    @endif

                                    {{-- Tips & Rekomendasi Perlengkapan --}}
                                    @if(!empty($item['destinasi']['tips']) || !empty($item['destinasi']['rekomendasi_travel_gear']))
                                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted">
                                            @if(!empty($item['destinasi']['tips']))
                                                <span>💡 {{ $item['destinasi']['tips'] }}</span>
                                            @endif
                                            @if(!empty($item['destinasi']['rekomendasi_travel_gear']))
                                                <span class="text-terracotta">🎒 Saran Gear: {{ implode(', ', (array) $item['destinasi']['rekomendasi_travel_gear']) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <img src="{{ $item['destinasi']['foto'] }}" alt="{{ $item['destinasi']['nama_destinasi'] ?? $item['destinasi']['nama'] }}"
                                     class="w-full sm:w-32 h-24 object-cover rounded-md flex-shrink-0" loading="lazy" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- RIGHT: Sidebar (add-ons) --}}
    <div class="w-full lg:w-[380px] flex-shrink-0">
        <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-3">OPSIONAL</p>
        <h2 class="font-playfair text-2xl text-text mb-6">Sempurnakan Liburan Anda</h2>

        <form method="POST" action="{{ route('itinerary.addons') }}" id="addons-form">
            @csrf

            {{-- Tour Guide --}}
            <div class="mb-8">
                <h3 class="font-inter font-semibold text-text text-[15px] mb-1">Pilih Tour Guide</h3>
                <p class="text-muted text-xs mb-4">Pilih satu teman perjalanan lokal.</p>

                <div class="flex flex-col gap-3">
                    @foreach($guides as $guide)
                        @php $isSelectedGuide = ($selectedGuide === $guide['id']); @endphp
                        <x-card data-guide-card="{{ $guide['id'] }}"
                            class="p-4 cursor-pointer transition-all {{ $isSelectedGuide ? 'border-forest ring-1 ring-forest' : 'hover:border-muted' }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $guide['foto'] }}" alt="{{ $guide['nama'] }}" class="w-12 h-12 rounded-full object-cover flex-shrink-0" loading="lazy" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-inter font-bold text-sm text-text">{{ $guide['nama'] }}</p>
                                            <span class="guide-check text-forest text-xs {{ $isSelectedGuide ? '' : 'hidden' }}">✓</span>
                                        </div>
                                        <p class="text-xs text-muted mt-0.5">★ {{ $guide['rating'] }} · {{ $guide['bahasa'] }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-inter font-bold text-xs text-text" data-tarif="{{ $guide['tarif'] }}">Rp {{ number_format($guide['tarif'], 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-muted">/ hari</p>
                                    <button type="button" data-guide-select="{{ $guide['id'] }}"
                                        class="mt-1 px-3 py-1 text-xs rounded border border-line hover:border-forest text-text cursor-pointer">
                                        {{ $isSelectedGuide ? 'Terpilih' : 'Pilih' }}
                                    </button>
                                </div>
                            </div>
                        </x-card>
                    @endforeach
                </div>
                <input type="hidden" name="guide_id" id="selected-guide-input" value="{{ $selectedGuide ?? '' }}">
            </div>

            {{-- Sewa Perlengkapan --}}
            <div class="mb-8">
                <h3 class="font-inter font-semibold text-text text-[15px] mb-1">Sewa Perlengkapan</h3>
                <p class="text-muted text-xs mb-4">Pilih perlengkapan yang dibutuhkan.</p>

                <div class="flex flex-col gap-3">
                    @foreach($gears as $gear)
                        @php $gearQty = $selectedGears[$gear['id']] ?? 0; @endphp
                        <x-card class="p-3" data-gear-row="{{ $gear['id'] }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $gear['foto'] }}" alt="{{ $gear['nama_barang'] }}" class="w-10 h-10 rounded object-cover flex-shrink-0" loading="lazy" />
                                    <div>
                                        <p class="font-inter font-medium text-xs text-text">{{ $gear['nama_barang'] }}</p>
                                        <p class="text-[11px] text-muted" data-gear-harga="{{ $gear['id'] }}" data-harga="{{ $gear['harga'] }}">
                                            Rp {{ number_format($gear['harga'], 0, ',', '.') }} / hari
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" data-gear-minus="{{ $gear['id'] }}"
                                        class="w-7 h-7 rounded border border-line flex items-center justify-center text-xs hover:border-forest cursor-pointer select-none">−</button>
                                    <input type="text" readonly name="gears[{{ $gear['id'] }}]" id="gear-qty-{{ $gear['id'] }}"
                                        value="{{ $gearQty }}" data-max="{{ $gear['stok'] }}"
                                        class="w-8 text-center text-xs font-inter font-bold bg-transparent outline-none">
                                    <button type="button" data-gear-plus="{{ $gear['id'] }}"
                                        class="w-7 h-7 rounded border border-line flex items-center justify-center text-xs hover:border-forest cursor-pointer select-none">+</button>
                                </div>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>

            {{-- Paket Hemat Bundle --}}
            <x-card class="p-5 bg-sand/40 border-dashed border-terracotta/40">
                <p class="font-inter font-bold text-terracotta text-[11px] tracking-wider uppercase mb-1">PAKET HEMAT</p>
                <h4 class="font-playfair text-lg text-text mb-2">Guide + Gear sekaligus, diskon 10%</h4>
                <p class="text-xs text-muted mb-4">Pilih guide lokal terbaik dan paket 3 perlengkapan rekomendasi otomatis dengan potongan harga khusus.</p>
                <button type="submit" name="bundle" value="1"
                    class="w-full h-11 bg-forest hover:bg-forest-dark text-white rounded font-inter text-xs font-semibold tracking-wide cursor-pointer transition-colors">
                    Ambil Paket Bundle
                </button>
            </x-card>
        </form>
    </div>
</div>

{{-- Sticky Bottom Summary Bar --}}
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-line py-4 px-6 sm:px-12 z-40">
    <div class="max-w-[1440px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <p class="text-xs font-inter uppercase tracking-wider text-muted">Estimasi Total (Guide + Gear)</p>
            <p class="font-playfair text-2xl font-bold text-text" id="estimasi-total">Rp —</p>
        </div>
        <div class="flex items-center gap-4 w-full sm:w-auto">
            <form method="POST" action="{{ route('itinerary.save') }}" class="flex-1 sm:flex-initial">
                @csrf
                <button type="submit"
                    class="w-full sm:w-auto h-12 px-6 border border-line hover:border-text rounded text-sm font-inter font-medium text-text cursor-pointer transition-colors">
                    Simpan Itinerary
                </button>
            </form>
            <a href="{{ route('pesanan.ringkasan') }}"
                class="flex-1 sm:flex-initial h-12 px-8 bg-forest hover:bg-forest-dark text-white rounded text-sm font-inter font-medium inline-flex items-center justify-center transition-colors">
                Lanjut ke Pembayaran →
            </a>
        </div>
    </div>
</div>

<input type="hidden" id="itinerary-durasi" value="{{ $itinerary['durasi'] }}">
<input type="hidden" id="bundle-discount" value="{{ $bundleDiscount }}">
<input type="hidden" id="is-bundle" value="{{ $isBundle ? '1' : '0' }}">

<div class="h-24"></div>
@endsection
