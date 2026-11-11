<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Mutator & Accessor untuk sinkronisasi kolom `nama` dan `name`.
     */
    protected function nama(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->attributes['name'] ?? null,
            set: function (?string $value) {
                $this->attributes['name'] = $value;

                return $value;
            }
        );
    }

    /**
     * Profil Tour Guide (1:1 jika role tour_guide).
     */
    public function tourGuide(): HasOne
    {
        return $this->hasOne(TourGuide::class);
    }

    /**
     * Profil Mitra Penyedia Gear (1:1 jika role mitra).
     */
    public function mitra(): HasOne
    {
        return $this->hasOne(Mitra::class);
    }

    /**
     * Riwayat itinerary milik user.
     */
    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    /**
     * Riwayat pesanan tiket / paket wisata.
     */
    public function pesanans(): HasMany
    {
        return $this->hasMany(Pesanan::class);
    }

    /**
     * Notifikasi in-app untuk user.
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Role Helper Methods
    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isTourGuide(): bool
    {
        return $this->role === 'tour_guide';
    }

    public function isMitra(): bool
    {
        return in_array($this->role, ['mitra', 'penyedia_gear']);
    }

    public function isWisatawan(): bool
    {
        return in_array($this->role, ['user', 'wisatawan']);
    }

    // Status Helper Methods
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'aktif']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'belum_lengkap']);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, ['rejected', 'ditolak']);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
