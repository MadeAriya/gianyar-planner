<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\GuideDashboardController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\ItineraryEditController;
use App\Http\Controllers\MitraDashboardController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\PreferensiController;
use Illuminate\Support\Facades\Route;

// Landing page
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Auth (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.process');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.process');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Halaman Status Pengajuan (Khusus Akun Pending / Rejected yang sudah login)
Route::middleware('auth.check')->group(function () {
    Route::get('/status-pengajuan', [AuthController::class, 'statusPengajuan'])->name('status.pengajuan');
    Route::post('/status-pengajuan/ajukan-ulang', [AuthController::class, 'ajukanUlang'])->name('status.ajukan_ulang');

    // Akses Dokumen Privat Terproteksi (KTP, Sertifikat, Bukti Usaha)
    Route::get('/dokumen/{type}/{id}/{field}', [DokumenController::class, 'show'])->name('dokumen.show');
});

// Wisatawan / User Routes (Aktif)
Route::middleware(['auth.check', 'role:user', 'active.account'])->group(function () {
    Route::get('/preferensi', [PreferensiController::class, 'create'])->name('preferensi.create');
    Route::post('/preferensi', [PreferensiController::class, 'store'])->name('preferensi.store');

    Route::get('/itinerary', [ItineraryController::class, 'show'])->name('itinerary.show');
    Route::post('/itinerary/regenerate', [ItineraryController::class, 'regenerate'])->name('itinerary.regenerate');
    Route::post('/itinerary/simpan', [ItineraryController::class, 'save'])->name('itinerary.save');
    Route::post('/itinerary/addons', [ItineraryController::class, 'saveAddons'])->name('itinerary.addons');

    Route::get('/pesanan/ringkasan', [PesananController::class, 'ringkasan'])->name('pesanan.ringkasan');
    Route::post('/pesanan', [PesananController::class, 'store'])->name('pesanan.store');
    Route::get('/pesanan', [PesananController::class, 'index'])->name('pesanan.index');

    // Itinerary Edit RESTful Endpoints (Smart Swap, Lock, Reorder, Undo, Redo, Blacklist)
    Route::prefix('itineraries/{id}')->name('itineraries.')->group(function () {
        Route::post('/items/{itemId}/replace-suggestions', [ItineraryEditController::class, 'replaceSuggestions'])->name('replace-suggestions');
        Route::patch('/items/{itemId}/replace', [ItineraryEditController::class, 'replace'])->name('replace');
        Route::delete('/items/{itemId}', [ItineraryEditController::class, 'destroy'])->name('items.destroy');
        Route::post('/items', [ItineraryEditController::class, 'store'])->name('items.store');
        Route::patch('/items/reorder', [ItineraryEditController::class, 'reorder'])->name('items.reorder');
        Route::patch('/items/{itemId}/lock', [ItineraryEditController::class, 'lock'])->name('items.lock');
        Route::patch('/items/{itemId}/duration', [ItineraryEditController::class, 'updateDuration'])->name('items.duration');
        Route::post('/regenerate-partial', [ItineraryEditController::class, 'regenerate'])->name('regenerate-partial');
        Route::post('/undo', [ItineraryEditController::class, 'undo'])->name('undo');
        Route::post('/redo', [ItineraryEditController::class, 'redo'])->name('redo');
        Route::post('/reset', [ItineraryEditController::class, 'reset'])->name('reset');
        Route::get('/exclusions', [ItineraryEditController::class, 'getExclusions'])->name('exclusions.index');
        Route::delete('/exclusions/{destinasiId}', [ItineraryEditController::class, 'removeExclusion'])->name('exclusions.destroy');
        Route::post('/duplicate', [ItineraryEditController::class, 'duplicate'])->name('duplicate');
        Route::get('/catalog', [ItineraryEditController::class, 'searchCatalog'])->name('catalog.search');
    });
});

// Tour Guide Routes (Aktif)
Route::middleware(['auth.check', 'role:tour_guide', 'active.account'])->prefix('guide')->name('guide.')->group(function () {
    Route::get('/dashboard', [GuideDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/kalender', [GuideDashboardController::class, 'kalender'])->name('kalender');
    Route::post('/kalender/toggle', [GuideDashboardController::class, 'toggleTanggal'])->name('kalender.toggle');
    Route::get('/bookings', [GuideDashboardController::class, 'bookings'])->name('bookings');
    Route::post('/bookings/{id}/respond', [GuideDashboardController::class, 'respondBooking'])->name('bookings.respond');
});

// Mitra Penyedia Gear Routes (Aktif)
Route::middleware(['auth.check', 'role:mitra', 'active.account'])->prefix('mitra')->name('mitra.')->group(function () {
    Route::get('/dashboard', [MitraDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/gears', [MitraDashboardController::class, 'gears'])->name('gears.index');
    Route::get('/gears/create', [MitraDashboardController::class, 'createGear'])->name('gears.create');
    Route::post('/gears', [MitraDashboardController::class, 'storeGear'])->name('gears.store');
    Route::get('/gears/{id}/edit', [MitraDashboardController::class, 'editGear'])->name('gears.edit');
    Route::put('/gears/{id}', [MitraDashboardController::class, 'updateGear'])->name('gears.update');
    Route::delete('/gears/{id}', [MitraDashboardController::class, 'destroyGear'])->name('gears.destroy');
    Route::get('/orders', [MitraDashboardController::class, 'orders'])->name('orders');
    Route::post('/orders/{id}/respond', [MitraDashboardController::class, 'respondOrder'])->name('orders.respond');
});

// Super Admin Backoffice Routes
Route::middleware(['auth.check', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Verifikasi Tour Guide
    Route::get('/guides', [AdminController::class, 'guides'])->name('guides.index');
    Route::get('/guides/{id}', [AdminController::class, 'guideDetail'])->name('guides.show');
    Route::post('/guides/{id}/verify', [AdminController::class, 'verifyGuide'])->name('guides.verify');

    // Verifikasi Mitra Gear
    Route::get('/mitras', [AdminController::class, 'mitras'])->name('mitras.index');
    Route::get('/mitras/{id}', [AdminController::class, 'mitraDetail'])->name('mitras.show');
    Route::post('/mitras/{id}/verify', [AdminController::class, 'verifyMitra'])->name('mitras.verify');

    // Manajemen Pengguna
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('users.toggle');

    // Verifikasi Pembayaran
    Route::get('/pembayaran', [AdminController::class, 'pembayaran'])->name('pembayaran.index');
    Route::post('/pembayaran/{id}/verify', [AdminController::class, 'verifikasiPembayaran'])->name('pembayaran.verify');

    // Audit Trail
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit.index');

    // Backward compat link
    Route::get('/verifikasi-mitra', fn () => redirect()->route('admin.mitras.index'))->name('mitra');
});
