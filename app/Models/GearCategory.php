<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GearCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kelompok_per_unit' => 'integer',
        ];
    }

    public function gears(): HasMany
    {
        return $this->hasMany(TravelGear::class, 'gear_category_id');
    }
}
