@extends('layouts.app')
@section('title', ($isEdit ? 'Edit Barang' : 'Tambah Barang') . ' — Mitra GianyarPlanner')

@section('content')
<div class="max-w-2xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between pb-4 border-b border-line">
        <div>
            <a href="{{ route('mitra.gears.index') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Katalog</a>
            <h1 class="font-playfair text-3xl text-text">{{ $isEdit ? 'Edit Barang Gear' : 'Tambah Barang Gear Baru' }}</h1>
        </div>
    </div>

    @if($errors->any())
        <x-alert type="error">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ $isEdit ? route('mitra.gears.update', $gear->id) : route('mitra.gears.store') }}" class="flex flex-col gap-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <x-card class="p-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Kategori Gear <span class="text-red-500">*</span></label>
                <select name="gear_category_id" required class="w-full h-11 px-3 border border-line rounded bg-white text-xs font-inter outline-none focus:border-forest">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('gear_category_id', $gear->gear_category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->nama }} ({{ $cat->sifat === 'per_orang' ? 'Per Orang' : 'Per Kelompok' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <x-input label="Nama Barang" name="nama_barang" placeholder="Contoh: Sepatu Trekking Anti-Slip" :value="old('nama_barang', $gear->nama_barang)" :required="true" />

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Deskripsi Barang</label>
                <textarea name="deskripsi" rows="3" placeholder="Jelaskan spesifikasi, bahan, dan fungsi barang..."
                    class="w-full text-xs font-inter p-3 border border-line rounded bg-white outline-none focus:border-forest">{{ old('deskripsi', $gear->deskripsi) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Tipe Transaksi</label>
                    <select name="tipe" class="w-full h-11 px-3 border border-line rounded bg-white text-xs font-inter outline-none">
                        <option value="sewa" {{ old('tipe', $gear->tipe) === 'sewa' ? 'selected' : '' }}>Sewa (Tarif per Hari)</option>
                        <option value="beli" {{ old('tipe', $gear->tipe) === 'beli' ? 'selected' : '' }}>Beli (Barang Habis Pakai / Milik Wisatawan)</option>
                    </select>
                </div>
                <x-input label="Harga (IDR)" name="harga" type="number" placeholder="50000" :value="old('harga', $gear->harga)" :required="true" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Uang Jaminan / Deposit (Opsional)" name="deposit" type="number" placeholder="0" :value="old('deposit', $gear->deposit ?? 0)" />
                <x-input label="Stok Total Tersedia" name="stok_total" type="number" placeholder="10" :value="old('stok_total', $gear->stok_total ?? 5)" :required="true" />
            </div>

            <x-input label="URL Foto Barang" name="foto" placeholder="https://images.unsplash.com/photo-..." :value="old('foto', $gear->foto)" />

            {{-- Tags Aktivitas --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Tag / Aktivitas yang Cocok</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 bg-sand/20 rounded border border-line max-h-36 overflow-y-auto">
                    @php
                        $tagsOptions = ['air_terjun', 'sawah', 'trekking', 'pura', 'pantai', 'sungai', 'cycling', 'ayunan', 'satwa', 'sunset', 'sunrise'];
                        $selectedTags = (array) old('tags_aktivitas', $gear->tags_aktivitas ?? ['trekking']);
                    @endphp
                    @foreach($tagsOptions as $t)
                        <label class="inline-flex items-center gap-1.5 text-xs text-text cursor-pointer">
                            <input type="checkbox" name="tags_aktivitas[]" value="{{ $t }}" class="rounded text-forest focus:ring-forest"
                                {{ in_array($t, $selectedTags) ? 'checked' : '' }}>
                            <span>{{ ucfirst(str_replace('_', ' ', $t)) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-line">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded text-forest focus:ring-forest"
                    {{ old('is_active', $gear->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="text-xs font-inter font-medium text-text cursor-pointer">
                    Tampilkan barang ini di katalog publik (Aktif)
                </label>
            </div>
        </x-card>

        <div class="flex items-center gap-3">
            <x-button type="submit" class="flex-1">{{ $isEdit ? 'Simpan Perubahan' : 'Tambahkan ke Katalog' }}</x-button>
            <a href="{{ route('mitra.gears.index') }}" class="px-5 py-3.5 border border-line rounded text-xs font-inter font-semibold text-muted hover:text-text">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
