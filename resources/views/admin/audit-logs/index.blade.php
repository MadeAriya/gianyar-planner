@extends('layouts.app')
@section('title', 'Jejak Audit — Admin GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between pb-4 border-b border-line">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Jejak Audit (Audit Trail Log)</h1>
            <p class="text-xs text-muted mt-1">Rekaman seluruh keputusan verifikasi, transaksi, dan tindakan administratif.</p>
        </div>
    </div>

    <x-card class="overflow-x-auto">
        <table class="w-full text-left text-xs font-inter">
            <thead>
                <tr class="border-b border-line bg-sand/30 text-muted uppercase">
                    <th class="p-3">ID</th>
                    <th class="p-3">Waktu Eksekusi</th>
                    <th class="p-3">Aktor (Pengguna)</th>
                    <th class="p-3">Aksi</th>
                    <th class="p-3">Entitas Sasaran</th>
                    <th class="p-3">Data Tambahan / Metadata</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line/60">
                @forelse($logs as $log)
                    <tr class="hover:bg-sand/10">
                        <td class="p-3 text-muted">#{{ $log->id }}</td>
                        <td class="p-3 text-muted whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="p-3 font-semibold text-text">
                            {{ $log->actor->nama ?? ($log->actor->email ?? 'Sistem / Anonim') }}
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-sand text-forest">
                                {{ $log->aksi }}
                            </span>
                        </td>
                        <td class="p-3 text-text">
                            {{ $log->target_type }} #{{ $log->target_id }}
                        </td>
                        <td class="p-3 text-muted font-mono text-[11px] break-all max-w-md">
                            {{ json_encode($log->meta, JSON_UNESCAPED_SLASHES) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-muted">Belum ada riwayat audit log tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div>
        {{ $logs->links() }}
    </div>
</div>
@endsection
