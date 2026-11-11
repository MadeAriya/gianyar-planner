@extends('layouts.app')
@section('title', 'Detail Verifikasi Mitra — GianyarPlanner')

@section('content')
<div class="max-w-3xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.mitras.index') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Daftar Mitra</a>
            <h1 class="font-playfair text-3xl text-text">Detail Verifikasi Mitra Gear</h1>
        </div>
        <div>
            <span class="px-3 py-1 rounded text-xs font-semibold uppercase {{ $mitra->status_verifikasi === 'approved' ? 'bg-green-100 text-green-800' : ($mitra->status_verifikasi === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                {{ $mitra->status_verifikasi }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    {{-- Data Usaha & Pemilik --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-4">Informasi Usaha & Pemilik</h2>
        <div class="grid grid-cols-2 gap-4 text-xs font-inter">
            <div>
                <p class="text-muted">Nama Usaha:</p>
                <p class="font-bold text-text text-sm mt-0.5">{{ $mitra->nama_usaha }}</p>
            </div>
            <div>
                <p class="text-muted">Nama Pemilik Akun:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $mitra->user->nama ?? $mitra->user->name }}</p>
            </div>
            <div>
                <p class="text-muted">Email Akun:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $mitra->user->email }}</p>
            </div>
            <div>
                <p class="text-muted">Nomor WhatsApp / HP:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $mitra->user->no_hp }}</p>
            </div>
            <div>
                <p class="text-muted">Kecamatan:</p>
                <p class="font-semibold text-text mt-0.5">{{ $mitra->kecamatan }}</p>
            </div>
            <div>
                <p class="text-muted">Metode Serah Terima:</p>
                <p class="font-semibold text-text mt-0.5">{{ ucfirst(str_replace('_', ' ', $mitra->metode_serah)) }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-muted">Alamat Usaha Lengkap:</p>
                <p class="font-medium text-text mt-0.5 leading-relaxed">{{ $mitra->alamat }}</p>
            </div>
        </div>
    </x-card>

    {{-- Dokumen Privat Terproteksi --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-2">Dokumen Legalitas Usaha (Storage Privat)</h2>
        <p class="text-xs text-muted mb-4">Dokumen disimpan secara privat dan hanya dapat dibuka oleh Super Admin dan pemilik akun.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 border border-line rounded bg-sand/20">
                <p class="font-inter font-semibold text-xs text-text mb-1">Foto KTP Pemilik</p>
                @if($mitra->foto_ktp)
                    <p class="text-[11px] text-muted mb-3 truncate">{{ basename($mitra->foto_ktp) }}</p>
                    <a href="{{ route('dokumen.show', ['type' => 'mitra', 'id' => $mitra->id, 'field' => 'foto_ktp']) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-forest text-white text-xs rounded hover:bg-forest-dark">
                        Buka Dokumen KTP ↗
                    </a>
                @else
                    <p class="text-xs text-red-500 italic">Belum diunggah</p>
                @endif
            </div>

            <div class="p-4 border border-line rounded bg-sand/20">
                <p class="font-inter font-semibold text-xs text-text mb-1">Bukti Usaha / Izin / Foto Tempat</p>
                @if($mitra->bukti_usaha)
                    <p class="text-[11px] text-muted mb-3 truncate">{{ basename($mitra->bukti_usaha) }}</p>
                    <a href="{{ route('dokumen.show', ['type' => 'mitra', 'id' => $mitra->id, 'field' => 'bukti_usaha']) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-forest text-white text-xs rounded hover:bg-forest-dark">
                        Buka Bukti Usaha ↗
                    </a>
                @else
                    <p class="text-xs text-red-500 italic">Belum diunggah</p>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Daftar Gear yang Dimiliki --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-2">Katalog Gear Mitra ({{ $mitra->gears->count() }} Barang)</h2>
        @if($mitra->gears->isEmpty())
            <p class="text-muted text-xs italic">Mitra belum memiliki barang gear yang didaftarkan.</p>
        @else
            <div class="divide-y divide-line/60 text-xs">
                @foreach($mitra->gears as $gear)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-text">{{ $gear->nama_barang }}</span>
                            <span class="text-muted text-[11px]"> · Stok: {{ $gear->stok_total }} · {{ ucfirst($gear->tipe) }}</span>
                        </div>
                        <span class="font-bold text-terracotta">Rp {{ number_format($gear->harga, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Form Keputusan Verifikasi --}}
    <x-card class="p-6 border-2 border-forest/30">
        <h2 class="font-playfair text-xl font-bold text-text mb-3">Keputusan Super Admin</h2>

        @if($mitra->catatan_verifikasi)
            <div class="p-3 mb-4 rounded bg-red-50 border border-red-200 text-xs text-red-800">
                <strong>Catatan Terakhir:</strong> {{ $mitra->catatan_verifikasi }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.mitras.verify', $mitra->id) }}" class="flex flex-col gap-4">
            @csrf

            <div>
                <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">
                    Catatan Verifikasi (Wajib diisi jika menolak)
                </label>
                <textarea name="catatan_verifikasi" rows="2" placeholder="Tuliskan catatan atau alasan penolakan jika berkas usaha tidak sesuai..."
                    class="w-full text-xs font-inter p-3 border border-line rounded bg-white outline-none focus:border-forest"></textarea>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                @if($mitra->status_verifikasi !== 'approved')
                    <button type="submit" name="action" value="approve"
                        class="px-5 py-2.5 bg-forest hover:bg-forest-dark text-white rounded text-xs font-inter font-semibold cursor-pointer">
                        ✓ Setujui Mitra Gear
                    </button>
                @endif

                @if($mitra->status_verifikasi !== 'rejected')
                    <button type="submit" name="action" value="reject"
                        class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-inter font-semibold cursor-pointer">
                        ✕ Tolak Pengajuan
                    </button>
                @endif

                @if($mitra->status_verifikasi === 'approved')
                    <button type="submit" name="action" value="suspend"
                        class="px-5 py-2.5 border border-red-300 text-red-700 hover:bg-red-50 rounded text-xs font-inter font-semibold cursor-pointer">
                        Tangguhkan (Suspend)
                    </button>
                @endif

                @if($mitra->status_verifikasi === 'suspended')
                    <button type="submit" name="action" value="reactivate"
                        class="px-5 py-2.5 bg-forest hover:bg-forest-dark text-white rounded text-xs font-inter font-semibold cursor-pointer">
                        Aktifkan Kembali
                    </button>
                @endif
            </div>
        </form>
    </x-card>
</div>
@endsection
