@extends('layouts.app')
@section('title', 'Permintaan Booking — Tour Guide GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('guide.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Daftar Permintaan Booking Masuk</h1>
            <p class="text-xs text-muted mt-1">Terima atau tolak pesanan jasa pemandu wisata dari wisatawan.</p>
        </div>

        {{-- Filter Status --}}
        <div class="flex gap-2">
            @foreach(['all' => 'Semua', 'menunggu_konfirmasi_guide' => 'Menunggu', 'dikonfirmasi_guide' => 'Diterima', 'terkonfirmasi' => 'Lunas', 'ditolak' => 'Ditolak'] as $key => $label)
                <a href="{{ route('guide.bookings', ['status' => $key]) }}"
                    class="px-3 py-1.5 rounded text-xs font-inter font-medium {{ $status === $key ? 'bg-forest text-white' : 'border border-line text-muted hover:border-text' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    <div class="flex flex-col gap-4">
        @forelse($bookings as $b)
            <x-card class="p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-line">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-playfair text-xl font-bold text-text">Booking #{{ $b->id }}</h3>
                            <span class="px-2.5 py-0.5 rounded text-xs font-semibold {{ $b->status === 'terkonfirmasi' ? 'bg-green-100 text-green-800' : ($b->status === 'menunggu_konfirmasi_guide' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst(str_replace('_', ' ', $b->status)) }}
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-1">
                            Wisatawan: <strong>{{ $b->pesanan->user->nama ?? 'Wisatawan' }}</strong> ({{ $b->pesanan->user->email }}) · Kontak: {{ $b->pesanan->user->no_hp }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-muted">Tarif Snapshot:</p>
                        <p class="font-playfair text-2xl font-bold text-terracotta">Rp {{ number_format($b->tarif_snapshot, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 text-xs font-inter">
                    <div>
                        <p class="text-muted">Periode Perjalanan:</p>
                        <p class="font-bold text-text text-sm mt-0.5">
                            {{ $b->tanggal_mulai->format('d M Y') }} s/d {{ $b->tanggal_selesai->format('d M Y') }}
                        </p>
                        @if($b->alasan_penolakan)
                            <div class="p-2.5 bg-red-50 rounded text-red-800 text-[11px] mt-3">
                                <strong>Alasan Penolakan:</strong> {{ $b->alasan_penolakan }}
                            </div>
                        @endif
                    </div>

                    {{-- Form Konfirmasi / Penolakan --}}
                    @if($b->status === 'menunggu_konfirmasi_guide')
                        <div class="flex flex-col gap-2 border-t md:border-t-0 md:border-l border-line pt-3 md:pt-0 md:pl-4">
                            <form method="POST" action="{{ route('guide.bookings.respond', $b->id) }}" class="flex flex-col gap-2">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <button type="submit" name="action" value="terima"
                                        class="flex-1 py-2 bg-forest hover:bg-forest-dark text-white rounded text-xs font-semibold cursor-pointer">
                                        ✓ Terima Pesanan
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="text" name="alasan_penolakan" placeholder="Alasan penolakan jika tidak bisa..."
                                        class="flex-1 text-xs p-2 border border-line rounded outline-none">
                                    <button type="submit" name="action" value="tolak"
                                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-semibold cursor-pointer">
                                        ✕ Tolak
                                    </button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="flex items-center justify-end text-xs text-muted italic">
                            Status pesanan telah diproses.
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <x-card class="p-8 text-center text-muted">
                Tidak ada pesanan booking tour guide sesuai filter "{{ $status }}".
            </x-card>
        @endforelse
    </div>

    <div>
        {{ $bookings->appends(['status' => $status])->links() }}
    </div>
</div>
@endsection
