@extends('layouts.app')
@section('title', 'Detail Verifikasi Guide — GianyarPlanner')

@section('content')
<div class="max-w-3xl mx-auto flex flex-col gap-6">
    <div class="flex items-center justify-between pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.guides.index') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Daftar Guide</a>
            <h1 class="font-playfair text-3xl text-text">Detail Verifikasi Tour Guide</h1>
        </div>
        <div>
            <span class="px-3 py-1 rounded text-xs font-semibold uppercase {{ $guide->status_verifikasi === 'approved' ? 'bg-green-100 text-green-800' : ($guide->status_verifikasi === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                {{ $guide->status_verifikasi }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    {{-- Data Diri & Profil --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-4">Informasi Pribadi & Kontak</h2>
        <div class="grid grid-cols-2 gap-4 text-xs font-inter">
            <div>
                <p class="text-muted">Nama Lengkap:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $guide->user->nama ?? $guide->user->name }}</p>
            </div>
            <div>
                <p class="text-muted">Email:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $guide->user->email }}</p>
            </div>
            <div>
                <p class="text-muted">Nomor WhatsApp / HP:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $guide->user->no_hp }}</p>
            </div>
            <div>
                <p class="text-muted">Status Akun Pengguna:</p>
                <p class="font-semibold text-text text-sm mt-0.5 uppercase">{{ $guide->user->status }}</p>
            </div>
        </div>
    </x-card>

    {{-- Kualifikasi & Kapasitas --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-4">Kompetensi & Tarif Layanan</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs font-inter mb-4">
            <div>
                <p class="text-muted">Tarif per Hari:</p>
                <p class="font-bold text-terracotta text-sm mt-0.5">Rp {{ number_format($guide->tarif_per_hari, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-muted">Kapasitas Maks Peserta:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $guide->max_peserta }} Orang</p>
            </div>
            <div>
                <p class="text-muted">Pengalaman:</p>
                <p class="font-semibold text-text text-sm mt-0.5">{{ $guide->pengalaman_tahun }} Tahun</p>
            </div>
            <div>
                <p class="text-muted">Bahasa yang Dikuasai:</p>
                <p class="font-semibold text-text mt-0.5">{{ implode(', ', (array) $guide->bahasa) }}</p>
            </div>
            <div>
                <p class="text-muted">Area Layanan:</p>
                <p class="font-semibold text-text mt-0.5">{{ $guide->area_layanan }}</p>
            </div>
            <div>
                <p class="text-muted">Nomor Lisensi:</p>
                <p class="font-semibold text-text mt-0.5">{{ $guide->nomor_lisensi ?? '—' }}</p>
            </div>
        </div>

        <div class="border-t border-line pt-3">
            <p class="text-xs text-muted mb-1">Spesialisasi Subkategori Wisata:</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach((array) $guide->spesialisasi as $sp)
                    <span class="px-2 py-0.5 bg-sand rounded text-xs text-text font-medium">
                        {{ \App\Support\DummyData::kategoriLabel($sp) }}
                    </span>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- Dokumen Privat Terproteksi --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-2">Dokumen Identitas (Storage Privat)</h2>
        <p class="text-xs text-muted mb-4">Dokumen disimpan secara privat dan hanya dapat dibuka oleh Super Admin dan pemilik akun.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 border border-line rounded bg-sand/20">
                <p class="font-inter font-semibold text-xs text-text mb-1">Foto KTP</p>
                @if($guide->foto_ktp)
                    <p class="text-[11px] text-muted mb-3 truncate">{{ basename($guide->foto_ktp) }}</p>
                    <a href="{{ route('dokumen.show', ['type' => 'guide', 'id' => $guide->id, 'field' => 'foto_ktp']) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-forest text-white text-xs rounded hover:bg-forest-dark">
                        Buka Dokumen KTP ↗
                    </a>
                @else
                    <p class="text-xs text-red-500 italic">Belum diunggah</p>
                @endif
            </div>

            <div class="p-4 border border-line rounded bg-sand/20">
                <p class="font-inter font-semibold text-xs text-text mb-1">Sertifikat / Kartu Lisensi Pemandu</p>
                @if($guide->sertifikat_lisensi)
                    <p class="text-[11px] text-muted mb-3 truncate">{{ basename($guide->sertifikat_lisensi) }}</p>
                    <a href="{{ route('dokumen.show', ['type' => 'guide', 'id' => $guide->id, 'field' => 'sertifikat_lisensi']) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-forest text-white text-xs rounded hover:bg-forest-dark">
                        Buka Sertifikat ↗
                    </a>
                @else
                    <p class="text-xs text-muted italic">Tidak dilampirkan</p>
                @endif
            </div>
        </div>
    </x-card>

    {{-- Form Keputusan Verifikasi --}}
    <x-card class="p-6 border-2 border-forest/30">
        <h2 class="font-playfair text-xl font-bold text-text mb-3">Keputusan Super Admin</h2>

        @if($guide->catatan_verifikasi)
            <div class="p-3 mb-4 rounded bg-red-50 border border-red-200 text-xs text-red-800">
                <strong>Catatan Terakhir:</strong> {{ $guide->catatan_verifikasi }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.guides.verify', $guide->id) }}" class="flex flex-col gap-4">
            @csrf

            <div>
                <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">
                    Catatan Verifikasi (Wajib diisi jika menolak)
                </label>
                <textarea name="catatan_verifikasi" rows="2" placeholder="Tuliskan catatan atau alasan penolakan jika berkas tidak sesuai..."
                    class="w-full text-xs font-inter p-3 border border-line rounded bg-white outline-none focus:border-forest"></textarea>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                @if($guide->status_verifikasi !== 'approved')
                    <button type="submit" name="action" value="approve"
                        class="px-5 py-2.5 bg-forest hover:bg-forest-dark text-white rounded text-xs font-inter font-semibold cursor-pointer">
                        ✓ Setujui Tour Guide
                    </button>
                @endif

                @if($guide->status_verifikasi !== 'rejected')
                    <button type="submit" name="action" value="reject"
                        class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-inter font-semibold cursor-pointer">
                        ✕ Tolak Pengajuan
                    </button>
                @endif

                @if($guide->status_verifikasi === 'approved')
                    <button type="submit" name="action" value="suspend"
                        class="px-5 py-2.5 border border-red-300 text-red-700 hover:bg-red-50 rounded text-xs font-inter font-semibold cursor-pointer">
                        Tangguhkan (Suspend)
                    </button>
                @endif

                @if($guide->status_verifikasi === 'suspended')
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
