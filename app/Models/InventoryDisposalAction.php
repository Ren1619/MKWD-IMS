<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $evidence */
#[Fillable(['inventory_disposal_id', 'actor_user_id', 'actor_name', 'action', 'from_status', 'to_status', 'attestation', 'evidence'])]
class InventoryDisposalAction extends Model
{
    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Disposal evidence is immutable.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Disposal evidence cannot be deleted.');
        });
    }
}
