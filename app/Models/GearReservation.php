<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GearReservation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'qty' => 'integer',
        ];
    }

    public function gear(): BelongsTo
    {
        return $this->belongsTo(TravelGear::class, 'gear_id');
    }

    public function pesananItem(): BelongsTo
    {
        return $this->belongsTo(PesananItem::class, 'pesanan_item_id');
    }
}
