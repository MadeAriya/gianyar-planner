@extends('layouts.guest')
@section('title', 'Daftar — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8">
    <div>
        <h1 class="font-playfair text-3xl text-text">Daftar Akun Baru</h1>
        <p class="text-muted mt-2">Bergabunglah dan mulai rencanakan perjalananmu.</p>
    </div>

    <form method="POST" action="{{ route('register.process') }}" class="flex flex-col gap-5">
        @csrf

        {{-- Pilih Peran --}}
        <div class="flex flex-col gap-2">
            <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Pilih Peran</label>
            <div class="grid grid-cols-3 gap-3">
                @php
                    $roles = [
                        'wisatawan' => ['label' => 'Wisatawan', 'desc' => 'Rencanakan perjalanan'],
                        'tour_guide' => ['label' => 'Tour Guide', 'desc' => 'Tawarkan jasa guide'],
                        'penyedia_gear' => ['label' => 'Penyedia Gear', 'desc' => 'Sewakan perlengkapan'],
                    ];
                    $selectedRole = old('role', $preselectedRole ?? 'wisatawan');
                @endphp
                @foreach($roles as $value => $info)
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="{{ $value }}" class="peer hidden" {{ $selectedRole === $value ? 'checked' : '' }}>
                        <div class="border border-line rounded-md p-4 text-center transition-colors peer-checked:border-forest peer-checked:bg-forest/5">
                            <p class="font-inter font-semibold text-sm text-text">{{ $info['label'] }}</p>
                            <p class="text-xs text-muted mt-1">{{ $info['desc'] }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('role')
                <p class="text-red-600 text-xs font-inter">{{ $message }}</p>
            @enderror
        </div>

        <x-input label="Nama Lengkap" name="nama" placeholder="Nama lengkap" :required="true" />
        <x-input label="Email" name="email" type="email" placeholder="email@contoh.com" :required="true" />
        <x-input label="Nomor HP" name="no_hp" placeholder="08xxxxxxxxxx" :required="true" />
        <x-input label="Password" name="password" type="password" placeholder="Minimal 8 karakter" :required="true" />
        <x-input label="Konfirmasi Password" name="password_confirmation" type="password" placeholder="Ulangi password" :required="true" />

        <x-button type="submit" class="w-full mt-2">Daftar Sekarang</x-button>
    </form>

    <p class="text-center text-sm text-muted">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-forest font-medium hover:underline">Masuk</a>
    </p>
</div>
@endsection
