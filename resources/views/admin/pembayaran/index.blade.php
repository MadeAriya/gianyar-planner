@extends('layouts.app')
@section('title', 'Verifikasi Pembayaran — Admin GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Verifikasi Pembayaran Pesanan</h1>
            <p class="text-xs text-muted mt-1">Periksa bukti transfer dan terbitkan kode tiket resmi wisatawan.</p>
        </div>

        {{-- Filter Status --}}
        <div class="flex gap-2">
            @foreach(['menunggu_verifikasi' => 'Menunggu Verifikasi', 'lunas' => 'Lunas', 'ditolak' => 'Ditolak', 'all' => 'Semua'] as $key => $label)
                <a href="{{ route('admin.pembayaran.index', ['status' => $key]) }}"
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

    <div class="flex flex-col gap-6">
        @forelse($pesanans as $p)
            <x-card class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-line">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-playfair text-xl font-bold text-text">Pesanan #{{ $p->id }}</h3>
                            @if($p->kode_tiket)
                                <span class="px-2 py-0.5 rounded bg-forest/10 text-forest font-mono text-xs font-bold">
                                    {{ $p->kode_tiket }}
                                </span>
                            @endif
                            <span class="px-2.5 py-0.5 rounded text-xs font-semibold {{ $p->status_pembayaran === 'lunas' ? 'bg-green-100 text-green-800' : ($p->status_pembayaran === 'menunggu_verifikasi' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst(str_replace('_', ' ', $p->status_pembayaran)) }}
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-1">
                            Wisatawan: <strong>{{ $p->user->nama ?? $p->user->name }}</strong> ({{ $p->user->email }}) · Tanggal Pesan: {{ $p->tgl_pesan->format('d M Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-muted">Total Pembayaran:</p>
                        <p class="font-playfair text-2xl font-bold text-forest">Rp {{ number_format($p->total_bayar, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 text-xs font-inter">
                    {{-- Rincian Pesanan --}}
                    <div>
                        <h4 class="font-bold text-text uppercase text-[11px] mb-2 tracking-wider">Item dalam Pesanan:</h4>
                        @if($p->pesananGuide)
                            <div class="p-2.5 bg-sand/20 rounded border border-line mb-2 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-text">Tour Guide: {{ $p->pesananGuide->guide->user->nama ?? 'Pemandu' }}</p>
                                    <p class="text-[11px] text-muted">{{ $p->pesananGuide->tanggal_mulai->format('d M') }} s/d {{ $p->pesananGuide->tanggal_selesai->format('d M Y') }}</p>
                                </div>
                                <span class="font-semibold text-terracotta">Rp {{ number_format($p->pesananGuide->tarif_snapshot, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @foreach($p->items as $item)
                            <div class="p-2.5 bg-sand/20 rounded border border-line mb-1.5 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-text">{{ $item->gear->nama_barang ?? 'Gear' }} (x{{ $item->qty }})</p>
                                    <p class="text-[11px] text-muted">Mitra: {{ $item->mitra->nama_usaha ?? 'Mitra' }} · {{ $item->jumlah_hari }} Hari</p>
                                </div>
                                <span class="font-semibold text-terracotta">Rp {{ number_format($item->harga_snapshot * $item->qty * $item->jumlah_hari, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Bukti Pembayaran & Aksi --}}
                    <div class="flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-text uppercase text-[11px] mb-2 tracking-wider">Bukti Transfer:</h4>
                            @if($p->bukti_upload)
                                <div class="p-3 bg-white border border-line rounded flex items-center justify-between">
                                    <span class="truncate max-w-xs text-muted">{{ basename($p->bukti_upload) }}</span>
                                    <a href="/storage/{{ $p->bukti_upload }}" target="_blank" class="px-3 py-1 bg-forest text-white rounded text-[11px] font-semibold hover:bg-forest-dark">
                                        Lihat Bukti Transfer ↗
                                    </a>
                                </div>
                            @else
                                <p class="text-muted italic">Belum ada bukti pembayaran yang diunggah.</p>
                            @endif
                        </div>

                        {{-- Form Verifikasi Admin --}}
                        @if($p->status_pembayaran === 'menunggu_verifikasi')
                            <form method="POST" action="{{ route('admin.pembayaran.verify', $p->id) }}" class="mt-4 pt-4 border-t border-line flex flex-col gap-3">
                                @csrf
                                <input type="text" name="catatan_admin" placeholder="Catatan admin (opsional untuk persetujuan, wajib jika tolak)"
                                    class="w-full text-xs p-2.5 border border-line rounded bg-white outline-none">
                                <div class="flex items-center gap-3">
                                    <button type="submit" name="action" value="setujui"
                                        class="px-4 py-2 bg-forest hover:bg-forest-dark text-white rounded text-xs font-semibold cursor-pointer">
                                        ✓ Konfirmasi Lunas & Terbitkan Tiket
                                    </button>
                                    <button type="submit" name="action" value="tolak"
                                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-semibold cursor-pointer">
                                        ✕ Tolak Bukti
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-card class="p-8 text-center text-muted">
                Tidak ada pesanan dengan status pembayaran "{{ $status }}".
            </x-card>
        @endforelse
    </div>

    <div>
        {{ $pesanans->appends(['status' => $status])->links() }}
    </div>
</div>
@endsection
