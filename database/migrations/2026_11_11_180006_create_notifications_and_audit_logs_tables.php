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
        // 1. Notifikasi In-App untuk Semua Peran
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipe', 50)->index(); // booking_guide|verifikasi_akun|pesanan_gear|pembayaran
            $table->string('judul');
            $table->text('isi');
            $table->json('data')->nullable(); // detail referensi / link target
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        // 2. Audit Trail Log untuk Keputusan Super Admin & Perubahan Kritis
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 50)->index(); // approve|reject|suspend|verify_payment|cancel_booking
            $table->string('target_type', 100)->index(); // TourGuide|Mitra|Pesanan|User
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('meta')->nullable(); // alasan penolakan, snapshot data lama/baru, IP address
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
