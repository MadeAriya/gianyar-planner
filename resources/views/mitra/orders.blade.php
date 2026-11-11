@extends('layouts.app')
@section('title', 'Pesanan Masuk — Mitra GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('mitra.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Daftar Pesanan Perlengkapan Masuk</h1>
            <p class="text-xs text-muted mt-1">Kelola dan perbarui status serah terima barang perlengkapan wisatawan.</p>
        </div>

        {{-- Filter Status --}}
        <div class="flex flex-wrap gap-2">
            @foreach([
                'all' => 'Semua',
                'menunggu_konfirmasi_mitra' => 'Menunggu',
                'dikonfirmasi_mitra' => 'Dikonfirmasi',
                'siap_diambil' => 'Siap Ambil',
                'dipakai' => 'Dipakai',
                'dikembalikan' => 'Dikembalikan/Selesai',
                'ditolak' => 'Ditolak'
            ] as $key => $label)
                <a href="{{ route('mitra.orders', ['status' => $key]) }}"
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
        @forelse($orders as $item)
            <x-card class="p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-line">
                    <div class="flex items-start gap-4">
                        <img src="{{ $item->gear->foto }}" alt="{{ $item->gear->nama_barang }}" class="w-14 h-14 object-cover rounded border border-line flex-shrink-0" />
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-playfair text-xl font-bold text-text">{{ $item->gear->nama_barang }}</h3>
                                <span class="px-2.5 py-0.5 rounded text-xs font-semibold
                                    @if(in_array($item->status, ['dikembalikan', 'selesai'])) bg-green-100 text-green-800
                                    @elseif(in_array($item->status, ['menunggu_konfirmasi_mitra'])) bg-yellow-100 text-yellow-800
                                    @elseif(in_array($item->status, ['dikonfirmasi_mitra', 'siap_diambil', 'dipakai'])) bg-blue-100 text-blue-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                Pesanan #{{ $item->pesanan_id }} · Pemesan: <strong>{{ $item->pesanan->user->nama ?? 'Wisatawan' }}</strong> ({{ $item->pesanan->user->no_hp ?? '-' }})
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-muted">Subtotal ({{ $item->qty }} unit x {{ $item->durasi_hari }} hari):</p>
                        <p class="font-playfair text-2xl font-bold text-terracotta">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                        @if($item->deposit_total > 0)
                            <p class="text-[11px] text-muted">+ Deposit: Rp {{ number_format($item->deposit_total, 0, ',', '.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 text-xs font-inter">
                    <div>
                        <p class="text-muted">Jadwal Sewa / Penggunaan:</p>
                        <p class="font-bold text-text text-sm mt-0.5">
                            {{ $item->tanggal_mulai ? $item->tanggal_mulai->format('d M Y') : '-' }} s/d {{ $item->tanggal_selesai ? $item->tanggal_selesai->format('d M Y') : '-' }}
                        </p>
                        <p class="text-muted mt-2">Metode Serah Terima: <span class="font-semibold text-text uppercase">{{ str_replace('_', ' ', $item->metode_serah) }}</span></p>

                        @if($item->alasan_penolakan)
                            <div class="p-2.5 bg-red-50 rounded text-red-800 text-[11px] mt-3">
                                <strong>Alasan Penolakan:</strong> {{ $item->alasan_penolakan }}
                            </div>
                        @endif
                    </div>

                    {{-- Action Form State Machine --}}
                    <div class="flex flex-col gap-2 border-t md:border-t-0 md:border-l border-line pt-3 md:pt-0 md:pl-4 justify-center">
                        @if($item->status === 'menunggu_konfirmasi_mitra')
                            <form method="POST" action="{{ route('mitra.orders.respond', $item->id) }}" class="flex flex-col gap-2">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <button type="submit" name="action" value="konfirmasi" class="flex-1 px-4 py-2 bg-forest text-white rounded font-medium hover:bg-forest-dark transition-colors text-center">
                                        ✓ Konfirmasi Ketersediaan
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <input type="text" name="alasan_penolakan" placeholder="Alasan penolakan jika stok kosong..." class="flex-1 p-2 border border-line rounded text-xs" />
                                    <button type="submit" name="action" value="tolak" class="px-3 py-2 bg-red-600 text-white rounded font-medium hover:bg-red-700 transition-colors">
                                        Tolak
                                    </button>
                                </div>
                            </form>
                        @elseif($item->status === 'dikonfirmasi_mitra')
                            <form method="POST" action="{{ route('mitra.orders.respond', $item->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="siap_diambil" />
                                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded font-medium hover:bg-blue-700 transition-colors">
                                    Tandai Barang Siap Diambil / Diantar
                                </button>
                            </form>
                        @elseif($item->status === 'siap_diambil')
                            <form method="POST" action="{{ route('mitra.orders.respond', $item->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="dipakai" />
                                <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700 transition-colors">
                                    Tandai Telah Diterima & Sedang Dipakai Wisatawan
                                </button>
                            </form>
                        @elseif($item->status === 'dipakai')
                            <form method="POST" action="{{ route('mitra.orders.respond', $item->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="dikembalikan" />
                                <button type="submit" class="w-full px-4 py-2 bg-forest text-white rounded font-medium hover:bg-forest-dark transition-colors">
                                    Tandai Sudah Dikembalikan / Selesai (Kembalikan Deposit)
                                </button>
                            </form>
                        @else
                            <p class="text-muted italic">Tidak ada tindakan lanjutan untuk status ini.</p>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-card class="p-8 text-center text-muted">
                Tidak ada pesanan barang perlengkapan dengan filter ini.
            </x-card>
        @endforelse

        <div>
            {{ $orders->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
