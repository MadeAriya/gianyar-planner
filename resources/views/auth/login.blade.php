@extends('layouts.guest')
@section('title', 'Masuk — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8">
    <div>
        <h1 class="font-playfair text-3xl text-text">Masuk ke Akun Anda</h1>
        <p class="text-muted mt-2">Mulai rencanakan perjalananmu di Gianyar.</p>
    </div>

    <form method="POST" action="{{ route('login.process') }}" class="flex flex-col gap-5">
        @csrf
        <x-input label="Email" name="email" type="email" placeholder="email@contoh.com" :required="true" />
        <x-input label="Password" name="password" type="password" placeholder="Masukkan password" :required="true" />

        <x-button type="submit" class="w-full mt-2">Masuk</x-button>
    </form>

    <div class="text-center text-sm text-muted space-y-2">
        <p>Belum punya akun? <a href="{{ route('register') }}" class="text-forest font-medium hover:underline">Daftar</a></p>
        <p>Ingin bergabung sebagai Tour Guide / Penyedia Gear?<br>
            <a href="{{ route('register', ['role' => 'tour_guide']) }}" class="text-terracotta font-medium hover:underline">Daftar sebagai Mitra</a>
        </p>
    </div>
</div>
@endsection
