<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GianyarPlanner')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg text-text font-inter antialiased">

<header class="w-full border-b border-line bg-white">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 h-20 flex items-center justify-between">
        <a href="/" class="font-playfair font-medium text-text text-[26px] tracking-[-0.5px]">GianyarPlanner</a>
        <nav class="hidden md:flex items-center gap-8 font-inter text-[15px] text-text">
            <a href="/#destinasi" class="hover:text-forest transition-colors">Destinasi</a>
            <a href="{{ route('preferensi.create') }}" class="{{ request()->routeIs('preferensi.*', 'itinerary.*') ? 'font-semibold border-b-2 border-forest pb-1' : 'hover:text-forest transition-colors' }}">Planner</a>
            <a href="#" class="hover:text-forest transition-colors">Guide</a>
            <a href="#" class="hover:text-forest transition-colors">Gear</a>
            <a href="#" class="hover:text-forest transition-colors">Paket</a>
        </nav>
        <div class="relative">
            <button id="avatar-btn" type="button" class="flex items-center gap-2 cursor-pointer">
                <span class="w-10 h-10 rounded-full bg-forest text-white flex items-center justify-center font-inter font-semibold text-sm">
                    {{ strtoupper(substr(session('user.nama', 'U'), 0, 2)) }}
                </span>
                <svg class="w-4 h-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="avatar-menu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-line rounded-md py-2 z-50">
                <div class="px-4 py-2 border-b border-line">
                    <p class="font-medium text-sm">{{ session('user.nama') }}</p>
                    <p class="text-xs text-muted">{{ session('user.email') }}</p>
                </div>
                @php
                    $userRole = Auth::user()->role ?? session('user.role');
                @endphp
                @if($userRole === 'super_admin')
                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-sand">Backoffice Admin</a>
                    <a href="{{ route('admin.guides.index') }}" class="block px-4 py-2 text-sm hover:bg-sand">Verifikasi Guide</a>
                    <a href="{{ route('admin.mitras.index') }}" class="block px-4 py-2 text-sm hover:bg-sand">Verifikasi Mitra</a>
                @elseif($userRole === 'tour_guide')
                    <a href="{{ route('guide.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-sand">Dashboard Guide</a>
                    <a href="{{ route('guide.kalender') }}" class="block px-4 py-2 text-sm hover:bg-sand">Kalender Ketersediaan</a>
                    <a href="{{ route('guide.bookings') }}" class="block px-4 py-2 text-sm hover:bg-sand">Permintaan Booking</a>
                @elseif($userRole === 'mitra')
                    <a href="{{ route('mitra.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-sand">Dashboard Mitra</a>
                    <a href="{{ route('mitra.gears.index') }}" class="block px-4 py-2 text-sm hover:bg-sand">Katalog Gear</a>
                    <a href="{{ route('mitra.orders') }}" class="block px-4 py-2 text-sm hover:bg-sand">Pesanan Masuk</a>
                @else
                    <a href="{{ route('pesanan.index') }}" class="block px-4 py-2 text-sm hover:bg-sand">Pesanan Saya</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-sand cursor-pointer">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</header>

<main class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 py-12">
    @if(session('success'))
        <x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
    @endif
    @yield('content')
</main>

<footer class="w-full bg-forest text-white">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 pt-16 pb-8">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-12">
            <div class="max-w-[410px]">
                <div class="font-playfair text-white text-4xl tracking-[-1px]">GianyarPlanner</div>
                <p class="text-[#d8dfd7] text-[17px] leading-[28.9px] mt-6 max-w-[340px]">Perjalanan yang dirancang untukmu. Cerita yang tumbuh bersama Gianyar.</p>
                <div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase mt-6">BERAKAR DI GIANYAR, BALI.</div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-10 lg:gap-14">
                <nav class="flex flex-col gap-6"><div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">JELAJAHI</div><div class="flex flex-col gap-4 text-[15px]"><a href="/#destinasi" class="hover:text-[#d8dfd7]">Destinasi</a><a href="{{ route('preferensi.create') }}" class="hover:text-[#d8dfd7]">Planner</a><a href="#" class="hover:text-[#d8dfd7]">Paket</a></div></nav>
                <nav class="flex flex-col gap-6"><div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">TEMAN PERJALANAN</div><div class="flex flex-col gap-4 text-[15px]"><a href="#" class="hover:text-[#d8dfd7]">Guide Lokal</a><a href="#" class="hover:text-[#d8dfd7]">Sewa Gear</a></div></nav>
                <nav class="flex flex-col gap-6"><div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">GIANYARPLANNER</div><div class="flex flex-col gap-4 text-[15px]"><a href="#" class="hover:text-[#d8dfd7]">Tentang Kami</a><a href="#" class="hover:text-[#d8dfd7]">Hubungi Kami</a><a href="{{ route('login') }}" class="hover:text-[#d8dfd7]">Masuk / Daftar</a></div></nav>
            </div>
        </div>
        <div class="mt-14 pt-6 border-t border-[#657d6d] flex flex-col sm:flex-row justify-between gap-4 text-[13px] text-[#d8dfd7]">
            <span>&copy; 2026 GianyarPlanner</span>
            <nav class="flex gap-8"><a href="#" class="hover:text-white">Privacy</a><a href="#" class="hover:text-white">Terms</a></nav>
        </div>
    </div>
</footer>
</body>
</html>
