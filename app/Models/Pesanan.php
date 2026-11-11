<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pesanan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tgl_pesan' => 'date',
            'total_bayar' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function pesananGuide(): HasOne
    {
        return $this->hasOne(PesananGuide::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class);
    }

    public function isLunas(): bool
    {
        return $this->status_pembayaran === 'lunas';
    }

    public function isMenungguVerifikasi(): bool
    {
        return $this->status_pembayaran === 'menunggu_verifikasi';
    }
}
