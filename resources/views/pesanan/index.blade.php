@extends('layouts.app')
@section('title', 'Pesanan Saya — GianyarPlanner')

@section('content')
<h1 class="font-playfair text-4xl text-text tracking-tight mb-2">Pesanan Saya</h1>
<p class="text-muted text-[15px] mb-8">Riwayat pesanan dan status pembayaran Anda.</p>

@if($pesanan->isEmpty())
    <x-card class="p-8 text-center">
        <p class="text-muted text-[15px]">Belum ada pesanan.</p>
        <a href="{{ route('preferensi.create') }}" class="inline-block mt-4 text-forest font-medium text-sm hover:underline">Buat Itinerary Pertama →</a>
    </x-card>
@else
    <div class="flex flex-col gap-4">
        @foreach($pesanan as $p)
            @php
                $statusColors = [
                    'menunggu_pembayaran' => 'bg-yellow-100 text-yellow-800',
                    'menunggu_validasi' => 'bg-blue-100 text-blue-800',
                    'lunas' => 'bg-green-100 text-green-800',
                    'ditolak' => 'bg-red-100 text-red-800',
                ];
                $statusLabel = [
                    'menunggu_pembayaran' => 'Menunggu Pembayaran',
                    'menunggu_validasi' => 'Menunggu Validasi',
                    'lunas' => 'Lunas',
                    'ditolak' => 'Ditolak',
                ];
                $color = $statusColors[$p['status_pesanan']] ?? 'bg-gray-100 text-gray-800';
                $label = $statusLabel[$p['status_pesanan']] ?? $p['status_pesanan'];
            @endphp
            <x-card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-inter font-semibold text-text text-sm">Pesanan #{{ $p['id'] }}</p>
                        <p class="text-muted text-xs mt-1">{{ $p['tgl_pesan'] }} · {{ ucfirst($p['jenis_paket']) }}</p>
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium {{ $color }}">{{ $label }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <p class="font-inter font-bold text-text">Rp {{ number_format($p['total_bayar'], 0, ',', '.') }}</p>
                    @if($p['status_pesanan'] === 'lunas' && !empty($p['kode_tiket']))
                        <div class="text-right">
                            <p class="text-xs text-muted">Kode Tiket</p>
                            <p class="font-mono font-bold text-forest text-lg">{{ $p['kode_tiket'] }}</p>
                        </div>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>
@endif
@endsection
