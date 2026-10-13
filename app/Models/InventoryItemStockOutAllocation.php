<?php

namespace App\Models;

use Database\Factories\InventoryItemStockOutAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $inventory_item_stock_out_allocation_id
 * @property int $quantity
 * @property string $unit_cost
 * @property int|null $committed_quantity Return commitment aggregate loaded by the return source query.
 */
#[Fillable(['inventory_item_stock_out_id', 'inventory_item_batch_id', 'quantity', 'unit_cost'])]
class InventoryItemStockOutAllocation extends Model
{
    /** @use HasFactory<InventoryItemStockOutAllocationFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_stock_out_allocation_id';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<InventoryItemStockOut, $this> */
    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(InventoryItemStockOut::class, 'inventory_item_stock_out_id', 'inventory_item_stock_out_id');
    }

    /** @return BelongsTo<InventoryItemBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryItemBatch::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }

    /** @return HasMany<InventoryReturnLine, $this> */
    public function returnLines(): HasMany
    {
        return $this->hasMany(InventoryReturnLine::class, 'source_allocation_id', 'inventory_item_stock_out_allocation_id');
    }
}
