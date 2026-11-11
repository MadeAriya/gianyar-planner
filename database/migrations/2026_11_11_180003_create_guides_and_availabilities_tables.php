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
        // 1. Profil Tour Guide (1:1 dengan User role tour_guide)
        Schema::create('tour_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->json('bahasa'); // ['ID', 'EN', 'JA']
            $table->json('spesialisasi'); // ['air_terjun', 'pura_candi', ...]
            $table->string('area_layanan'); // kecamatan atau wilayah operasional
            $table->unsignedInteger('tarif_per_hari');
            $table->unsignedTinyInteger('max_peserta')->default(10);
            $table->unsignedTinyInteger('pengalaman_tahun')->default(1);
            $table->string('nomor_lisensi', 100)->nullable();
            $table->text('foto')->nullable();
            $table->text('foto_ktp')->nullable(); // Disimpan di storage privat
            $table->text('sertifikat_lisensi')->nullable(); // Disimpan di storage privat
            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->unsignedInteger('jumlah_ulasan')->default(0);
            $table->unsignedInteger('jumlah_job_selesai')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100.00); // persentase menerima pesanan
            $table->string('status_verifikasi', 30)->default('pending')->index(); // pending|approved|rejected|suspended
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 2. Ketersediaan Tanggal Guide (Sumber Kebenaran Anti Double-Booking)
        Schema::create('guide_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('tour_guides')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status', 30)->default('tersedia')->index(); // tersedia|diblokir_guide|ditahan|terpesan
            $table->string('booking_ref', 50)->nullable();
            $table->timestamps();

            $table->unique(['guide_id', 'tanggal'], 'unique_guide_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guide_availabilities');
        Schema::dropIfExists('tour_guides');
    }
};
