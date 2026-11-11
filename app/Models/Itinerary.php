<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Itinerary extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tgl_berangkat' => 'date',
            'tgl_pulang' => 'date',
            'kategori' => 'array',
            'seed' => 'integer',
            'seed_dasar' => 'integer',
            'versi' => 'integer',
            'jumlah_edit' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('hari_ke')->orderBy('urutan');
    }

    public function pesanan(): HasOne
    {
        return $this->hasOne(Pesanan::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'terkunci';
    }
}
