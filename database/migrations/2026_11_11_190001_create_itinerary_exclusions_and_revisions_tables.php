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
        // 1. Blacklist Destinasi per Itinerary
        Schema::create('itinerary_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('itineraries')->cascadeOnDelete();
            $table->foreignId('destinasi_id')->constrained('destinasis')->cascadeOnDelete();
            $table->string('alasan', 50)->default('lainnya'); // terlalu_jauh|terlalu_mahal|terlalu_berat|sudah_pernah|kurang_menarik|lainnya
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['itinerary_id', 'destinasi_id'], 'unique_itin_dest_exclusion');
        });

        // 2. Snapshot Riwayat Revisi untuk Undo/Redo & Audit Trail
        Schema::create('itinerary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('itineraries')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->json('snapshot'); // Seluruh items & metadata jadwal pada versi tersebut
            $table->string('aksi', 50); // generate|ganti|hapus|tambah|pindah|kunci|regenerate_sebagian|reset|durasi|jam_mulai
            $table->string('ringkasan_perubahan', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['itinerary_id', 'versi'], 'idx_itin_revision_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_revisions');
        Schema::dropIfExists('itinerary_exclusions');
    }
};
