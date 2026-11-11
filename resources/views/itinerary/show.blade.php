@extends('layouts.app')
@section('title', 'Jadwal Perjalanan Anda — GianyarPlanner')

@section('content')
<x-stepper :current="2" :total="2" />

<input type="hidden" id="itinerary-id" value="{{ $itinerary['id'] ?? ($itineraryModel->id ?? '') }}">
<input type="hidden" id="itinerary-version" value="{{ $itinerary['versi'] ?? 1 }}">

{{-- Status Banner jika Terkunci (Pesanan Aktif) --}}
@if(!empty($itinerary['is_locked']))
    <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-300 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="text-2xl mt-0.5">🔒</span>
            <div>
                <h4 class="font-inter font-bold text-sm text-amber-900">Itinerary ini Terkunci (Pesanan Aktif)</h4>
                <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                    Jadwal tidak dapat diedit langsung karena pesanan guide atau gear sudah aktif/lunas.
                    Anda dapat menduplikatnya untuk membuat rencana perjalanan baru yang bebas dimodifikasi.
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('itineraries.duplicate', $itinerary['id']) }}">
            @csrf
            <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded font-inter text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer shadow-sm">
                Duplikat sebagai Draft Baru
            </button>
        </form>
    </div>
@endif

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-8">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="font-playfair text-4xl sm:text-5xl text-text tracking-tight leading-tight">Jadwal Perjalanan Anda</h1>
            <span class="px-2.5 py-1 text-xs rounded-full bg-sand text-muted font-medium border border-line">
                Versi <strong class="text-text" id="label-versi">{{ $itinerary['versi'] ?? 1 }}</strong> · <span id="label-edits">{{ $itinerary['jumlah_edit'] ?? 0 }}</span> kali diedit
            </span>
        </div>
        <p class="text-muted text-[15px] mt-2">
            {{ $itinerary['durasi'] }} Hari {{ max(0, $itinerary['durasi'] - 1) }} Malam · {{ $itinerary['jumlah_orang'] }} Orang
            @if(!empty($itinerary['tipe_traveler']))
                · {{ ucfirst($itinerary['tipe_traveler']) }}
            @endif
            · Wisata {{ implode(', ', array_map(fn($k) => \App\Support\DummyData::kategoriLabel($k), (array) $itinerary['kategori'])) }}
        </p>
    </div>

    {{-- Top Action Toolbar --}}
    <div class="flex flex-wrap items-center gap-2 mt-1 sm:mt-2">
        @if(empty($itinerary['is_locked']))
            {{-- Undo Button --}}
            <button type="button" id="btn-undo"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-inter font-semibold rounded border border-line text-text hover:border-forest transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                {{ ($itinerary['versi'] ?? 1) <= 1 ? 'disabled' : '' }}
                title="Batalkan perubahan terakhir (Undo)">
                <span>↩</span> Urungkan
            </button>

            {{-- Redo Button --}}
            <button type="button" id="btn-redo"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-inter font-semibold rounded border border-line text-text hover:border-forest transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                disabled
                title="Ulangi perubahan (Redo)">
                <span>↪</span> Ulangi
            </button>

            {{-- Blacklist / Exclusions Manager --}}
            <button type="button" id="btn-open-exclusions"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-inter font-semibold rounded border border-line text-muted hover:border-muted hover:text-text transition-colors cursor-pointer"
                title="Lihat destinasi yang disembunyikan">
                <span>🚫</span> Disembunyikan (<span id="exclusions-count">{{ isset($itineraryModel) && $itineraryModel->exclusions ? $itineraryModel->exclusions->count() : 0 }}</span>)
            </button>

            {{-- Reset Button --}}
            <button type="button" id="btn-reset"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-inter font-semibold rounded border border-line text-muted hover:text-red-600 hover:border-red-300 transition-colors cursor-pointer"
                title="Kembalikan ke jadwal semula sebelum diedit">
                <span>↺</span> Reset
            </button>

            {{-- Generate Ulang Total Button --}}
            <form method="POST" action="{{ route('itinerary.regenerate') }}" class="inline">
                @csrf
                <button type="submit" title="Dapatkan rekomendasi variasi rute acak lain dengan preferensi yang sama"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-inter font-semibold rounded border border-forest text-forest hover:bg-forest hover:text-white transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Acak Total
                </button>
            </form>
        @endif

        <a href="{{ route('preferensi.create') }}" class="text-xs text-muted hover:text-text underline whitespace-nowrap ml-1">Edit Preferensi</a>
    </div>
</div>

@if(session('success'))
    <x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>
