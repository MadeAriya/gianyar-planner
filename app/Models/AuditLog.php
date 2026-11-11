<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Catat aksi audit secara terstruktur.
     */
    public static function record(?int $actorId, string $aksi, string $targetType, ?int $targetId = null, array $meta = []): self
    {
        return self::create([
            'actor_id' => $actorId,
            'aksi' => $aksi,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
