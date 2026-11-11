@extends('layouts.app')
@section('title', 'Dashboard Mitra Gear — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-line">
        <div>
            <p class="font-inter font-semibold text-terracotta text-xs tracking-wider uppercase mb-1">AREA MITRA PERLENGKAPAN</p>
            <h1 class="font-playfair text-3xl sm:text-4xl text-text">Dashboard Mitra Gear</h1>
            <p class="text-muted text-sm mt-1">Usaha: <strong>{{ $mitra->nama_usaha }}</strong> · Pemilik: {{ $mitra->user->nama ?? $mitra->user->name }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('mitra.gears.create') }}" class="px-4 py-2 bg-forest text-white rounded text-xs font-inter font-semibold hover:bg-forest-dark transition-colors">
                + Tambah Barang Gear
            </a>
            <a href="{{ route('mitra.orders') }}" class="px-4 py-2 border border-line text-text rounded text-xs font-inter font-semibold hover:border-forest transition-colors">
                Pesanan Masuk ({{ $metrics['pending_orders'] }})
            </a>
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Total Katalog Gear</p>
            <p class="font-playfair text-3xl font-bold text-text mt-2">{{ $metrics['total_gears'] }}</p>
            <p class="text-xs text-muted mt-1">{{ $metrics['active_gears'] }} barang aktif</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Pesanan Menunggu</p>
            <p class="font-playfair text-3xl font-bold text-terracotta mt-2">{{ $metrics['pending_orders'] }}</p>
            <p class="text-xs text-muted mt-1">Perlu konfirmasi</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Pesanan Selesai</p>
            <p class="font-playfair text-3xl font-bold text-forest mt-2">{{ $metrics['completed_orders'] }}</p>
            <p class="text-xs text-muted mt-1">Sewa & penjualan selesai</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Rating Toko</p>
            <p class="font-playfair text-3xl font-bold text-terracotta mt-2">★ {{ number_format($mitra->rating_avg, 1) }}</p>
            <p class="text-xs text-muted mt-1">Berdasarkan ulasan</p>
        </x-card>
    </div>

    {{-- Pesanan Gear Masuk Terbaru --}}
    <x-card class="p-6">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-line">
            <h2 class="font-playfair text-xl text-text font-bold">Permintaan Sewa / Pembelian Terbaru</h2>
            <a href="{{ route('mitra.orders') }}" class="text-xs text-forest hover:underline">Lihat Semua Pesanan →</a>
        </div>
        @if($pendingOrders->isEmpty())
            <p class="text-muted text-xs py-4 text-center">Belum ada pesanan gear baru yang menunggu konfirmasi.</p>
        @else
            <div class="flex flex-col divide-y divide-line/60">
                @foreach($pendingOrders as $o)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="font-inter font-bold text-sm text-text">{{ $o->gear->nama_barang }} (x{{ $o->qty }})</p>
                            <p class="text-xs text-muted">Wisatawan: {{ $o->pesanan->user->nama ?? 'Wisatawan' }} · Metode: {{ ucfirst(str_replace('_', ' ', $o->metode_serah)) }}</p>
                            <p class="text-xs text-terracotta mt-0.5">Total: Rp {{ number_format($o->harga_snapshot * $o->qty * $o->jumlah_hari, 0, ',', '.') }} ({{ $o->jumlah_hari }} hari)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('mitra.orders.respond', $o->id) }}" class="inline">
                                @csrf
                                <button type="submit" name="action" value="konfirmasi" class="px-3.5 py-1.5 rounded bg-forest text-white text-xs font-semibold hover:bg-forest-dark">
                                    ✓ Konfirmasi Stok
                                </button>
                            </form>
                            <a href="{{ route('mitra.orders') }}" class="px-3 py-1.5 rounded border border-line text-xs font-medium text-text hover:border-red-400">
                                Rincian / Tolak
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
@endsection