@endif
@if(session('info'))
    <x-alert type="info" class="mb-6">{{ session('info') }}</x-alert>
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
                {{-- Day header with Partial Regenerate & Add Buttons --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-8 pb-3 border-b border-line/60">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider">HARI {{ $day['hari_ke'] }}</h2>
                            <span class="text-xs text-muted">({{ count($day['items']) }} Kunjungan)</span>
                        </div>
                        <span class="text-muted text-xs">{{ $day['tanggal'] }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if(!empty($day['zona']))
                            <span class="text-xs px-2.5 py-1 rounded bg-sand text-muted font-medium">Wilayah: {{ $day['zona'] }}</span>
                        @endif

                        @if(empty($itinerary['is_locked']))
                            <button type="button" data-action="regen-day" data-day="{{ $day['hari_ke'] }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-inter font-medium rounded border border-line hover:border-forest text-muted hover:text-forest transition-colors cursor-pointer"
                                title="Acak ulang hanya destinasi yang tidak dikunci pada hari ini">
                                <span>🎲</span> Acak Hari Ini
                            </button>
                            <button type="button" data-action="open-add-modal" data-day="{{ $day['hari_ke'] }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-inter font-medium rounded bg-forest/10 hover:bg-forest text-forest hover:text-white transition-colors cursor-pointer"
                                title="Tambah destinasi baru ke rute hari ini">
                                <span>+</span> Tambah Destinasi
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Timeline --}}
                <div class="relative pl-6 border-l border-line" data-day-timeline="{{ $day['hari_ke'] }}">
                    @forelse($day['items'] as $item)
                        @php
                            $itemId = $item['id'] ?? $item['item_id'];
                            $destId = $item['destinasi_id'] ?? ($item['destinasi']['id'] ?? $item['id']);
                            $destName = $item['destinasi']['nama_destinasi'] ?? ($item['destinasi']['nama'] ?? $item['nama']);
                            $destFoto = $item['destinasi']['foto'] ?? 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=400&q=80';
                            $destKategori = $item['destinasi']['kategori'] ?? 'alam';
                            $destHarga = $item['destinasi']['harga_tiket'] ?? 0;
                            $destLokasi = $item['destinasi']['lokasi'] ?? ($item['destinasi']['kecamatan'] ?? 'Gianyar');
                            $destDeskripsi = $item['destinasi']['deskripsi'] ?? '';
                            $destTips = $item['destinasi']['tips'] ?? '';
                            $destGears = $item['destinasi']['rekomendasi_travel_gear'] ?? [];
                            $isItemLocked = !empty($item['is_locked']);
                            $itemSumber = $item['sumber'] ?? 'generated';
                        @endphp
                        <div class="relative mb-10 last:mb-0 group/card p-3 -ml-3 rounded-xl border border-transparent hover:border-line hover:bg-white/60 transition-all"
                            data-timeline-item="{{ $itemId }}"
                            data-dest-id="{{ $destId }}"
                            data-day="{{ $day['hari_ke'] }}"
                            data-urutan="{{ $item['urutan'] }}">

                            {{-- Orange dot --}}
                            <div class="absolute -left-[19px] top-4 w-3 h-3 rounded-full {{ $isItemLocked ? 'bg-forest ring-4 ring-forest/20' : 'bg-terracotta' }}"></div>

                            {{-- Time, Slot, Distance, & Quick Action Toolbar --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-terracotta font-inter text-sm font-semibold">{{ $item['jam_mulai'] }} – {{ $item['jam_selesai'] }}</p>
                                    <span class="text-xs text-muted/60">·</span>
                                    <span class="text-xs font-inter text-muted uppercase tracking-wider font-medium">{{ $item['slot_label'] ?? ucfirst($item['slot']) }}</span>

                                    {{-- Duration chip with click-to-edit --}}
                                    <button type="button" data-action="edit-duration" data-item-id="{{ $itemId }}" data-duration="{{ $item['durasi_menit'] ?? 90 }}"
                                        class="text-[11px] px-2 py-0.5 rounded bg-sand hover:bg-sand/80 text-muted font-medium cursor-pointer transition-colors"
                                        title="Ubah lama kunjungan">
                                        ⏱️ ±{{ $item['durasi_menit'] ?? 90 }} mnt ✏️
                                    </button>

                                    @if(!empty($item['jarak_dari_sebelumnya_km']))
                                        <span class="text-xs text-muted/60">·</span>
                                        <span class="text-[11px] px-2 py-0.5 rounded bg-sand/80 text-muted font-medium font-inter">
                                            📍 ±{{ $item['jarak_dari_sebelumnya_km'] }} km ({{ $item['waktu_tempuh_menit'] }} mnt perjalanan)
                                        </span>
                                    @endif

                                    {{-- Status Badges --}}
                                    @if($isItemLocked)
                                        <span class="px-2 py-0.5 text-[10px] bg-forest text-white font-semibold rounded flex items-center gap-1" title="Destinasi ini dikunci">
                                            📌 Terkunci
                                        </span>
                                    @endif
                                    @if($itemSumber === 'diganti')
                                        <span class="px-1.5 py-0.2 text-[10px] bg-blue-50 text-blue-700 font-semibold rounded">🔄 Diganti</span>
                                    @elseif($itemSumber === 'manual')
                                        <span class="px-1.5 py-0.2 text-[10px] bg-purple-50 text-purple-700 font-semibold rounded">➕ Ditambahkan</span>
                                    @endif
                                </div>

                                {{-- Inline Item Action Buttons --}}
                                @if(empty($itinerary['is_locked']))
                                    <div class="flex items-center gap-1 opacity-90 sm:opacity-0 group-hover/card:opacity-100 transition-opacity">
                                        {{-- Toggle Lock (Pin) --}}
                                        <button type="button" data-action="toggle-lock" data-item-id="{{ $itemId }}"
                                            class="px-2 py-1 text-xs rounded border border-line hover:border-forest text-text transition-colors cursor-pointer {{ $isItemLocked ? 'bg-forest/10 border-forest text-forest font-semibold' : '' }}"
                                            title="{{ $isItemLocked ? 'Lepas kunci destinasi' : 'Kunci destinasi agar tidak teracak saat generate' }}">
                                            {{ $isItemLocked ? '📌 Lepas' : '🔓 Kunci' }}
                                        </button>

                                        {{-- Move Up / Down Buttons --}}
                                        <button type="button" data-action="move-up" data-item-id="{{ $itemId }}"
                                            class="w-6 h-6 rounded border border-line hover:border-forest text-xs flex items-center justify-center text-muted hover:text-text cursor-pointer transition-colors"
                                            title="Pindah urutan ke atas">
                                            ▲
                                        </button>
                                        <button type="button" data-action="move-down" data-item-id="{{ $itemId }}"
                                            class="w-6 h-6 rounded border border-line hover:border-forest text-xs flex items-center justify-center text-muted hover:text-text cursor-pointer transition-colors"
                                            title="Pindah urutan ke bawah">
                                            ▼
                                        </button>

                                        {{-- Smart Swap Button --}}
                                        <button type="button" data-action="open-swap-modal"
                                            data-item-id="{{ $itemId }}"
                                            data-dest-id="{{ $destId }}"
                                            data-dest-nama="{{ $destName }}"
                                            data-dest-kategori="{{ $destKategori }}"
                                            data-dest-foto="{{ $destFoto }}"
                                            class="px-2.5 py-1 text-xs font-semibold rounded bg-forest/10 hover:bg-forest text-forest hover:text-white transition-colors cursor-pointer"
                                            title="Ganti destinasi ini dengan rekomendasi cerdas alternatif">
                                            🔄 Ganti
                                        </button>

                                        {{-- Delete Button --}}
                                        <button type="button" data-action="open-delete-modal"
                                            data-item-id="{{ $itemId }}"
                                            data-dest-id="{{ $destId }}"
                                            data-dest-nama="{{ $destName }}"
                                            class="w-6 h-6 rounded border border-line hover:border-red-400 text-xs flex items-center justify-center text-muted hover:text-red-600 cursor-pointer transition-colors"
                                            title="Hapus destinasi dari jadwal">
                                            🗑️
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Content row: text + image --}}
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-playfair text-xl font-bold text-text">{{ $destName }}</h3>
                                        <span class="px-2 py-0.5 text-[11px] rounded bg-sand text-muted font-medium font-inter">
                                            {{ \App\Support\DummyData::kategoriLabel($destKategori) }}
                                        </span>
                                        <span class="text-xs text-muted">
                                            {{ $destHarga > 0 ? 'Rp ' . number_format($destHarga, 0, ',', '.') : 'Gratis' }}
                                        </span>
                                    </div>

                                    <p class="text-muted text-sm mt-1.5 leading-relaxed">{{ $destDeskripsi }}</p>

                                    @if(!empty($destLokasi))
                                        <p class="text-xs text-muted/80 mt-1.5 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-terracotta flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            {{ $destLokasi }}
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
                                    @if(!empty($destTips) || !empty($destGears))
                                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted">
                                            @if(!empty($destTips))
                                                <span>💡 {{ $destTips }}</span>
                                            @endif
                                            @if(!empty($destGears))
                                                <span class="text-terracotta">🎒 Saran Gear: {{ implode(', ', (array) $destGears) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <img src="{{ $destFoto }}" alt="{{ $destName }}"
                                     class="w-full sm:w-32 h-24 object-cover rounded-md flex-shrink-0" loading="lazy" />
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center border border-dashed border-line rounded-lg">
                            <p class="text-sm text-muted">Belum ada kunjungan untuk hari ini.</p>
                            @if(empty($itinerary['is_locked']))
                                <button type="button" data-action="open-add-modal" data-day="{{ $day['hari_ke'] }}"
                                    class="mt-2 px-3 py-1.5 text-xs bg-forest text-white rounded font-medium cursor-pointer">
                                    + Tambah Destinasi Pertama
                                </button>
                            @endif
                        </div>
                    @endforelse
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
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-inter font-semibold text-text text-[15px]">Pilih Tour Guide</h3>
                    <span class="text-[10px] bg-forest/10 text-forest font-semibold px-2 py-0.5 rounded">Tersedia di Tanggal Ini</span>
                </div>
                <p class="text-muted text-xs mb-4">Pemandu lokal berlisensi yang cocok dengan rute Anda.</p>

                <div class="flex flex-col gap-3">
                    @forelse($guides as $guide)
                        @php
                            $gId = is_array($guide) ? $guide['id'] : $guide->id;
                            $gNama = is_array($guide) ? $guide['nama'] : ($guide->user->nama ?? $guide->user->name ?? $guide->nama);
                            $gFoto = is_array($guide) ? $guide['foto'] : $guide->foto;
                            $gRating = is_array($guide) ? $guide['rating'] : $guide->rating_avg;
                            $gTarif = is_array($guide) ? $guide['tarif'] : $guide->tarif_per_hari;
                            $gBahasa = is_array($guide) ? $guide['bahasa'] : (is_array($guide->bahasa) ? implode(', ', $guide->bahasa) : $guide->bahasa);
                            $gAlasan = is_object($guide) && !empty($guide->alasan_rekomendasi) ? $guide->alasan_rekomendasi : null;
                            $isSelectedGuide = ($selectedGuide == $gId);
                        @endphp
                        <x-card data-guide-card="{{ $gId }}"
                            class="p-4 cursor-pointer transition-all {{ $isSelectedGuide ? 'border-forest ring-1 ring-forest' : 'hover:border-muted' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <img src="{{ $gFoto }}" alt="{{ $gNama }}" class="w-12 h-12 rounded-full object-cover flex-shrink-0 mt-0.5" loading="lazy" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-inter font-bold text-sm text-text">{{ $gNama }}</p>
                                            <span class="guide-check text-forest text-xs {{ $isSelectedGuide ? '' : 'hidden' }}">✓</span>
                                        </div>
                                        <p class="text-xs text-muted mt-0.5">★ {{ number_format((float)$gRating, 2) }} · {{ $gBahasa }}</p>
                                        @if($gAlasan)
                                            <p class="text-[10px] text-forest font-medium mt-1 leading-snug">💡 {{ $gAlasan }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-inter font-bold text-xs text-text" data-tarif="{{ $gTarif }}">Rp {{ number_format($gTarif, 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-muted">/ hari</p>
                                    <button type="button" data-guide-select="{{ $gId }}"
                                        class="mt-1 px-3 py-1 text-xs rounded border border-line hover:border-forest text-text cursor-pointer">
                                        {{ $isSelectedGuide ? 'Terpilih' : 'Pilih' }}
                                    </button>
                                </div>
                            </div>
                        </x-card>
                    @empty
                        <p class="text-xs text-muted italic">Tidak ada tour guide yang tersedia pada tanggal ini.</p>
                    @endforelse
                </div>
                <input type="hidden" name="guide_id" id="selected-guide-input" value="{{ $selectedGuide ?? '' }}">
            </div>

            {{-- Sewa Perlengkapan --}}
            <div class="mb-8">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-inter font-semibold text-text text-[15px]">Perlengkapan Rekomendasi</h3>
                    <span class="text-[10px] bg-terracotta/10 text-terracotta font-semibold px-2 py-0.5 rounded">Sesuai Aktivitas</span>
                </div>
                <p class="text-muted text-xs mb-4">Diselaraskan otomatis dengan jenis destinasi rute Anda.</p>

                <div class="flex flex-col gap-3">
                    @forelse($gears as $gear)
                        @php
                            $gearId = is_array($gear) ? $gear['id'] : $gear->id;
                            $gearNama = is_array($gear) ? $gear['nama_barang'] : $gear->nama_barang;
                            $gearFoto = is_array($gear) ? $gear['foto'] : $gear->foto;
                            $gearHarga = is_array($gear) ? $gear['harga'] : $gear->harga;
                            $gearTipe = is_array($gear) ? ($gear['tipe'] ?? 'sewa') : $gear->tipe;
                            $gearStok = is_array($gear) ? ($gear['stok'] ?? 10) : $gear->stok_total;
                            $gearQty = $selectedGears[$gearId] ?? 0;

                            $itemRec = collect($gearRecommendations['all_items'] ?? [])->firstWhere('gear.id', $gearId);
                            $priority = $itemRec['priority'] ?? null;
                            $alasan = $itemRec['alasan'] ?? null;
                        @endphp
                        <x-card class="p-3" data-gear-row="{{ $gearId }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <img src="{{ $gearFoto }}" alt="{{ $gearNama }}" class="w-10 h-10 rounded object-cover flex-shrink-0 mt-0.5" loading="lazy" />
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <p class="font-inter font-medium text-xs text-text">{{ $gearNama }}</p>
                                            @if($priority === 'wajib')
                                                <span class="px-1.5 py-0.2 bg-red-100 text-red-700 text-[9px] font-bold rounded">Wajib</span>
                                            @elseif($priority === 'disarankan')
                                                <span class="px-1.5 py-0.2 bg-amber-100 text-amber-800 text-[9px] font-bold rounded">Disarankan</span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-muted mt-0.5" data-gear-harga="{{ $gearId }}" data-harga="{{ $gearHarga }}">
                                            Rp {{ number_format($gearHarga, 0, ',', '.') }} {{ $gearTipe === 'sewa' ? '/ hari' : '/ unit' }}
                                        </p>
                                        @if($alasan)
                                            <p class="text-[10px] text-muted mt-0.5 italic leading-tight">{{ $alasan }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 mt-1">
                                    <button type="button" data-gear-minus="{{ $gearId }}"
                                        class="w-7 h-7 rounded border border-line flex items-center justify-center text-xs hover:border-forest cursor-pointer select-none">−</button>
                                    <input type="text" readonly name="gears[{{ $gearId }}]" id="gear-qty-{{ $gearId }}"
                                        value="{{ $gearQty }}" data-max="{{ $gearStok }}"
                                        class="w-8 text-center text-xs font-inter font-bold bg-transparent outline-none">
                                    <button type="button" data-gear-plus="{{ $gearId }}"
                                        class="w-7 h-7 rounded border border-line flex items-center justify-center text-xs hover:border-forest cursor-pointer select-none">+</button>
                                </div>
                            </div>
                        </x-card>
                    @empty
                        <p class="text-xs text-muted italic">Tidak ada perlengkapan khusus yang dibutuhkan.</p>
                    @endforelse
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

{{-- ======================================================== --}}
{{-- MODAL 1: SMART SWAP (GANTI DESTINASI CERDAS)            --}}
{{-- ======================================================== --}}
<div id="modal-smart-swap" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        {{-- Header --}}
        <div class="p-6 border-b border-line flex items-start justify-between gap-4">
            <div>
                <span class="text-[11px] font-inter font-bold uppercase tracking-wider text-terracotta">SMART SWAP</span>
                <h3 class="font-playfair text-2xl font-bold text-text mt-0.5">Ganti Destinasi</h3>
                <p class="text-xs text-muted mt-1">Mengganti: <strong id="swap-current-nama" class="text-text">—</strong></p>
            </div>
            <button type="button" data-close-modal="modal-smart-swap" class="text-muted hover:text-text text-2xl p-1 cursor-pointer">&times;</button>
        </div>

        {{-- Reason Filter Chips --}}
        <div class="px-6 pt-4 pb-2 bg-sand/30 border-b border-line">
            <p class="text-xs font-semibold text-text mb-2">Kenapa Anda ingin mengganti destinasi ini?</p>
            <div class="flex flex-wrap gap-2" id="swap-reason-chips">
                <button type="button" data-reason="kurang_menarik" class="swap-chip active px-3 py-1 rounded-full text-xs font-medium border border-forest bg-forest text-white cursor-pointer">
                    ✨ Kurang Menarik
                </button>
                <button type="button" data-reason="terlalu_jauh" class="swap-chip px-3 py-1 rounded-full text-xs font-medium border border-line bg-white text-muted hover:border-forest hover:text-forest cursor-pointer">
                    🚗 Terlalu Jauh / Macet
                </button>
                <button type="button" data-reason="terlalu_mahal" class="swap-chip px-3 py-1 rounded-full text-xs font-medium border border-line bg-white text-muted hover:border-forest hover:text-forest cursor-pointer">
                    💰 Terlalu Mahal
                </button>
                <button type="button" data-reason="terlalu_berat" class="swap-chip px-3 py-1 rounded-full text-xs font-medium border border-line bg-white text-muted hover:border-forest hover:text-forest cursor-pointer">
                    ⛰️ Fisik Terlalu Berat
                </button>
                <button type="button" data-reason="sudah_pernah" class="swap-chip px-3 py-1 rounded-full text-xs font-medium border border-line bg-white text-muted hover:border-forest hover:text-forest cursor-pointer">
                    🚩 Sudah Pernah ke Sini
                </button>
                <button type="button" data-reason="lainnya" class="swap-chip px-3 py-1 rounded-full text-xs font-medium border border-line bg-white text-muted hover:border-forest hover:text-forest cursor-pointer">
                    💭 Alasan Lain
                </button>
            </div>
        </div>

        {{-- Candidates List Container --}}
        <div class="p-6 overflow-y-auto flex-1 space-y-3" id="swap-candidates-container">
            <div class="text-center py-12 text-muted text-sm">
                <span class="inline-block animate-spin mr-2">⚙️</span> Mencari kandidat pengganti terbaik...
            </div>
        </div>

        {{-- Footer --}}
        <div class="p-4 border-t border-line bg-sand/20 flex items-center justify-between">
            <button type="button" id="btn-swap-refresh" class="text-xs text-forest hover:underline font-semibold flex items-center gap-1 cursor-pointer">
                <span>🔄</span> Lihat Opsi Lain (Acak Variasi)
            </button>
            <button type="button" data-close-modal="modal-smart-swap" class="px-4 py-2 text-xs font-semibold rounded border border-line text-muted hover:text-text cursor-pointer">
                Tutup
            </button>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 2: TAMBAH DESTINASI (CHEAPEST INSERTION)           --}}
{{-- ======================================================== --}}
<div id="modal-add-dest" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        {{-- Header --}}
        <div class="p-6 border-b border-line flex items-start justify-between gap-4">
            <div>
                <span class="text-[11px] font-inter font-bold uppercase tracking-wider text-forest">KATALOG GIANYAR</span>
                <h3 class="font-playfair text-2xl font-bold text-text mt-0.5">Tambah Destinasi ke Hari <span id="add-target-day">1</span></h3>
                <p class="text-xs text-muted mt-1">Sistem akan menyisipkan di urutan waktu paling efisien (Cheapest Insertion).</p>
            </div>
            <button type="button" data-close-modal="modal-add-dest" class="text-muted hover:text-text text-2xl p-1 cursor-pointer">&times;</button>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 bg-sand/30 border-b border-line flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" id="catalog-search" placeholder="Cari nama, daya tarik, atau kecamatan..."
                    class="w-full h-10 pl-9 pr-3 rounded-lg border border-line bg-white text-xs text-text focus:outline-none focus:border-forest">
                <span class="absolute left-3 top-2.5 text-muted text-xs">🔍</span>
            </div>
            <select id="catalog-kategori" class="h-10 px-3 rounded-lg border border-line bg-white text-xs text-text focus:outline-none focus:border-forest cursor-pointer">
                <option value="">Semua Kategori</option>
                <option value="alam">Alam & Lanskap</option>
                <option value="budaya">Budaya & Pura</option>
                <option value="seni">Seni & Kerajinan</option>
                <option value="kuliner">Kuliner Tradisional</option>
                <option value="aktivitas">Aktivitas Petualangan</option>
            </select>
        </div>

        {{-- Destination Catalog List --}}
        <div class="p-6 overflow-y-auto flex-1 space-y-3" id="catalog-results-container">
            <div class="text-center py-12 text-muted text-sm">
                <span class="inline-block animate-spin mr-2">⚙️</span> Memuat katalog destinasi...
            </div>
        </div>

        {{-- Footer --}}
        <div class="p-4 border-t border-line bg-sand/20 flex justify-end">
            <button type="button" data-close-modal="modal-add-dest" class="px-4 py-2 text-xs font-semibold rounded border border-line text-muted hover:text-text cursor-pointer">
                Batal
            </button>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 3: KONFIRMASI HAPUS & EXCLUSION                    --}}
{{-- ======================================================== --}}
<div id="modal-delete" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden p-6">
        <h3 class="font-playfair text-xl font-bold text-text">Hapus Destinasi</h3>
        <p class="text-xs text-muted mt-2">
            Apakah Anda yakin ingin menghapus <strong id="delete-dest-nama" class="text-text">—</strong> dari jadwal perjalanan?
        </p>

        <div class="mt-4">
            <label class="block text-xs font-semibold text-text mb-1">Pilih alasan (opsional):</label>
            <select id="delete-reason" class="w-full h-10 px-3 rounded border border-line bg-white text-xs text-text focus:outline-none focus:border-forest cursor-pointer">
                <option value="kurang_menarik">Kurang Menarik</option>
                <option value="terlalu_jauh">Terlalu Jauh / Macet</option>
                <option value="terlalu_mahal">Terlalu Mahal</option>
                <option value="terlalu_berat">Fisik Terlalu Berat</option>
                <option value="sudah_pernah">Sudah Pernah ke Sini</option>
                <option value="lainnya">Alasan Lain</option>
            </select>
        </div>

        <div class="mt-3 flex items-start gap-2">
            <input type="checkbox" id="delete-add-exclusion" checked class="mt-0.5 rounded border-line text-forest focus:ring-forest">
            <label for="delete-add-exclusion" class="text-xs text-muted leading-snug">
                Sembunyikan juga dari rekomendasi berikutnya agar destinasi ini tidak muncul lagi di itinerary ini.
            </label>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-close-modal="modal-delete" class="px-4 py-2 text-xs font-semibold rounded border border-line text-muted hover:text-text cursor-pointer">
                Batal
            </button>
            <button type="button" id="btn-confirm-delete" class="px-4 py-2 text-xs font-semibold rounded bg-red-600 hover:bg-red-700 text-white cursor-pointer transition-colors">
                Hapus dari Jadwal
            </button>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 4: UBAH DURASI KUNJUNGAN                           --}}
{{-- ======================================================== --}}
<div id="modal-duration" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl overflow-hidden p-6">
        <h3 class="font-playfair text-xl font-bold text-text">Ubah Durasi Kunjungan</h3>
        <p class="text-xs text-muted mt-1">Sesuaikan berapa lama Anda ingin berada di destinasi ini:</p>

        <div class="grid grid-cols-3 gap-2 mt-4" id="duration-quick-options">
            <button type="button" data-dur="45" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">45 mnt</button>
            <button type="button" data-dur="60" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">1 Jam</button>
            <button type="button" data-dur="90" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">1.5 Jam</button>
            <button type="button" data-dur="120" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">2 Jam</button>
            <button type="button" data-dur="150" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">2.5 Jam</button>
            <button type="button" data-dur="180" class="py-2 text-xs font-semibold rounded border border-line hover:border-forest text-text cursor-pointer">3 Jam</button>
        </div>

        <div class="mt-4">
            <label class="block text-xs font-semibold text-text mb-1">Durasi Kustom (Menit):</label>
            <input type="number" id="input-custom-duration" min="30" max="360" step="15" value="90"
                class="w-full h-10 px-3 rounded border border-line text-xs text-text focus:outline-none focus:border-forest">
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-close-modal="modal-duration" class="px-4 py-2 text-xs font-semibold rounded border border-line text-muted hover:text-text cursor-pointer">
                Batal
            </button>
            <button type="button" id="btn-save-duration" class="px-4 py-2 text-xs font-semibold rounded bg-forest hover:bg-forest-dark text-white cursor-pointer transition-colors">
                Simpan Durasi
            </button>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 5: DAFTAR TERSEMBUNYI (EXCLUSIONS / BLACKLIST)     --}}
{{-- ======================================================== --}}
<div id="modal-exclusions" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-line flex items-start justify-between gap-4">
            <div>
                <span class="text-[11px] font-inter font-bold uppercase tracking-wider text-muted">PREFERENSI NEGATIF</span>
                <h3 class="font-playfair text-xl font-bold text-text mt-0.5">Destinasi yang Disembunyikan</h3>
                <p class="text-xs text-muted mt-1">Destinasi di bawah ini tidak akan muncul saat Anda melakukan acak rute atau swap.</p>
            </div>
            <button type="button" data-close-modal="modal-exclusions" class="text-muted hover:text-text text-2xl p-1 cursor-pointer">&times;</button>
        </div>

        <div class="p-6 overflow-y-auto flex-1 space-y-3" id="exclusions-list-container">
            <div class="text-center py-8 text-muted text-xs">Memuat daftar...</div>
        </div>

        <div class="p-4 border-t border-line bg-sand/20 flex justify-end">
            <button type="button" data-close-modal="modal-exclusions" class="px-4 py-2 text-xs font-semibold rounded border border-line text-muted hover:text-text cursor-pointer">
                Tutup
            </button>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- FLOATING TOAST NOTIFICATION (UNDO / STATUS FEEDBACK)     --}}
{{-- ======================================================== --}}
<div id="toast-action" class="fixed bottom-24 right-6 z-50 hidden transform transition-all duration-300">
    <div class="bg-forest-dark text-white px-5 py-3.5 rounded-xl shadow-2xl flex items-center gap-4 text-xs font-inter border border-white/10">
        <span id="toast-message" class="leading-relaxed">Perubahan berhasil disimpan.</span>
        <button type="button" id="toast-undo-btn" class="hidden underline font-bold text-amber-300 hover:text-amber-200 cursor-pointer whitespace-nowrap">
            Urungkan (Undo)
        </button>
        <button type="button" id="toast-close-btn" class="text-white/60 hover:text-white text-base cursor-pointer">&times;</button>
    </div>
