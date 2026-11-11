<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Destinasi Wisata Gianyar
        Schema::create('destinasis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_destinasi');
            $table->string('nama')->nullable(); // alias
            $table->string('slug')->unique();
            $table->string('kecamatan');
            $table->string('lokasi');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('kategori_utama', 50)->index();
            $table->json('subkategori');
            $table->string('kategori', 50)->index(); // primary subcategory
            $table->json('tags')->nullable();
            $table->unsignedInteger('harga_tiket')->default(0);
            $table->unsignedSmallInteger('durasi_kunjungan_menit')->default(90);
            $table->string('jam_buka', 10)->default('08:00');
            $table->string('jam_tutup', 10)->default('18:00');
            $table->json('waktu_terbaik')->nullable(); // ['pagi', 'sore']
            $table->string('tingkat_fisik', 30)->default('sedang'); // mudah/sedang/sulit
            $table->unsignedTinyInteger('popularitas')->default(5); // 1-10
            $table->json('cocok_untuk')->nullable(); // ['solo', 'couple', 'family', 'group']
            $table->json('rekomendasi_travel_gear')->nullable();
            $table->text('tips')->nullable();
            $table->text('guide_singkat')->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('foto');
            $table->string('zona', 50)->index();
            $table->json('slot_ideal')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Header Itinerary
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tgl_berangkat');
            $table->date('tgl_pulang');
            $table->unsignedTinyInteger('durasi')->default(1);
            $table->unsignedTinyInteger('jumlah_orang')->default(2);
            $table->json('kategori');
            $table->string('tipe_traveler', 30)->default('couple');
            $table->string('tingkat_fisik', 30)->default('sedang');
            $table->string('status', 30)->default('draft')->index(); // 'draft', 'tersimpan', 'terkunci'
            $table->unsignedBigInteger('seed')->nullable();
            $table->unsignedBigInteger('seed_dasar')->nullable();
            $table->unsignedSmallInteger('versi')->default(1);
            $table->unsignedSmallInteger('jumlah_edit')->default(0);
            $table->timestamps();
        });

        // 3. Tabel Detail Item Kunjungan Itinerary
        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('itineraries')->cascadeOnDelete();
            $table->foreignId('destinasi_id')->constrained('destinasis')->restrictOnDelete();
            $table->unsignedTinyInteger('hari_ke')->default(1);
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->string('slot', 20)->default('pagi'); // pagi, siang, sore
            $table->string('slot_label', 30)->nullable();
            $table->string('jam_mulai', 10);
            $table->string('jam_selesai', 10);
            $table->unsignedSmallInteger('durasi_menit')->default(90);
            $table->unsignedSmallInteger('durasi_override_menit')->nullable();
            $table->decimal('jarak_dari_sebelumnya_km', 6, 2)->nullable();
            $table->unsignedSmallInteger('waktu_tempuh_menit')->nullable();
            $table->text('alasan')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->string('sumber', 50)->default('generator');
            $table->text('catatan_user')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itineraries');
        Schema::dropIfExists('destinasis');
    }
};
