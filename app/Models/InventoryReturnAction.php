<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $inventory_return_id
 * @property int $actor_user_id
 * @property string $actor_name
 * @property string $action
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $remarks
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['inventory_return_id', 'actor_user_id', 'actor_name', 'action', 'from_status', 'to_status', 'remarks', 'metadata'])]
class InventoryReturnAction extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Return evidence is immutable. Record a new action.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Return evidence cannot be deleted.');
        });
    }
}
