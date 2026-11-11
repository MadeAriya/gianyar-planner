@extends('layouts.app')
@section('title', 'Kalender Ketersediaan — Tour Guide GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('guide.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Kalender Ketersediaan Pemandu</h1>
            <p class="text-xs text-muted mt-1">Blokir tanggal saat Anda berhalangan, atau buka kembali tanggal untuk menerima pesanan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs font-inter">
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-500"></span> Tersedia</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-400"></span> Diblokir Manual</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-yellow-400"></span> Ditahan (Menunggu Bayar)</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-forest"></span> Terpesan (Lunas)</span>
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    {{-- Grid Kalender 30 Hari --}}
    <x-card class="p-6">
        <h2 class="font-playfair text-xl font-bold text-text mb-4">Jadwal 30 Hari ke Depan</h2>
        <div class="grid grid-cols-2 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-7 gap-3">
            @foreach($days as $d)
                @php
                    $isTersedia = $d['status'] === 'tersedia';
                    $isBlocked = $d['status'] === 'diblokir_guide';
                    $isDitahan = $d['status'] === 'ditahan';
                    $isBooked = $d['status'] === 'terpesan';

                    $cardBg = match($d['status']) {
                        'tersedia' => 'bg-green-50/60 border-green-300 text-green-900',
                        'diblokir_guide' => 'bg-gray-100 border-gray-300 text-gray-700',
                        'ditahan' => 'bg-yellow-50 border-yellow-300 text-yellow-800',
                        'terpesan' => 'bg-forest/10 border-forest text-forest font-semibold',
                        default => 'bg-sand/30 border-line text-text',
                    };
                @endphp
                <div class="p-3 border rounded-md flex flex-col justify-between h-28 {{ $cardBg }}">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase font-bold text-muted">{{ $d['day_name'] }}</span>
                            <span class="text-xs font-semibold">{{ $d['month_name'] }}</span>
                        </div>
                        <p class="font-playfair text-2xl font-bold mt-0.5">{{ $d['day_num'] }}</p>
                    </div>

                    <div class="pt-2 border-t border-line/40 flex items-center justify-between">
                        <span class="text-[10px] font-medium uppercase truncate">
                            {{ str_replace('_', ' ', $d['status']) }}
                        </span>

                        @if($isTersedia)
                            <form method="POST" action="{{ route('guide.kalender.toggle') }}" class="inline">
                                @csrf
                                <input type="hidden" name="tanggal" value="{{ $d['date_str'] }}">
                                <input type="hidden" name="action" value="blokir">
                                <button type="submit" title="Blokir tanggal ini"
                                    class="text-[10px] px-1.5 py-0.5 bg-gray-200 hover:bg-gray-300 text-text rounded cursor-pointer">
                                    Blokir
                                </button>
                            </form>
                        @elseif($isBlocked)
                            <form method="POST" action="{{ route('guide.kalender.toggle') }}" class="inline">
                                @csrf
                                <input type="hidden" name="tanggal" value="{{ $d['date_str'] }}">
                                <input type="hidden" name="action" value="buka">
                                <button type="submit" title="Buka kembali tanggal ini"
                                    class="text-[10px] px-1.5 py-0.5 bg-green-600 hover:bg-green-700 text-white rounded cursor-pointer">
                                    Buka
                                </button>
                            </form>
                        @else
                            <span class="text-[10px] text-muted">Terkunci</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
@endsection
