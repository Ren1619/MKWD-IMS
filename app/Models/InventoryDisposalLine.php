<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $source_snapshot
 * @property string|null $proceeds
 */
#[Fillable(['inventory_disposal_id', 'inventory_asset_id', 'quantity', 'unit', 'description', 'source_reference', 'source_snapshot', 'source_hash', 'or_number', 'proceeds', 'disposal_event_id'])]
class InventoryDisposalLine extends Model
{
    /** @return BelongsTo<InventoryDisposal, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDisposal::class, 'inventory_disposal_id');
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'quantity' => 'integer', 'proceeds' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            if ($line->getOriginal('proceeds') !== null || $line->isDirty(['inventory_disposal_id', 'inventory_asset_id', 'quantity', 'unit', 'description', 'source_reference', 'source_snapshot', 'source_hash'])) {
                throw new \LogicException('Disposal lines are frozen and completion may be recorded only once.');
            }
        });
        static::deleting(function (): never {
            throw new \LogicException('Disposal lines cannot be deleted.');
        });
    }
}
