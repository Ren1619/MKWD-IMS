<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_return_line_id
 * @property int $inventory_item_batch_id
 * @property int $quantity
 * @property string $unit_cost
 */
#[Fillable(['inventory_return_line_id', 'inventory_item_batch_id', 'quantity', 'unit_cost'])]
class InventoryReturnAllocation extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'decimal:2'];
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

    /** @return BelongsTo<InventoryReturnLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InventoryReturnLine::class, 'inventory_return_line_id');
    }

    /** @return BelongsTo<InventoryItemBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryItemBatch::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }
}
