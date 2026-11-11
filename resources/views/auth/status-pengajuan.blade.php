@extends('layouts.guest')
@section('title', 'Status Pengajuan — GianyarPlanner')

@section('content')
<div class="max-w-xl mx-auto flex flex-col gap-6">
    <div class="text-center">
        <p class="font-inter font-semibold text-terracotta text-xs tracking-wider uppercase mb-2">VERIFIKASI AKUN MITRA</p>
        <h1 class="font-playfair text-3xl sm:text-4xl text-text">Status Pengajuan Akun</h1>
        <p class="text-muted text-sm mt-2">Halo, <strong>{{ $user->nama }}</strong> ({{ ucfirst(str_replace('_', ' ', $user->role)) }})</p>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    @if($user->status === 'pending')
        <div class="bg-[#FFF9EE] border border-[#F3E2BD] rounded-lg p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-[#FCE8B2] text-[#B06000] flex items-center justify-center mx-auto mb-4 text-xl font-bold">
                ⏳
            </div>
            <h2 class="font-playfair text-xl text-text font-bold mb-2">Menunggu Persetujuan Admin</h2>
            <p class="text-muted text-sm leading-relaxed mb-4">
                Pendaftaran Anda sebagai <strong>{{ $user->role === 'tour_guide' ? 'Tour Guide' : 'Mitra Penyedia Gear' }}</strong> telah kami terima. Tim Super Admin Gianyar Planner sedang meninjau dokumen identitas dan berkas kelayakan Anda.
            </p>
            <div class="bg-white rounded border border-[#F3E2BD] p-4 text-left text-xs text-muted flex flex-col gap-1.5 mb-2">
                <div><strong>Email Akun:</strong> {{ $user->email }}</div>
                <div><strong>Nomor HP:</strong> {{ $user->no_hp }}</div>
                @if($user->isTourGuide() && $profil)
                    <div><strong>Tarif per Hari:</strong> Rp {{ number_format($profil->tarif_per_hari, 0, ',', '.') }}</div>
                    <div><strong>Area Layanan:</strong> {{ $profil->area_layanan }}</div>
                @elseif($user->isMitra() && $profil)
                    <div><strong>Nama Usaha:</strong> {{ $profil->nama_usaha }}</div>
                    <div><strong>Alamat:</strong> {{ $profil->alamat }}, {{ $profil->kecamatan }}</div>
                @endif
            </div>
            <p class="text-xs text-muted/80 mt-3">Proses verifikasi biasanya memakan waktu 1x24 jam kerja. Anda dapat me-refresh halaman ini untuk mengecek status terbaru.</p>
        </div>

    @elseif($user->status === 'rejected')
        <div class="bg-[#FDF2F2] border border-[#F8B4B4] rounded-lg p-6">
            <div class="w-12 h-12 rounded-full bg-[#FDE8E8] text-[#E02424] flex items-center justify-center mx-auto mb-4 text-xl font-bold">
                ✕
            </div>
            <h2 class="font-playfair text-xl text-text font-bold text-center mb-2">Pengajuan Akun Belum Disetujui</h2>
            <p class="text-muted text-sm text-center leading-relaxed mb-4">
                Mohon maaf, pengajuan akun Anda belum dapat disetujui karena berkas atau informasi yang belum memenuhi ketentuan.
            </p>

            @if(!empty($profil?->catatan_verifikasi))
                <div class="bg-white border border-[#F8B4B4] rounded p-4 mb-6">
                    <p class="font-inter font-semibold text-xs text-red-700 uppercase tracking-wider mb-1">Catatan dari Super Admin:</p>
                    <p class="text-text text-sm italic leading-relaxed">"{{ $profil->catatan_verifikasi }}"</p>
                </div>
            @endif

            <h3 class="font-playfair text-lg font-bold text-text mb-3">Ajukan Ulang Dokumen Perbaikan</h3>
            <form method="POST" action="{{ route('status.ajukan_ulang') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf

                <div>
                    <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Upload Ulang Foto KTP (JPG/PNG/PDF, maks 2MB)</label>
                    <input type="file" name="foto_ktp" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-text border border-line rounded p-2.5 bg-white" />
                </div>

                @if($user->isTourGuide())
                    <div>
                        <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Upload Ulang Sertifikat / Lisensi Pemandu (Opsional)</label>
                        <input type="file" name="sertifikat_lisensi" accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-xs text-text border border-line rounded p-2.5 bg-white" />
                    </div>
                @elseif($user->isMitra())
                    <div>
                        <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Upload Ulang Bukti Usaha / Surat Izin (JPG/PNG/PDF)</label>
                        <input type="file" name="bukti_usaha" accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-xs text-text border border-line rounded p-2.5 bg-white" />
                    </div>
                @endif

                <x-button type="submit" class="w-full mt-2">Kirim Ulang Dokumen Perbaikan</x-button>
            </form>
        </div>
    @endif

    <div class="flex items-center justify-between border-t border-line pt-4 mt-2">
        <a href="/" class="text-xs text-muted hover:text-text">← Kembali ke Beranda</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-red-600 hover:underline cursor-pointer">Keluar (Logout)</button>
        </form>
    </div>
</div>
@endsection
