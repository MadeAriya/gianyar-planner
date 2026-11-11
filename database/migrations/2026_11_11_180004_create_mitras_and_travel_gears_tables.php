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
        // 1. Profil Mitra Penyedia Gear (1:1 dengan User role mitra)
        Schema::create('mitras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nama_usaha');
            $table->text('alamat');
            $table->string('kecamatan');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('jam_operasional', 100)->nullable();
            $table->string('metode_serah', 30)->default('keduanya'); // ambil_di_tempat|antar_ke_hotel|keduanya
            $table->text('foto_ktp')->nullable(); // Disimpan di storage privat
            $table->text('bukti_usaha')->nullable(); // Disimpan di storage privat
            $table->string('status_verifikasi', 30)->default('pending')->index(); // pending|approved|rejected|suspended
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->timestamps();
        });

        // 2. Master Kategori Gear
        Schema::create('gear_categories', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 100);
            $table->string('sifat', 30)->default('per_orang'); // per_orang|per_kelompok
            $table->unsignedTinyInteger('kelompok_per_unit')->default(1);
            $table->string('ikon', 50)->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // 3. Barang Travel Gear Milik Mitra
        Schema::create('travel_gears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitras')->cascadeOnDelete();
            $table->foreignId('gear_category_id')->constrained('gear_categories')->cascadeOnDelete();
            $table->string('nama_barang');
            $table->text('deskripsi')->nullable();
            $table->string('tipe', 20)->default('sewa'); // sewa|beli
            $table->unsignedInteger('harga'); // per hari untuk sewa, per unit untuk beli
            $table->unsignedInteger('deposit')->default(0);
            $table->unsignedInteger('stok_total')->default(0);
            $table->json('varian')->nullable(); // ['S', 'M', 'L']
            $table->json('tags_aktivitas'); // ['air_terjun', 'trekking', 'pura', 'pantai']
            $table->text('foto')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Reservasi Stok Gear per Rentang Tanggal
        Schema::create('gear_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gear_id')->constrained('travel_gears')->cascadeOnDelete();
            $table->unsignedBigInteger('pesanan_item_id')->nullable()->index();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedInteger('qty');
            $table->string('status', 30)->default('ditahan')->index(); // ditahan|dikonfirmasi|dibatalkan|selesai
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gear_reservations');
        Schema::dropIfExists('travel_gears');
        Schema::dropIfExists('gear_categories');
        Schema::dropIfExists('mitras');
    }
};
