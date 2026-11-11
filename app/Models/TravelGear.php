<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelGear extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'harga' => 'integer',
            'deposit' => 'integer',
            'stok_total' => 'integer',
            'varian' => 'array',
            'tags_aktivitas' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GearCategory::class, 'gear_category_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(GearReservation::class, 'gear_id');
    }

    public function pesananItems(): HasMany
    {
        return $this->hasMany(PesananItem::class, 'gear_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Hitung sisa stok tersedia untuk rentang tanggal tertentu.
     */
    public function stokTersedia(string $tglMulai, string $tglSelesai): int
    {
        $bookedQty = (int) $this->reservations()
            ->whereIn('status', ['ditahan', 'dikonfirmasi'])
            ->where('tanggal_mulai', '<=', $tglSelesai)
            ->where('tanggal_selesai', '>=', $tglMulai)
            ->sum('qty');

        return max(0, $this->stok_total - $bookedQty);
    }
}
