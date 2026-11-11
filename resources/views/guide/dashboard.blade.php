@extends('layouts.app')
@section('title', 'Dashboard Tour Guide — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-line">
        <div>
            <p class="font-inter font-semibold text-terracotta text-xs tracking-wider uppercase mb-1">AREA MITRA PEMANDU</p>
            <h1 class="font-playfair text-3xl sm:text-4xl text-text">Dashboard Tour Guide</h1>
            <p class="text-muted text-sm mt-1">Halo, <strong>{{ $guide->user->nama ?? $guide->user->name }}</strong>. Kelola jadwal dan permintaan perjalanan wisatawan.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('guide.kalender') }}" class="px-4 py-2 border border-forest text-forest rounded text-xs font-inter font-semibold hover:bg-forest hover:text-white transition-colors">
                📅 Kalender Ketersediaan
            </a>
            <a href="{{ route('guide.bookings') }}" class="px-4 py-2 bg-forest text-white rounded text-xs font-inter font-semibold hover:bg-forest-dark transition-colors">
                Permintaan Booking ({{ $metrics['pending_requests'] }})
            </a>
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Rating Rata-rata</p>
            <p class="font-playfair text-3xl font-bold text-terracotta mt-2">★ {{ number_format($metrics['rating_avg'], 1) }}</p>
            <p class="text-xs text-muted mt-1">{{ $metrics['jumlah_ulasan'] }} ulasan wisatawan</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Acceptance Rate</p>
            <p class="font-playfair text-3xl font-bold text-forest mt-2">{{ number_format($metrics['acceptance_rate'], 1) }}%</p>
            <p class="text-xs text-muted mt-1">Tingkat respons persetujuan</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Job Selesai</p>
            <p class="font-playfair text-3xl font-bold text-text mt-2">{{ $metrics['job_selesai'] }}</p>
            <p class="text-xs text-muted mt-1">Perjalanan sukses</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Jadwal Mendatang</p>
            <p class="font-playfair text-3xl font-bold text-forest mt-2">{{ $metrics['upcoming_jobs'] }}</p>
            <p class="text-xs text-muted mt-1">Job terkonfirmasi</p>
        </x-card>
    </div>

    {{-- Permintaan Booking Masuk --}}
    <x-card class="p-6">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-line">
            <h2 class="font-playfair text-xl text-text font-bold">Permintaan Booking Terbaru</h2>
            <a href="{{ route('guide.bookings') }}" class="text-xs text-forest hover:underline">Lihat Semua →</a>
        </div>
        @if($pendingBookings->isEmpty())
            <p class="text-muted text-xs py-4 text-center">Belum ada permintaan booking baru yang menunggu konfirmasi Anda.</p>
        @else
            <div class="flex flex-col divide-y divide-line/60">
                @foreach($pendingBookings as $b)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="font-inter font-bold text-sm text-text">Wisatawan: {{ $b->pesanan->user->nama ?? 'Wisatawan' }}</p>
                            <p class="text-xs text-muted">Tanggal: <strong>{{ $b->tanggal_mulai->format('d M') }} s/d {{ $b->tanggal_selesai->format('d M Y') }}</strong></p>
                            <p class="text-xs text-terracotta mt-0.5">Tarif: Rp {{ number_format($b->tarif_snapshot, 0, ',', '.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('guide.bookings.respond', $b->id) }}" class="inline">
                                @csrf
                                <button type="submit" name="action" value="terima" class="px-3.5 py-1.5 rounded bg-forest text-white text-xs font-semibold hover:bg-forest-dark">
                                    ✓ Terima Job
                                </button>
                            </form>
                            <a href="{{ route('guide.bookings') }}" class="px-3 py-1.5 rounded border border-line text-xs font-medium text-text hover:border-red-400">
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
