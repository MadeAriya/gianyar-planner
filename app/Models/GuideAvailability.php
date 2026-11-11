<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideAvailability extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(TourGuide::class, 'guide_id');
    }

    public function scopeTersedia(Builder $query): Builder
    {
        return $query->where('status', 'tersedia');
    }
}
