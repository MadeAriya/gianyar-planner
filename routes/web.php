<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\PreferensiController;
use Illuminate\Support\Facades\Route;

// Landing page
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Auth (guest)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Mitra profile (auth-only)
Route::middleware('auth.check')->group(function () {
    Route::get('/mitra/lengkapi-profil', [AuthController::class, 'showMitraProfile'])->name('mitra.profil');
    Route::post('/mitra/lengkapi-profil', [AuthController::class, 'saveMitraProfile'])->name('mitra.profil.save');
});

// Wisatawan routes
Route::middleware(['auth.check', 'role:wisatawan'])->group(function () {
    Route::get('/preferensi', [PreferensiController::class, 'create'])->name('preferensi.create');
    Route::post('/preferensi', [PreferensiController::class, 'store'])->name('preferensi.store');

    Route::get('/itinerary', [ItineraryController::class, 'show'])->name('itinerary.show');
    Route::post('/itinerary/regenerate', [ItineraryController::class, 'regenerate'])->name('itinerary.regenerate');
    Route::post('/itinerary/simpan', [ItineraryController::class, 'save'])->name('itinerary.save');
    Route::post('/itinerary/addons', [ItineraryController::class, 'saveAddons'])->name('itinerary.addons');

    Route::get('/pesanan/ringkasan', [PesananController::class, 'ringkasan'])->name('pesanan.ringkasan');
    Route::post('/pesanan', [PesananController::class, 'store'])->name('pesanan.store');
    Route::get('/pesanan', [PesananController::class, 'index'])->name('pesanan.index');
});

// Mitra dashboard
Route::middleware(['auth.check', 'role:tour_guide,penyedia_gear'])->group(function () {
    Route::get('/mitra/dashboard', [MitraController::class, 'dashboard'])->name('mitra.dashboard');
});

// Admin
Route::middleware(['auth.check', 'role:admin'])->group(function () {
    Route::get('/admin/verifikasi-mitra', [MitraController::class, 'adminIndex'])->name('admin.mitra');
    Route::post('/admin/verifikasi-mitra/{email}', [MitraController::class, 'adminVerify'])->name('admin.mitra.verify');
});
