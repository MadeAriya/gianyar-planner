<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GianyarPlanner — Rancang Perjalanan Impianmu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg text-text font-inter antialiased">

{{-- ======== NAVBAR ======== --}}
<header class="absolute top-0 left-0 right-0 z-50">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 h-20 flex items-center justify-between">
        <a href="/" class="font-playfair font-medium text-white text-[26px] tracking-[-0.5px]">GianyarPlanner</a>
        <nav class="hidden md:flex items-center gap-8 font-inter text-[15px] text-white/80">
            <a href="#destinasi" class="hover:text-white transition-colors">Destinasi</a>
            <a href="#cara-kerja" class="hover:text-white transition-colors">Planner</a>
            <a href="#" class="hover:text-white transition-colors">Guide</a>
            <a href="#" class="hover:text-white transition-colors">Gear</a>
            <a href="#paket" class="hover:text-white transition-colors">Paket</a>
        </nav>
        @if(session('user'))
            <a href="{{ route('preferensi.create') }}" class="h-10 px-6 bg-forest text-white text-sm font-medium rounded flex items-center">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="h-10 px-6 bg-forest text-white text-sm font-medium rounded flex items-center hover:bg-forest-dark">Masuk / Daftar</a>
        @endif
    </div>
</header>

{{-- ======== HERO SECTION ======== --}}
<section class="relative h-[700px] overflow-hidden">
    <img src="https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1920&q=80"
         alt="Tegallalang Rice Terrace" class="absolute inset-0 w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-transparent"></div>

    <div class="relative z-10 max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 h-full flex flex-col justify-end pb-24">
        <p class="text-white/70 text-xs tracking-[3px] uppercase mb-4">EXPLORE BALI · DISCOVER GIANYAR</p>
        <h1 class="font-playfair text-white text-5xl lg:text-6xl leading-tight max-w-[700px]">
            Rancang Perjalanan<br>Impianmu di Gianyar dalam Hitungan Detik.
        </h1>
        <p class="text-white/70 text-[17px] mt-4 max-w-[500px]">
            Buat itinerary personal, temukan guide lokal, dan sewa perlengkapan perjalanan.
        </p>
        <a href="{{ route('preferensi.create') }}"
           class="mt-8 inline-flex items-center gap-2 h-14 px-10 bg-forest text-white font-inter font-medium text-[15px] rounded w-fit hover:bg-forest-dark">
            Mulai Buat Itinerary
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
        <p class="text-white/50 text-xs mt-4 tracking-wide">✦ Gratis tanpa biaya platform</p>
    </div>
</section>

{{-- ======== HOW IT WORKS ======== --}}
<section id="cara-kerja" class="bg-bg">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 py-24">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-6 mb-14">
            <div>
                <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-4">CARA KERJA</p>
                <h2 class="font-playfair text-4xl text-text leading-tight">Perjalanan indah, tanpa rumit.</h2>
            </div>
            <p class="text-muted text-[15px] max-w-[380px] lg:text-right">
                Dari ide hingga perjalanan nyata, GianyarPlanner menangani semuanya. Cukup 3 langkah sederhana.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            {{-- Step 1 --}}
            <div>
                <div class="grid grid-cols-3 gap-2 mb-6">
                    <img src="https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1555899434-94d1368aa7af?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                </div>
                <p class="text-terracotta font-inter font-bold text-2xl mb-2">01</p>
                <h3 class="font-playfair text-xl font-bold text-text mb-2">Isi Preferensi</h3>
                <p class="text-muted text-sm leading-relaxed">Pilih tanggal, durasi, dan kategori wisata yang kamu sukai.</p>
            </div>
            {{-- Step 2 --}}
            <div>
                <div class="grid grid-cols-3 gap-2 mb-6">
                    <img src="https://images.unsplash.com/photo-1539367628448-4bc5c9d171c8?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1604999333679-b86d54738315?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                </div>
                <p class="text-terracotta font-inter font-bold text-2xl mb-2">02</p>
                <h3 class="font-playfair text-xl font-bold text-text mb-2">Dapatkan Itinerary Otomatis</h3>
                <p class="text-muted text-sm leading-relaxed">Sistem kami buatkan jadwal perjalanan lengkap, siap kamu edit.</p>
            </div>
            {{-- Step 3 --}}
            <div>
                <div class="grid grid-cols-3 gap-2 mb-6">
                    <img src="https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                    <img src="https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=300&q=80" alt="" class="w-full h-28 object-cover rounded-md" loading="lazy" />
                </div>
                <p class="text-terracotta font-inter font-bold text-2xl mb-2">03</p>
                <h3 class="font-playfair text-xl font-bold text-text mb-2">Tambah Guide & Perlengkapan</h3>
                <p class="text-muted text-sm leading-relaxed">Pilih guide lokal berpengalaman dan sewa gear perjalanan.</p>
            </div>
        </div>
    </div>
</section>

