<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourGuide extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bahasa' => 'array',
            'spesialisasi' => 'array',
            'tarif_per_hari' => 'integer',
            'max_peserta' => 'integer',
            'pengalaman_tahun' => 'integer',
            'rating_avg' => 'float',
            'jumlah_ulasan' => 'integer',
            'jumlah_job_selesai' => 'integer',
            'acceptance_rate' => 'float',
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

    public function availabilities(): HasMany
    {
        return $this->hasMany(GuideAvailability::class, 'guide_id');
    }

    public function pesananGuides(): HasMany
    {
        return $this->hasMany(PesananGuide::class, 'guide_id');
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
