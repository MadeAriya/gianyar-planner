@extends('layouts.app')
@section('title', 'Dashboard Mitra — GianyarPlanner')

@section('content')
<h1 class="font-playfair text-4xl text-text tracking-tight mb-2">Dashboard Mitra</h1>
<p class="text-muted text-[15px] mb-8">Selamat datang, {{ $user['nama'] }}.</p>

{{-- Status Card --}}
@php
    $statusConfig = [
        'belum_lengkap' => ['label' => 'Belum Lengkap', 'color' => 'bg-yellow-100 text-yellow-800', 'type' => 'warning'],
        'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'color' => 'bg-blue-100 text-blue-800', 'type' => 'warning'],
        'aktif' => ['label' => 'Aktif', 'color' => 'bg-green-100 text-green-800', 'type' => 'success'],
        'ditolak' => ['label' => 'Ditolak', 'color' => 'bg-red-100 text-red-800', 'type' => 'error'],
    ];
    $cfg = $statusConfig[$user['status']] ?? ['label' => $user['status'], 'color' => 'bg-gray-100 text-gray-800', 'type' => 'warning'];
@endphp

<x-card class="p-6 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider">Status Akun</h2>
            <p class="text-muted text-sm mt-1">{{ ucfirst(str_replace('_', ' ', $user['role'])) }}</p>
        </div>
        <span class="inline-block px-4 py-1.5 rounded-full text-xs font-medium {{ $cfg['color'] }}">{{ $cfg['label'] }}</span>
    </div>
</x-card>

@if($user['status'] === 'belum_lengkap')
    <x-alert type="warning" class="mb-6">
        Profil Anda belum lengkap. <a href="{{ route('mitra.profil') }}" class="underline font-medium">Lengkapi profil sekarang</a> untuk mengajukan verifikasi.
    </x-alert>
@elseif($user['status'] === 'ditolak')
    <x-alert type="error" class="mb-6">
        Profil Anda ditolak.
        @if(!empty($user['notes']))
            <br><strong>Catatan:</strong> {{ $user['notes'] }}
        @endif
        <br><a href="{{ route('mitra.profil') }}" class="underline font-medium">Ajukan ulang</a>
    </x-alert>
@elseif($user['status'] === 'menunggu_verifikasi')
    <x-alert type="warning" class="mb-6">
        Profil sedang diverifikasi oleh admin. Harap menunggu.
    </x-alert>
@endif

{{-- Profile Info --}}
<x-card class="p-6">
    <h2 class="font-inter font-semibold text-text text-sm uppercase tracking-wider mb-4">Informasi Profil</h2>
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="text-muted">Nama</span>
            <p class="font-medium text-text">{{ $user['nama'] }}</p>
        </div>
        <div>
            <span class="text-muted">Email</span>
            <p class="font-medium text-text">{{ $user['email'] }}</p>
        </div>
        <div>
            <span class="text-muted">No. HP</span>
            <p class="font-medium text-text">{{ $user['no_hp'] ?? '-' }}</p>
        </div>
        <div>
            <span class="text-muted">Peran</span>
            <p class="font-medium text-text">{{ ucfirst(str_replace('_', ' ', $user['role'])) }}</p>
        </div>
        @if(!empty($user['nama_usaha']))
            <div>
                <span class="text-muted">Nama Usaha</span>
                <p class="font-medium text-text">{{ $user['nama_usaha'] }}</p>
            </div>
        @endif
        @if(!empty($user['alamat']))
            <div>
                <span class="text-muted">Alamat</span>
                <p class="font-medium text-text">{{ $user['alamat'] }}</p>
            </div>
        @endif
    </div>

    <div class="mt-6 pt-4 border-t border-line">
        <a href="{{ route('mitra.profil') }}" class="text-forest text-sm font-medium hover:underline">Edit Profil →</a>
    </div>
</x-card>
@endsection
