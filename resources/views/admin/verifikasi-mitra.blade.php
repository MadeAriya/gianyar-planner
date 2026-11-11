@extends('layouts.app')
@section('title', 'Verifikasi Mitra — GianyarPlanner')

@section('content')
<h1 class="font-playfair text-4xl text-text tracking-tight mb-2">Verifikasi Mitra</h1>
<p class="text-muted text-[15px] mb-8">Kelola pendaftaran tour guide dan penyedia gear.</p>

@if($mitraList->isEmpty())
    <x-card class="p-8 text-center">
        <p class="text-muted text-[15px]">Belum ada mitra terdaftar.</p>
    </x-card>
@else
    <div class="flex flex-col gap-4">
        @foreach($mitraList as $mitra)
            @php
                $statusColors = [
                    'belum_lengkap' => 'bg-gray-100 text-gray-800',
                    'menunggu_verifikasi' => 'bg-yellow-100 text-yellow-800',
                    'aktif' => 'bg-green-100 text-green-800',
                    'ditolak' => 'bg-red-100 text-red-800',
                ];
                $color = $statusColors[$mitra['status']] ?? 'bg-gray-100 text-gray-800';
            @endphp
            <x-card class="p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <p class="font-inter font-semibold text-text text-sm">{{ $mitra['nama'] }}</p>
                        <p class="text-muted text-xs mt-1">{{ $mitra['email'] }} · {{ ucfirst(str_replace('_', ' ', $mitra['role'])) }}</p>
                        @if(!empty($mitra['nama_usaha']))
                            <p class="text-muted text-xs mt-1">{{ $mitra['nama_usaha'] }} — {{ $mitra['alamat'] ?? '' }}</p>
                        @endif
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium {{ $color }}">{{ ucfirst(str_replace('_', ' ', $mitra['status'])) }}</span>
                </div>

                @if($mitra['status'] === 'menunggu_verifikasi')
                    <form method="POST" action="{{ route('admin.mitra.verify', ['email' => $mitra['email']]) }}" class="border-t border-line pt-4">
                        @csrf
                        <div class="mb-3">
                            <label for="notes-{{ $loop->index }}" class="block text-xs font-medium text-muted mb-1">Catatan (wajib jika ditolak)</label>
                            <textarea name="notes" id="notes-{{ $loop->index }}" rows="2"
                                class="w-full border border-line rounded p-3 text-sm font-inter focus:border-forest focus:ring-1 focus:ring-forest outline-none" placeholder="Tuliskan alasan penolakan..."></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" name="action" value="setujui"
                                class="h-10 px-6 bg-forest text-white font-inter text-sm rounded cursor-pointer hover:bg-forest-dark">
                                Setujui
                            </button>
                            <button type="submit" name="action" value="tolak"
                                class="h-10 px-6 border border-red-500 text-red-600 font-inter text-sm rounded cursor-pointer hover:bg-red-50">
                                Tolak
                            </button>
                        </div>
                    </form>
                @endif
            </x-card>
        @endforeach
    </div>
@endif
@endsection