</div>

{{-- ======================================================== --}}
{{-- VANILLA JAVASCRIPT ITINERARY EDITOR                      --}}
{{-- ======================================================== --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ITIN_ID = document.getElementById('itinerary-id')?.value;
    let currentVersion = parseInt(document.getElementById('itinerary-version')?.value || 1);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // State untuk modal aktif
    let activeSwapItemId = null;
    let activeSwapDestId = null;
    let activeSwapReason = 'kurang_menarik';
    let activeSwapSeed = 100;

    let activeAddDay = 1;
    let activeDeleteItem = null;
    let activeDurationItemId = null;

    // Cek flash toast dari sessionStorage pasca-reload
    try {
        const storedToast = sessionStorage.getItem('itinerary_flash_toast');
        if (storedToast) {
            const data = JSON.parse(storedToast);
            sessionStorage.removeItem('itinerary_flash_toast');
            showToast(data.message, data.can_undo);
        }
    } catch (e) {}

    // ==========================================
    // HTTP API Request Helper
    // ==========================================
    async function apiCall(endpoint, method = 'GET', body = null) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        };

        const options = { method, headers };
        if (body) {
            body.versi = currentVersion;
            options.body = JSON.stringify(body);
        }

        try {
            const res = await fetch(endpoint, options);
            const json = await res.json();

            if (res.status === 409) {
                alert('Konflik versi: Jadwal telah diperbarui di sesi lain. Halaman akan dimuat ulang untuk sinkronisasi.');
                window.location.reload();
                return null;
            }

            if (!res.ok) {
                throw new Error(json.message || 'Terjadi kesalahan pada server.');
            }

            return json;
        } catch (err) {
            alert(err.message);
            return null;
        }
    }

    // ==========================================
    // Toast Notification Handler
    // ==========================================
    function showToast(message, canUndo = false) {
        const toast = document.getElementById('toast-action');
        const msgEl = document.getElementById('toast-message');
        const undoBtn = document.getElementById('toast-undo-btn');
        if (!toast || !msgEl) return;

        msgEl.textContent = message;
        if (undoBtn) {
            undoBtn.classList.toggle('hidden', !canUndo);
        }

        toast.classList.remove('hidden');
        toast.classList.add('flex');

        if (window._toastTimeout) clearTimeout(window._toastTimeout);
        window._toastTimeout = setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        }, 7000);
    }

    document.getElementById('toast-close-btn')?.addEventListener('click', () => {
        document.getElementById('toast-action')?.classList.add('hidden');
    });

    document.getElementById('toast-undo-btn')?.addEventListener('click', async () => {
        const res = await apiCall(`/itineraries/${ITIN_ID}/undo`, 'POST', {});
        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: 'Perubahan berhasil dibatalkan (Undo)',
                can_undo: false
            }));
            window.location.reload();
        }
    });

    // ==========================================
    // Modal Open & Close Utility
    // ==========================================
    function openModal(id) {
        document.getElementById(id)?.classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.add('hidden');
    }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
    });

    // ==========================================
    // 1. PIN / LOCK TOGGLE
    // ==========================================
    document.querySelectorAll('[data-action="toggle-lock"]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const itemId = btn.getAttribute('data-item-id');
            const res = await apiCall(`/itineraries/${ITIN_ID}/items/${itemId}/lock`, 'PATCH', {});
            if (res && res.success) {
                sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                    message: res.message,
                    can_undo: res.can_undo
                }));
                window.location.reload();
            }
        });
    });

    // ==========================================
    // 2. REORDER (MOVE UP / MOVE DOWN)
    // ==========================================
    document.querySelectorAll('[data-action="move-up"]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const card = btn.closest('[data-timeline-item]');
            const prevCard = card.previousElementSibling;
            if (!prevCard || !prevCard.hasAttribute('data-timeline-item')) {
                return; // Sudah paling atas
            }
            await swapItemsOrder(card, prevCard);
        });
    });

    document.querySelectorAll('[data-action="move-down"]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const card = btn.closest('[data-timeline-item]');
            const nextCard = card.nextElementSibling;
            if (!nextCard || !nextCard.hasAttribute('data-timeline-item')) {
                return; // Sudah paling bawah
            }
            await swapItemsOrder(card, nextCard);
        });
    });

    async function swapItemsOrder(cardA, cardB) {
        const day = cardA.getAttribute('data-day');
        const container = cardA.parentElement;
        const allItems = Array.from(container.querySelectorAll('[data-timeline-item]'));

        const idxA = allItems.indexOf(cardA);
        const idxB = allItems.indexOf(cardB);
        if (idxA === -1 || idxB === -1) return;

        // Swap di array
        [allItems[idxA], allItems[idxB]] = [allItems[idxB], allItems[idxA]];

        const payloadOrder = allItems.map((el, i) => ({
            id: parseInt(el.getAttribute('data-timeline-item')),
            hari_ke: parseInt(day),
            urutan: i + 1
        }));

        const res = await apiCall(`/itineraries/${ITIN_ID}/items/reorder`, 'PATCH', {
            items_order: payloadOrder
        });

        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: 'Urutan rute berhasil diperbarui.',
                can_undo: res.can_undo
            }));
            window.location.reload();
        }
    }

    // ==========================================
    // 3. SMART SWAP MODAL & EXECUTION
    // ==========================================
    document.querySelectorAll('[data-action="open-swap-modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            activeSwapItemId = btn.getAttribute('data-item-id');
            activeSwapDestId = btn.getAttribute('data-dest-id');
            const destNama = btn.getAttribute('data-dest-nama');

            document.getElementById('swap-current-nama').textContent = destNama;
            activeSwapReason = 'kurang_menarik';
            activeSwapSeed = Math.floor(Math.random() * 900) + 100;

            // Reset chip active state
            document.querySelectorAll('#swap-reason-chips .swap-chip').forEach(c => {
                const isActive = c.getAttribute('data-reason') === activeSwapReason;
                c.classList.toggle('bg-forest', isActive);
                c.classList.toggle('text-white', isActive);
                c.classList.toggle('border-forest', isActive);
                c.classList.toggle('bg-white', !isActive);
                c.classList.toggle('text-muted', !isActive);
            });

            openModal('modal-smart-swap');
            loadSwapCandidates();
        });
    });

    // Reason chip click
    document.querySelectorAll('#swap-reason-chips .swap-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            activeSwapReason = chip.getAttribute('data-reason');
            document.querySelectorAll('#swap-reason-chips .swap-chip').forEach(c => {
                const isActive = c === chip;
                c.classList.toggle('bg-forest', isActive);
                c.classList.toggle('text-white', isActive);
                c.classList.toggle('border-forest', isActive);
                c.classList.toggle('bg-white', !isActive);
                c.classList.toggle('text-muted', !isActive);
            });
            loadSwapCandidates();
        });
    });

    // Refresh candidate seeds
    document.getElementById('btn-swap-refresh')?.addEventListener('click', () => {
        activeSwapSeed = Math.floor(Math.random() * 900) + 100;
        loadSwapCandidates();
    });

    async function loadSwapCandidates() {
        const container = document.getElementById('swap-candidates-container');
        if (!container) return;

        container.innerHTML = `<div class="text-center py-12 text-muted text-sm"><span class="inline-block animate-spin mr-2">⚙️</span> Mencari kandidat pengganti terbaik...</div>`;

        const res = await apiCall(`/itineraries/${ITIN_ID}/items/${activeSwapItemId}/replace-suggestions`, 'POST', {
            alasan: activeSwapReason,
            limit: 4,
            seed: activeSwapSeed
        });

        if (!res || !res.candidates || res.candidates.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-muted text-sm">Tidak ditemukan alternatif lain yang sesuai kriteria di sekitar lokasi ini.</div>`;
            return;
        }

        let html = '';
        res.candidates.forEach(cand => {
            const dest = cand.destinasi;
            const price = dest.harga_tiket > 0 ? 'Rp ' + Number(dest.harga_tiket).toLocaleString('id-ID') : 'Gratis';
            const dist = cand.jarak_dari_prev_km ? `±${cand.jarak_dari_prev_km} km` : 'Dekat';

            html += `
            <div class="p-4 rounded-xl border border-line hover:border-forest hover:bg-sand/20 transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5 flex-1">
                    <img src="${dest.foto || 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=400&q=80'}"
                         alt="${dest.nama}" class="w-16 h-16 rounded-lg object-cover flex-shrink-0 mt-0.5">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="font-playfair text-lg font-bold text-text">${dest.nama}</h4>
                            <span class="px-2 py-0.5 text-[10px] rounded bg-sand text-muted font-medium">${dest.kategori || 'Wisata'}</span>
                            <span class="text-xs text-muted">· ${price}</span>
                        </div>
                        <p class="text-xs text-muted mt-1 flex items-center gap-2">
                            <span>📍 ${dest.kecamatan || 'Gianyar'} (${dist})</span>
                            <span>⏰ ${dest.jam_buka || '08:00'} - ${dest.jam_tutup || '18:00'}</span>
                        </p>
                        ${cand.alasan_rekomendasi ? `<p class="text-[11px] text-forest font-medium mt-1 leading-snug">💡 ${cand.alasan_rekomendasi}</p>` : ''}
                    </div>
                </div>
                <button type="button" data-select-candidate="${dest.id}"
                    class="w-full sm:w-auto px-4 py-2 bg-forest hover:bg-forest-dark text-white rounded-lg text-xs font-semibold cursor-pointer transition-colors whitespace-nowrap shadow-sm">
                    Pilih Destinasi Ini
                </button>
            </div>
            `;
        });

        container.innerHTML = html;

        // Pasang event listener pemilihan kandidat
        container.querySelectorAll('[data-select-candidate]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const destId = btn.getAttribute('data-select-candidate');
                btn.disabled = true;
                btn.textContent = 'Memproses...';

                const replaceRes = await apiCall(`/itineraries/${ITIN_ID}/items/${activeSwapItemId}/replace`, 'PATCH', {
                    destinasi_id: destId,
                    alasan: activeSwapReason
                });

                if (replaceRes && replaceRes.success) {
                    sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                        message: replaceRes.message,
                        can_undo: replaceRes.can_undo
                    }));
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Pilih Destinasi Ini';
                }
            });
        });
    }

    // ==========================================
    // 4. TAMBAH DESTINASI (CHEAPEST INSERTION)
    // ==========================================
    document.querySelectorAll('[data-action="open-add-modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            activeAddDay = btn.getAttribute('data-day') || 1;
            document.getElementById('add-target-day').textContent = activeAddDay;
            openModal('modal-add-dest');
            loadCatalogDestinations();
        });
    });

    let searchDebounceTimer = null;
    document.getElementById('catalog-search')?.addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(loadCatalogDestinations, 300);
    });

    document.getElementById('catalog-kategori')?.addEventListener('change', loadCatalogDestinations);

    async function loadCatalogDestinations() {
        const container = document.getElementById('catalog-results-container');
        const q = document.getElementById('catalog-search')?.value || '';
        const kat = document.getElementById('catalog-kategori')?.value || '';

        container.innerHTML = `<div class="text-center py-12 text-muted text-sm"><span class="inline-block animate-spin mr-2">⚙️</span> Memuat katalog destinasi...</div>`;

        const res = await apiCall(`/itineraries/${ITIN_ID}/catalog?q=${encodeURIComponent(q)}&kategori=${encodeURIComponent(kat)}`, 'GET');

        if (!res || !res.destinations || res.destinations.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-muted text-sm">Tidak ada destinasi yang cocok dengan pencarian.</div>`;
            return;
        }

        let html = '';
        res.destinations.forEach(dest => {
            const price = dest.harga_tiket > 0 ? 'Rp ' + Number(dest.harga_tiket).toLocaleString('id-ID') : 'Gratis';
            html += `
            <div class="p-3.5 rounded-xl border border-line hover:border-forest hover:bg-sand/20 transition-all flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <img src="${dest.foto || 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=400&q=80'}"
                         alt="${dest.nama}" class="w-12 h-12 rounded object-cover flex-shrink-0">
                    <div class="min-w-0">
                        <h4 class="font-playfair text-base font-bold text-text truncate">${dest.nama}</h4>
                        <p class="text-xs text-muted truncate">📍 ${dest.kecamatan || 'Gianyar'} · ${price} · ${dest.kategori || 'Wisata'}</p>
                    </div>
                </div>
                <button type="button" data-add-catalog="${dest.id}"
                    class="px-3 py-1.5 bg-forest hover:bg-forest-dark text-white rounded text-xs font-semibold cursor-pointer transition-colors whitespace-nowrap shadow-sm">
                    + Tambah
                </button>
            </div>
            `;
        });

        container.innerHTML = html;

        container.querySelectorAll('[data-add-catalog]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const destId = btn.getAttribute('data-add-catalog');
                btn.disabled = true;
                btn.textContent = 'Menyisipkan...';

                const addRes = await apiCall(`/itineraries/${ITIN_ID}/items`, 'POST', {
                    destinasi_id: destId,
                    hari_ke: activeAddDay
                });

                if (addRes && addRes.success) {
                    sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                        message: addRes.message,
                        can_undo: addRes.can_undo
                    }));
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    btn.textContent = '+ Tambah';
                }
            });
        });
    }

    // ==========================================
    // 5. HAPUS DESTINASI & EXCLUSION
    // ==========================================
    document.querySelectorAll('[data-action="open-delete-modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            activeDeleteItem = btn.getAttribute('data-item-id');
            const destNama = btn.getAttribute('data-dest-nama');
            document.getElementById('delete-dest-nama').textContent = destNama;
            openModal('modal-delete');
        });
    });

    document.getElementById('btn-confirm-delete')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-confirm-delete');
        btn.disabled = true;
        btn.textContent = 'Menghapus...';

        const reason = document.getElementById('delete-reason')?.value || 'lainnya';
        const addExclusion = document.getElementById('delete-add-exclusion')?.checked;

        const res = await apiCall(`/itineraries/${ITIN_ID}/items/${activeDeleteItem}`, 'DELETE', {
            alasan: addExclusion ? reason : null
        });

        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: res.message,
                can_undo: res.can_undo
            }));
            window.location.reload();
        } else {
            btn.disabled = false;
            btn.textContent = 'Hapus dari Jadwal';
        }
    });

    // ==========================================
    // 6. UBAH DURASI KUNJUNGAN
    // ==========================================
    document.querySelectorAll('[data-action="edit-duration"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            activeDurationItemId = btn.getAttribute('data-item-id');
            const dur = btn.getAttribute('data-duration') || 90;
            const input = document.getElementById('input-custom-duration');
            if (input) input.value = dur;
            openModal('modal-duration');
        });
    });

    document.querySelectorAll('#duration-quick-options button').forEach(btn => {
        btn.addEventListener('click', () => {
            const dur = btn.getAttribute('data-dur');
            const input = document.getElementById('input-custom-duration');
            if (input) input.value = dur;
        });
    });

    document.getElementById('btn-save-duration')?.addEventListener('click', async () => {
        const dur = parseInt(document.getElementById('input-custom-duration')?.value || 90);
        const res = await apiCall(`/itineraries/${ITIN_ID}/items/${activeDurationItemId}/duration`, 'PATCH', {
            durasi_menit: dur
        });

        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: res.message,
                can_undo: res.can_undo
            }));
            window.location.reload();
        }
    });

    // ==========================================
    // 7. PARTIAL REGENERATE (HARI INI)
    // ==========================================
    document.querySelectorAll('[data-action="regen-day"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const day = btn.getAttribute('data-day');
            btn.disabled = true;
            btn.textContent = 'Mengacak...';

            const res = await apiCall(`/itineraries/${ITIN_ID}/regenerate-partial`, 'POST', {
                scope: 'hari',
                hari_ke: parseInt(day)
            });

            if (res && res.success) {
                sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                    message: res.message,
                    can_undo: res.can_undo
                }));
                window.location.reload();
            } else {
                btn.disabled = false;
                btn.textContent = '🎲 Acak Hari Ini';
            }
        });
    });

    // ==========================================
    // 8. GLOBAL UNDO, REDO, RESET
    // ==========================================
    document.getElementById('btn-undo')?.addEventListener('click', async () => {
        const res = await apiCall(`/itineraries/${ITIN_ID}/undo`, 'POST', {});
        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: 'Perubahan berhasil dibatalkan (Undo)',
                can_undo: false
            }));
            window.location.reload();
        }
    });

    document.getElementById('btn-redo')?.addEventListener('click', async () => {
        const res = await apiCall(`/itineraries/${ITIN_ID}/redo`, 'POST', {});
        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: 'Perubahan berhasil diulangi (Redo)',
                can_undo: true
            }));
            window.location.reload();
        }
    });

    document.getElementById('btn-reset')?.addEventListener('click', async () => {
        if (!confirm('Apakah Anda yakin ingin mengembalikan seluruh jadwal ke versi awal? Semua perubahan akan direset.')) {
            return;
        }
        const res = await apiCall(`/itineraries/${ITIN_ID}/reset`, 'POST', {});
        if (res && res.success) {
            sessionStorage.setItem('itinerary_flash_toast', JSON.stringify({
                message: 'Jadwal berhasil dikembalikan ke versi awal.',
                can_undo: true
            }));
            window.location.reload();
        }
    });

    // ==========================================
    // 9. EXCLUSIONS (BLACKLIST) MANAGER
    // ==========================================
    document.getElementById('btn-open-exclusions')?.addEventListener('click', async () => {
        openModal('modal-exclusions');
        const container = document.getElementById('exclusions-list-container');
        container.innerHTML = `<div class="text-center py-8 text-muted text-xs">Memuat daftar...</div>`;

        const res = await apiCall(`/itineraries/${ITIN_ID}/exclusions`, 'GET');
        if (!res || !res.exclusions || res.exclusions.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-muted text-xs">Belum ada destinasi yang disembunyikan pada itinerary ini.</div>`;
            return;
        }

        let html = '';
        res.exclusions.forEach(ex => {
            const dest = ex.destinasi || {};
            html += `
            <div class="p-3 rounded-lg border border-line flex items-center justify-between gap-3">
                <div>
                    <h5 class="font-playfair text-sm font-bold text-text">${dest.nama || 'Destinasi #' + ex.destinasi_id}</h5>
                    <p class="text-[11px] text-muted">Alasan: <span class="capitalize">${(ex.alasan || 'lainnya').replace('_', ' ')}</span></p>
                </div>
                <button type="button" data-unblock-dest="${ex.destinasi_id}"
                    class="px-2.5 py-1 text-xs rounded border border-line hover:border-forest text-forest font-semibold cursor-pointer transition-colors">
                    Kembalikan
                </button>
            </div>
            `;
        });

        container.innerHTML = html;

        container.querySelectorAll('[data-unblock-dest]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const destId = btn.getAttribute('data-unblock-dest');
                btn.disabled = true;
                btn.textContent = 'Memproses...';

                const unblockRes = await apiCall(`/itineraries/${ITIN_ID}/exclusions/${destId}`, 'DELETE');
                if (unblockRes && unblockRes.success) {
                    showToast(unblockRes.message);
                    btn.closest('.border-line').remove();
                    const countEl = document.getElementById('exclusions-count');
                    if (countEl) {
                        countEl.textContent = Math.max(0, parseInt(countEl.textContent || 1) - 1);
                    }
                }
            });
        });
    });
});
</script>
@endsection
