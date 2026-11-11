<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destinasi extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subkategori' => 'array',
            'tags' => 'array',
            'waktu_terbaik' => 'array',
            'cocok_untuk' => 'array',
            'rekomendasi_travel_gear' => 'array',
            'slot_ideal' => 'array',
            'is_active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'harga_tiket' => 'integer',
            'durasi_kunjungan_menit' => 'integer',
            'popularitas' => 'integer',
        ];
    }

    /**
     * Item itinerary yang merujuk destinasi ini.
     */
    public function itineraryItems(): HasMany
    {
        return $this->hasMany(ItineraryItem::class, 'destinasi_id');
    }
}
