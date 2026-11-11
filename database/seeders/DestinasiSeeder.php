<?php

namespace Database\Seeders;

use App\Models\Destinasi;
use App\Support\DummyData;
use Illuminate\Database\Seeder;

class DestinasiSeeder extends Seeder
{
    /**
     * Seed 42 destinasi wisata nyata Kabupaten Gianyar ke database.
     * Idempoten: menggunakan updateOrCreate berbasis slug unik.
     */
    public function run(): void
    {
        $allDestinasi = DummyData::destinasi();

        foreach ($allDestinasi as $d) {
            Destinasi::updateOrCreate(
                ['slug' => $d['slug']],
                [
                    'id' => $d['id'],
                    'nama_destinasi' => $d['nama_destinasi'] ?? $d['nama'],
                    'nama' => $d['nama'] ?? $d['nama_destinasi'],
                    'kecamatan' => $d['kecamatan'],
                    'lokasi' => $d['lokasi'],
                    'lat' => $d['lat'],
                    'lng' => $d['lng'],
                    'kategori_utama' => $d['kategori_utama'],
                    'subkategori' => $d['subkategori'],
                    'kategori' => $d['kategori'],
                    'tags' => $d['tags'] ?? [],
                    'harga_tiket' => $d['harga_tiket'] ?? 0,
                    'durasi_kunjungan_menit' => $d['durasi_kunjungan_menit'] ?? 90,
                    'jam_buka' => $d['jam_buka'] ?? '08:00',
                    'jam_tutup' => $d['jam_tutup'] ?? '18:00',
                    'waktu_terbaik' => $d['waktu_terbaik'] ?? ['pagi'],
                    'tingkat_fisik' => $d['tingkat_fisik'] ?? 'sedang',
                    'popularitas' => $d['popularitas'] ?? 5,
                    'cocok_untuk' => $d['cocok_untuk'] ?? ['couple'],
                    'rekomendasi_travel_gear' => $d['rekomendasi_travel_gear'] ?? [],
                    'tips' => $d['tips'] ?? null,
                    'guide_singkat' => $d['guide_singkat'] ?? null,
                    'deskripsi' => $d['deskripsi'] ?? null,
                    'foto' => $d['foto'],
                    'zona' => $d['zona'] ?? 'Gianyar',
                    'slot_ideal' => $d['slot_ideal'] ?? ['pagi', 'siang', 'sore'],
                    'is_active' => true,
                ]
            );
        }
    }
}
