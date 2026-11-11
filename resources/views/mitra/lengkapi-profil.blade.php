@extends('layouts.app')
@section('title', 'Lengkapi Profil — GianyarPlanner')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="font-playfair text-4xl text-text tracking-tight mb-2">Lengkapi Profil Mitra</h1>
    <p class="text-muted text-[15px] mb-8">Lengkapi data usaha Anda untuk verifikasi oleh admin.</p>

    {{-- Status banners --}}
    @if($user['status'] === 'menunggu_verifikasi')
        <x-alert type="warning" class="mb-6">
            Profil Anda sedang dalam proses verifikasi. Harap menunggu konfirmasi dari admin.
        </x-alert>
    @elseif($user['status'] === 'ditolak')
        <x-alert type="error" class="mb-6">
            Profil Anda ditolak.
            @if(!empty($user['notes']))
                <br><strong>Catatan admin:</strong> {{ $user['notes'] }}
            @endif
            <br>Silakan perbaiki data dan ajukan ulang.
        </x-alert>
    @elseif($user['status'] === 'aktif')
        <x-alert type="success" class="mb-6">
            Profil Anda sudah terverifikasi dan aktif.
        </x-alert>
    @endif

    @if($errors->any())
        <x-alert type="error" class="mb-6">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('mitra.profil.save') }}" enctype="multipart/form-data">
        @csrf

        <x-card class="p-6 mb-6">
            <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Informasi Usaha</h2>

            <div class="flex flex-col gap-4">
                <x-input name="nama_usaha" label="Nama Usaha" :value="old('nama_usaha', $user['nama_usaha'] ?? '')" required />
                <x-input name="alamat" label="Alamat" :value="old('alamat', $user['alamat'] ?? '')" required />

                <div>
                    <label for="foto_ktp" class="block text-sm font-medium text-text mb-1">Foto KTP</label>
                    <input type="file" name="foto_ktp" id="foto_ktp" accept="image/*"
                        class="block w-full text-sm text-text border border-line rounded p-3" />
                    @if(!empty($user['foto_ktp']))
                        <p class="text-muted text-xs mt-1">File sebelumnya: {{ basename($user['foto_ktp']) }}</p>
                    @endif
                    @error('foto_ktp')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bukti_usaha" class="block text-sm font-medium text-text mb-1">Bukti Usaha</label>
                    <input type="file" name="bukti_usaha" id="bukti_usaha" accept="image/*,.pdf"
                        class="block w-full text-sm text-text border border-line rounded p-3" />
                    @if(!empty($user['bukti_usaha']))
                        <p class="text-muted text-xs mt-1">File sebelumnya: {{ basename($user['bukti_usaha']) }}</p>
                    @endif
                    @error('bukti_usaha')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-card>

        {{-- Tour Guide extra fields --}}
        @if($user['role'] === 'tour_guide')
            <x-card class="p-6 mb-6">
                <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Informasi Tour Guide</h2>

                <div class="flex flex-col gap-4">
                    <x-input name="bahasa" label="Bahasa (pisahkan dengan koma)" :value="old('bahasa', $user['bahasa'] ?? '')" placeholder="ID, EN" required />
                    <x-input name="tarif" label="Tarif per Hari (Rp)" type="number" :value="old('tarif', $user['tarif'] ?? '')" required />

                    <div>
                        <label for="foto" class="block text-sm font-medium text-text mb-1">Foto Profil</label>
                        <input type="file" name="foto" id="foto" accept="image/*"
                            class="block w-full text-sm text-text border border-line rounded p-3" />
                        @if(!empty($user['foto']))
                            <p class="text-muted text-xs mt-1">File sebelumnya: {{ basename($user['foto']) }}</p>
                        @endif
                        @error('foto')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </x-card>
        @endif

        <x-button type="submit" class="w-full">Ajukan Verifikasi</x-button>
    </form>
</div>
@endsection
