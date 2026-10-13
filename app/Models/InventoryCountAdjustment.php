<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_count_line_id
 * @property int $inventory_item_batch_id
 * @property int $quantity
 * @property string|null $unit_cost
 */
#[Fillable(['inventory_count_line_id', 'inventory_item_batch_id', 'quantity', 'unit_cost'])]
class InventoryCountAdjustment extends Model
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
        return ['quantity' => 'integer', 'unit_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<InventoryCountLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InventoryCountLine::class, 'inventory_count_line_id');
    }
}
