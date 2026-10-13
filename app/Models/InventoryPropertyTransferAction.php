<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $evidence */
#[Fillable(['inventory_property_transfer_id', 'actor_user_id', 'actor_name', 'action', 'from_status', 'to_status', 'attestation', 'evidence'])]
class InventoryPropertyTransferAction extends Model
{
    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Transfer decisions are immutable.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Transfer decisions cannot be deleted.');
        });
    }
}