{{-- ======== DESTINASI POPULER ======== --}}
<section id="destinasi" class="bg-sand">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 py-24">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-6 mb-14">
            <div>
                <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-4">JELAJAHI GIANYAR</p>
                <h2 class="font-playfair text-4xl text-text leading-tight">Destinasi Populer</h2>
            </div>
            <p class="text-muted text-[15px] max-w-[380px] lg:text-right">
                Dari sawah terasering hingga pura kuno — Gianyar punya cerita di setiap sudutnya.
            </p>
        </div>

        @php
            $topDestinasi = array_slice(\App\Support\DummyData::destinasi(), 0, 6);
        @endphp

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($topDestinasi as $d)
                <div class="relative rounded-md overflow-hidden h-[220px] group">
                    <img src="{{ $d['foto'] }}" alt="{{ $d['nama_destinasi'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-4 text-white">
                        <h3 class="font-playfair text-lg font-medium">{{ $d['nama_destinasi'] }}</h3>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ======== PAKET HEMAT ======== --}}
<section id="paket" class="bg-bg">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 py-24">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-6 mb-14">
            <div>
                <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase mb-4">PENAWARAN SPESIAL</p>
                <h2 class="font-playfair text-4xl text-text leading-tight">Paket Hemat</h2>
            </div>
            <p class="text-muted text-[15px] max-w-[380px] lg:text-right">
                Gabungkan itinerary dengan guide dan perlengkapan. Hemat lebih banyak, nikmati lebih banyak.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Planner + Guide --}}
            <div class="bg-white border border-line rounded-md p-8">
                <h3 class="font-playfair text-xl font-bold text-text mb-2">Planner + Guide</h3>
                <p class="text-muted text-sm mb-6">Perjalanan terencana bersama guide lokal yang berpengalaman.</p>
                <ul class="flex flex-col gap-3 text-sm text-text mb-8">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-forest flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Itinerary otomatis, guide sudah termasuk</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-forest flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Guide lokal berpengalaman</span>
                    </li>
                </ul>
                <p class="text-muted text-sm mb-4">Mulai dari</p>
                <p class="font-inter font-bold text-text text-2xl mb-6">Rp <span class="text-forest">300.000</span> <span class="text-muted text-sm font-normal">/ hari</span></p>
                <a href="{{ route('preferensi.create') }}" class="block w-full h-12 bg-forest text-white text-sm font-medium rounded flex items-center justify-center hover:bg-forest-dark">Pilih Paket</a>
            </div>

            {{-- Planner + Guide + Gear --}}
            <div class="bg-white border border-line rounded-md p-8">
                <h3 class="font-playfair text-xl font-bold text-text mb-2">Planner + Guide + Gear</h3>
                <p class="text-muted text-sm mb-6">Paket lengkap: itinerary, guide, dan perlengkapan perjalanan.</p>
                <ul class="flex flex-col gap-3 text-sm text-text mb-8">
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-forest flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Semua fitur Planner + Guide</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-forest flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Perlengkapan sudah disiapkan</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-forest flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Diskon bundle 10%</span>
                    </li>
                </ul>
                <p class="text-muted text-sm mb-4">Mulai dari</p>
                <p class="font-inter font-bold text-text text-2xl mb-6">Rp <span class="text-forest">350.000</span> <span class="text-muted text-sm font-normal">/ hari</span></p>
                <a href="{{ route('preferensi.create') }}" class="block w-full h-12 bg-forest text-white text-sm font-medium rounded flex items-center justify-center hover:bg-forest-dark">Pilih Paket</a>
            </div>
        </div>
    </div>
</section>

{{-- ======== FOOTER ======== --}}
<footer class="w-full bg-forest text-white">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 pt-16 pb-8">
        <div class="flex flex-col lg:flex-row items-start justify-between gap-12">
            <div class="max-w-[410px]">
                <div class="font-playfair text-white text-4xl tracking-[-1px]">GianyarPlanner</div>
                <p class="text-[#d8dfd7] text-[17px] leading-[28.9px] mt-6 max-w-[340px]">Perjalanan yang dirancang untukmu. Cerita yang tumbuh bersama Gianyar.</p>
                <div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase mt-6">BERAKAR DI GIANYAR, BALI.</div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-10 lg:gap-14">
                <nav class="flex flex-col gap-6">
                    <div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">JELAJAHI</div>
                    <div class="flex flex-col gap-4 text-[15px]">
                        <a href="#destinasi" class="hover:text-[#d8dfd7]">Destinasi</a>
                        <a href="{{ route('preferensi.create') }}" class="hover:text-[#d8dfd7]">Planner</a>
                        <a href="#paket" class="hover:text-[#d8dfd7]">Paket</a>
                    </div>
                </nav>
                <nav class="flex flex-col gap-6">
                    <div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">TEMAN PERJALANAN</div>
                    <div class="flex flex-col gap-4 text-[15px]">
                        <a href="#" class="hover:text-[#d8dfd7]">Guide Lokal</a>
                        <a href="#" class="hover:text-[#d8dfd7]">Sewa Gear</a>
                    </div>
                </nav>
                <nav class="flex flex-col gap-6">
                    <div class="text-[#d8dfd7] text-xs tracking-[2px] uppercase">GIANYARPLANNER</div>
                    <div class="flex flex-col gap-4 text-[15px]">
                        <a href="#" class="hover:text-[#d8dfd7]">Tentang Kami</a>
                        <a href="#" class="hover:text-[#d8dfd7]">Hubungi Kami</a>
                        <a href="{{ route('login') }}" class="hover:text-[#d8dfd7]">Masuk / Daftar</a>
                    </div>
                </nav>
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
