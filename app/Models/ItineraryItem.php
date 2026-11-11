<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hari_ke' => 'integer',
            'urutan' => 'integer',
            'durasi_menit' => 'integer',
            'durasi_override_menit' => 'integer',
            'jarak_dari_sebelumnya_km' => 'float',
            'waktu_tempuh_menit' => 'integer',
            'is_locked' => 'boolean',
        ];
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function destinasi(): BelongsTo
    {
        return $this->belongsTo(Destinasi::class);
    }
}
