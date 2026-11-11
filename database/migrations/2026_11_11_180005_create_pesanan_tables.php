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
        // 1. Header Pesanan (Menghubungkan Wisatawan, Itinerary, Guide & Gear)
        Schema::create('pesanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('itinerary_id')->nullable()->constrained('itineraries')->nullOnDelete();
            $table->string('kode_tiket', 50)->nullable()->unique();
            $table->date('tgl_pesan');
            $table->unsignedInteger('total_bayar')->default(0);
            $table->string('status_pembayaran', 30)->default('belum_bayar')->index(); // belum_bayar|menunggu_verifikasi|lunas|ditolak|refund
            $table->text('bukti_upload')->nullable();
            $table->string('status_pesanan', 40)->default('menunggu_konfirmasi')->index(); // menunggu_konfirmasi|dikonfirmasi|menunggu_pembayaran|terkonfirmasi|berlangsung|selesai|dibatalkan
            $table->timestamp('expires_at')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
        });

        // 2. Pemesanan Tour Guide (State Machine Guide)
        Schema::create('pesanan_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('tour_guides')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedInteger('tarif_snapshot');
            $table->string('status', 40)->default('dipilih')->index(); // dipilih|menunggu_konfirmasi_guide|dikonfirmasi_guide|terkonfirmasi|berlangsung|selesai|ditolak|kedaluwarsa|dibatalkan_user|dibatalkan_guide
            $table->text('alasan_penolakan')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 3. Pemesanan Item Gear (State Machine Gear per Mitra)
        Schema::create('pesanan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanans')->cascadeOnDelete();
            $table->foreignId('gear_id')->constrained('travel_gears')->restrictOnDelete();
            $table->foreignId('mitra_id')->constrained('mitras')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('harga_snapshot');
            $table->unsignedTinyInteger('jumlah_hari')->default(1);
            $table->string('status', 40)->default('menunggu_konfirmasi_mitra')->index(); // menunggu_konfirmasi_mitra|dikonfirmasi_mitra|siap_diambil|dipakai|dikembalikan|selesai|ditolak|kedaluwarsa|dibatalkan
            $table->string('metode_serah', 30)->default('ambil_di_tempat'); // ambil_di_tempat|antar_ke_hotel
            $table->text('alamat_antar')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 4. Tambah relasi foreign key dari gear_reservations ke pesanan_items
        Schema::table('gear_reservations', function (Blueprint $table) {
            $table->foreign('pesanan_item_id')->references('id')->on('pesanan_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gear_reservations', function (Blueprint $table) {
            $table->dropForeign(['pesanan_item_id']);
        });

        Schema::dropIfExists('pesanan_items');
        Schema::dropIfExists('pesanan_guides');
        Schema::dropIfExists('pesanans');
    }
};
