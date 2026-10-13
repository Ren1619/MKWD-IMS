<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_count_session_id
 * @property int $actor_user_id
 * @property string $actor_name
 * @property string|null $idempotency_key
 * @property string|null $action
 * @property string|null $remarks
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['inventory_count_session_id', 'actor_user_id', 'actor_name', 'action', 'remarks', 'metadata', 'idempotency_key'])]
class InventoryCountAction extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Physical count evidence is immutable. Record a new observation or action.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Physical count evidence cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
