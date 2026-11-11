@extends('layouts.app')
@section('title', 'Ringkasan Pesanan — GianyarPlanner')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="font-playfair text-4xl text-text tracking-tight mb-2">Ringkasan Pesanan</h1>
    <p class="text-muted text-[15px] mb-8">Periksa detail pesanan sebelum melakukan pembayaran.</p>

    @if($errors->any())
        <x-alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    {{-- Itinerary summary --}}
    <x-card class="p-6 mb-6">
        <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Detail Itinerary</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-muted">Tanggal Berangkat</span>
                <p class="font-medium text-text">{{ $preferensi['tgl_berangkat'] }}</p>
            </div>
            <div>
                <span class="text-muted">Durasi</span>
                <p class="font-medium text-text">{{ $itinerary['durasi'] }} Hari {{ $itinerary['durasi'] - 1 }} Malam</p>
            </div>
            <div>
                <span class="text-muted">Jumlah Orang</span>
                <p class="font-medium text-text">{{ $itinerary['jumlah_orang'] }} Orang</p>
            </div>
            <div>
                <span class="text-muted">Kategori</span>
                <p class="font-medium text-text">{{ implode(', ', array_map(fn($k) => \App\Support\DummyData::kategoriLabel($k), $itinerary['kategori'])) }}</p>
            </div>
        </div>
    </x-card>

    {{-- Guide --}}
    @if($guide)
        <x-card class="p-6 mb-6">
            <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Tour Guide</h2>
            <div class="flex items-center gap-4">
                <img src="{{ $guide['foto'] }}" alt="{{ $guide['nama'] }}" class="w-12 h-12 rounded-full object-cover" />
                <div class="flex-1">
                    <p class="font-semibold text-text text-sm">{{ $guide['nama'] }}</p>
                    <p class="text-muted text-xs">★ {{ $guide['rating'] }} · {{ $guide['bahasa'] }}</p>
                </div>
                <div class="text-right">
                    <p class="text-muted text-xs">{{ $itinerary['durasi'] }} hari × Rp {{ number_format($guide['tarif'], 0, ',', '.') }}</p>
                    <p class="font-semibold text-text text-sm">Rp {{ number_format($guideCost, 0, ',', '.') }}</p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- Gear --}}
    @if(count($gearDetails) > 0)
        <x-card class="p-6 mb-6">
            <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Perlengkapan</h2>
            <div class="flex flex-col gap-3">
                @foreach($gearDetails as $g)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-text">{{ $g['nama_barang'] }} × {{ $g['jumlah'] }}</span>
                        <span class="font-semibold text-text">Rp {{ number_format($g['subtotal'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Total --}}
    <x-card class="p-6 mb-8">
        @if($isBundle)
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-muted">Subtotal</span>
                <span class="text-text">Rp {{ number_format(($guideCost + $gearCost), 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-terracotta">Diskon Bundle ({{ $bundleDiscount * 100 }}%)</span>
                <span class="text-terracotta">- Rp {{ number_format(($guideCost + $gearCost) - $totalBayar, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="flex items-center justify-between border-t border-line pt-3">
            <span class="font-inter font-bold text-text text-lg">Total Bayar</span>
            <span class="font-inter font-bold text-text text-lg">Rp {{ number_format($totalBayar, 0, ',', '.') }}</span>
        </div>
    </x-card>

    {{-- Upload bukti pembayaran --}}
    <x-card class="p-6 mb-8">
        <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Upload Bukti Pembayaran</h2>
        <p class="text-muted text-sm mb-4">Transfer ke rekening BCA 1234567890 a.n. GianyarPlanner, lalu unggah bukti di bawah ini.</p>

        <form method="POST" action="{{ route('pesanan.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="total_bayar" value="{{ $totalBayar }}" />

            <div class="mb-4">
                <label for="bukti_upload" class="block text-sm font-medium text-text mb-2">Bukti Transfer (JPG, PNG, atau PDF, maks 2MB)</label>
                <input type="file" name="bukti_upload" id="bukti_upload" accept=".jpg,.jpeg,.png,.pdf" required
                    class="block w-full text-sm text-text border border-line rounded p-3 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-forest file:text-white hover:file:bg-forest-dark" />
                @error('bukti_upload')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" class="w-full">Konfirmasi Pesanan</x-button>
        </form>
    </x-card>

    <a href="{{ route('itinerary.show') }}" class="inline-flex items-center gap-2 text-muted text-sm hover:text-text">
        ← Kembali ke Itinerary
    </a>
</div>
@endsection
