@extends('layouts.app')
@section('title', 'Katalog Gear — Mitra GianyarPlanner')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-line">
        <div>
            <a href="{{ route('mitra.dashboard') }}" class="text-xs text-muted hover:text-text mb-1 inline-block">← Kembali ke Dashboard</a>
            <h1 class="font-playfair text-3xl text-text">Katalog Barang Perlengkapan</h1>
            <p class="text-xs text-muted mt-1">Daftar perlengkapan wisata yang Anda sewakan atau jual kepada wisatawan.</p>
        </div>
        <a href="{{ route('mitra.gears.create') }}" class="px-4 py-2 bg-forest text-white rounded text-xs font-inter font-semibold hover:bg-forest-dark transition-colors">
            + Tambah Barang Baru
        </a>
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
                    <th class="p-3">Foto & Nama Barang</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3">Tipe</th>
                    <th class="p-3">Harga</th>
                    <th class="p-3">Stok Total</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line/60">
                @forelse($gears as $g)
                    <tr class="hover:bg-sand/10">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $g->foto }}" alt="{{ $g->nama_barang }}" class="w-10 h-10 object-cover rounded flex-shrink-0" loading="lazy" />
                                <div>
                                    <p class="font-semibold text-text text-sm">{{ $g->nama_barang }}</p>
                                    <p class="text-[10px] text-muted truncate max-w-xs">{{ implode(', ', (array) $g->tags_aktivitas) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 text-muted">{{ $g->category->nama ?? '—' }}</td>
                        <td class="p-3 uppercase font-medium text-xs">{{ $g->tipe }}</td>
                        <td class="p-3 font-semibold text-terracotta">
                            Rp {{ number_format($g->harga, 0, ',', '.') }} {{ $g->tipe === 'sewa' ? '/ hari' : '/ unit' }}
                        </td>
                        <td class="p-3 font-semibold text-text">{{ $g->stok_total }} Unit</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $g->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                {{ $g->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('mitra.gears.edit', $g->id) }}" class="px-2.5 py-1 rounded border border-line hover:border-forest text-text text-[11px] font-medium">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('mitra.gears.destroy', $g->id) }}" onsubmit="return confirm('Hapus barang ini dari katalog?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 rounded border border-red-200 hover:bg-red-50 text-red-600 text-[11px] font-medium cursor-pointer">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-muted">Belum ada barang di katalog. Klik "+ Tambah Barang Baru".</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div>
        {{ $gears->links() }}
    </div>
</div>
@endsection
