@extends('layouts.app')
@section('title', 'Verifikasi Mitra Gear — Admin GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Daftar Verifikasi Mitra Penyedia Gear</h1>
        </div>

        {{-- Filter Tab Status --}}
        <div class="flex gap-2">
            @foreach(['all' => 'Semua', 'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'suspended' => 'Suspended'] as $key => $label)
                <a href="{{ route('admin.mitras.index', ['status' => $key]) }}"
                    class="px-3 py-1.5 rounded text-xs font-inter font-medium {{ $status === $key ? 'bg-forest text-white' : 'border border-line text-muted hover:border-text' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    <x-card class="overflow-x-auto">
        <table class="w-full text-left text-xs font-inter">
            <thead>
                <tr class="border-b border-line bg-sand/30 text-muted uppercase">
                    <th class="p-3">Nama Usaha</th>
                    <th class="p-3">Pemilik / User</th>
                    <th class="p-3">Kecamatan & Alamat</th>
                    <th class="p-3">Metode Serah</th>
                    <th class="p-3">Status Verifikasi</th>
                    <th class="p-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line/60">
                @forelse($mitras as $m)
                    <tr class="hover:bg-sand/10">
                        <td class="p-3 font-semibold text-text">
                            {{ $m->nama_usaha }}
                        </td>
                        <td class="p-3 text-muted">
                            {{ $m->user->nama ?? $m->user->name }}
                            <span class="block text-[10px] text-muted/80">{{ $m->user->email }} · {{ $m->user->no_hp }}</span>
                        </td>
                        <td class="p-3 text-text">
                            {{ $m->kecamatan }}
                            <span class="block text-[11px] text-muted truncate max-w-xs">{{ $m->alamat }}</span>
                        </td>
                        <td class="p-3 text-muted">
                            {{ ucfirst(str_replace('_', ' ', $m->metode_serah)) }}
                        </td>
                        <td class="p-3">
                            @php
                                $badgeClass = match($m->status_verifikasi) {
                                    'approved' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'suspended' => 'bg-gray-200 text-gray-800',
                                    default => 'bg-sand text-muted',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $badgeClass }}">
                                {{ ucfirst($m->status_verifikasi) }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <a href="{{ route('admin.mitras.show', $m->id) }}" class="px-3 py-1 rounded bg-forest text-white hover:bg-forest-dark text-[11px] font-semibold">
                                Periksa
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-muted">Tidak ada data mitra sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-2">
        {{ $mitras->appends(['status' => $status])->links() }}
    </div>
</div>
@endsection
