@extends('layouts.app')
@section('title', 'Super Admin Dashboard — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-line">
        <div>
            <p class="font-inter font-semibold text-terracotta text-xs tracking-wider uppercase mb-1">PANEL BACKOFFICE</p>
            <h1 class="font-playfair text-3xl sm:text-4xl text-text">Super Admin Dashboard</h1>
            <p class="text-muted text-sm mt-1">Kelola verifikasi pemandu, mitra gear, verifikasi transaksi pembayaran, dan pantau jejak audit.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.guides.index', ['status' => 'pending']) }}" class="px-4 py-2 bg-forest text-white rounded text-xs font-inter font-semibold hover:bg-forest-dark transition-colors">
                Antrean Guide ({{ $metrics['pending_guides'] }})
            </a>
            <a href="{{ route('admin.mitras.index', ['status' => 'pending']) }}" class="px-4 py-2 border border-line text-text rounded text-xs font-inter font-semibold hover:border-forest transition-colors">
                Antrean Mitra ({{ $metrics['pending_mitras'] }})
            </a>
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Antrean Guide Pending</p>
            <p class="font-playfair text-3xl font-bold text-terracotta mt-2">{{ $metrics['pending_guides'] }}</p>
            <p class="text-xs text-muted mt-1">{{ $metrics['approved_guides'] }} Guide Disetujui</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Antrean Mitra Pending</p>
            <p class="font-playfair text-3xl font-bold text-terracotta mt-2">{{ $metrics['pending_mitras'] }}</p>
            <p class="text-xs text-muted mt-1">{{ $metrics['approved_mitras'] }} Mitra Disetujui</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Verifikasi Pembayaran</p>
            <p class="font-playfair text-3xl font-bold text-forest mt-2">{{ $metrics['menunggu_bayar_count'] }}</p>
            <p class="text-xs text-muted mt-1">{{ $metrics['pesanan_lunas_count'] }} Pesanan Lunas</p>
        </x-card>
        <x-card class="p-5">
            <p class="text-xs font-inter font-semibold text-muted uppercase">Total Pengguna</p>
            <p class="font-playfair text-3xl font-bold text-text mt-2">{{ $metrics['total_users'] }}</p>
            <p class="text-xs text-muted mt-1">Seluruh akun terdaftar</p>
        </x-card>
    </div>

    {{-- Grid 2 Kolom: Antrean Pending Guide & Mitra --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Pending Guides --}}
        <x-card class="p-6">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-line">
                <h2 class="font-playfair text-xl text-text font-bold">Verifikasi Tour Guide Masuk</h2>
                <a href="{{ route('admin.guides.index') }}" class="text-xs text-forest hover:underline">Lihat Semua →</a>
            </div>
            @if($recentPendingGuides->isEmpty())
                <p class="text-muted text-xs py-4 text-center">Tidak ada pengajuan tour guide pending.</p>
            @else
                <div class="flex flex-col divide-y divide-line/60">
                    @foreach($recentPendingGuides as $g)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div>
                                <p class="font-inter font-bold text-sm text-text">{{ $g->user->nama ?? $g->user->name }}</p>
                                <p class="text-xs text-muted">{{ $g->user->email }} · {{ $g->area_layanan }}</p>
                                <p class="text-xs text-terracotta mt-0.5">Rp {{ number_format($g->tarif_per_hari, 0, ',', '.') }} / hari</p>
                            </div>
                            <a href="{{ route('admin.guides.show', $g->id) }}" class="px-3 py-1.5 rounded border border-line hover:border-forest text-xs font-inter font-medium text-text">
                                Periksa Berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Pending Mitras --}}
        <x-card class="p-6">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-line">
                <h2 class="font-playfair text-xl text-text font-bold">Verifikasi Mitra Gear Masuk</h2>
                <a href="{{ route('admin.mitras.index') }}" class="text-xs text-forest hover:underline">Lihat Semua →</a>
            </div>
            @if($recentPendingMitras->isEmpty())
                <p class="text-muted text-xs py-4 text-center">Tidak ada pengajuan mitra pending.</p>
            @else
                <div class="flex flex-col divide-y divide-line/60">
                    @foreach($recentPendingMitras as $m)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div>
                                <p class="font-inter font-bold text-sm text-text">{{ $m->nama_usaha }}</p>
                                <p class="text-xs text-muted">Pemilik: {{ $m->user->nama ?? $m->user->name }} · {{ $m->kecamatan }}</p>
                                <p class="text-xs text-muted/80 mt-0.5">{{ $m->alamat }}</p>
                            </div>
                            <a href="{{ route('admin.mitras.show', $m->id) }}" class="px-3 py-1.5 rounded border border-line hover:border-forest text-xs font-inter font-medium text-text">
                                Periksa Berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- Audit Trail Ringkas --}}
    <x-card class="p-6">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-line">
            <h2 class="font-playfair text-xl text-text font-bold">Jejak Audit Terkini (Audit Trail)</h2>
            <a href="{{ route('admin.audit.index') }}" class="text-xs text-forest hover:underline">Buka Audit Log Lengkap →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-inter">
                <thead>
                    <tr class="border-b border-line text-muted uppercase">
                        <th class="pb-2">Waktu</th>
                        <th class="pb-2">Aktor</th>
                        <th class="pb-2">Aksi</th>
                        <th class="pb-2">Entitas Target</th>
                        <th class="pb-2">Keterangan / Meta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/60">
                    @forelse($recentAuditLogs as $log)
                        <tr>
                            <td class="py-2.5 text-muted">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="py-2.5 font-medium text-text">{{ $log->actor->nama ?? ($log->actor->email ?? 'Sistem') }}</td>
                            <td class="py-2.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-sand text-muted">
                                    {{ $log->aksi }}
                                </span>
                            </td>
                            <td class="py-2.5 text-muted">{{ $log->target_type }} #{{ $log->target_id }}</td>
                            <td class="py-2.5 text-muted truncate max-w-xs">{{ json_encode($log->meta) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-muted">Belum ada catatan log audit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
