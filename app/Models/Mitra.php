<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mitra extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'rating_avg' => 'float',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function gears(): HasMany
    {
        return $this->hasMany(TravelGear::class, 'mitra_id');
    }

    public function pesananItems(): HasMany
    {
        return $this->hasMany(PesananItem::class, 'mitra_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status_verifikasi', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'));
    }

    public function isApproved(): bool
    {
        return $this->status_verifikasi === 'approved';
    }
}
