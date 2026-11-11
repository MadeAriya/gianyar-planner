@extends('layouts.app')
@section('title', 'Manajemen Pengguna — Admin GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Manajemen Pengguna</h1>
            <p class="text-xs text-muted mt-1">Daftar seluruh akun pengguna yang terdaftar pada sistem Gianyar Planner.</p>
        </div>

        {{-- Filter Peran --}}
        <div class="flex flex-wrap gap-2">
            @foreach(['all' => 'Semua Peran', 'user' => 'Wisatawan', 'tour_guide' => 'Tour Guide', 'mitra' => 'Mitra Gear', 'super_admin' => 'Admin'] as $key => $label)
                <a href="{{ route('admin.users.index', ['role' => $key, 'status' => $status]) }}"
                    class="px-3 py-1.5 rounded text-xs font-inter font-medium {{ $role === $key ? 'bg-forest text-white' : 'border border-line text-muted hover:border-text' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    <x-card class="overflow-x-auto">
        <table class="w-full text-left text-xs font-inter">
            <thead>
                <tr class="border-b border-line bg-sand/30 text-muted uppercase">
                    <th class="p-3">ID</th>
                    <th class="p-3">Nama Lengkap</th>
                    <th class="p-3">Email & No HP</th>
                    <th class="p-3">Peran (Role)</th>
                    <th class="p-3">Status Akun</th>
                    <th class="p-3">Terdaftar Sejak</th>
                    <th class="p-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line/60">
                @forelse($users as $u)
                    <tr class="hover:bg-sand/10">
                        <td class="p-3 text-muted">#{{ $u->id }}</td>
                        <td class="p-3 font-semibold text-text">{{ $u->nama ?? $u->name }}</td>
                        <td class="p-3 text-muted">
                            {{ $u->email }}
                            <span class="block text-[10px] text-muted/80">{{ $u->no_hp ?? '—' }}</span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-sand text-text">
                                {{ ucfirst(str_replace('_', ' ', $u->role)) }}
                            </span>
                        </td>
                        <td class="p-3">
                            @php
                                $badge = match($u->status) {
                                    'active' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'suspended' => 'bg-gray-200 text-gray-800',
                                    default => 'bg-sand text-muted',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $badge }}">
                                {{ ucfirst($u->status) }}
                            </span>
                        </td>
                        <td class="p-3 text-muted">{{ $u->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="p-3 text-right">
                            @if(!$u->isSuperAdmin() && $u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="px-2.5 py-1 rounded text-[11px] font-medium border {{ $u->isSuspended() ? 'border-green-600 text-green-700 hover:bg-green-50' : 'border-red-300 text-red-600 hover:bg-red-50' }} cursor-pointer">
                                        {{ $u->isSuspended() ? 'Aktifkan' : 'Suspend' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-muted">Tidak ada data pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div>
        {{ $users->appends(['role' => $role, 'status' => $status])->links() }}
    </div>
</div>
@endsection
