<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $inventory_return_id
 * @property int $inventory_item_id
 * @property int|null $source_allocation_id
 * @property int|null $source_supply_request_id
 * @property string|null $source_issue_reference
 * @property string $stock_number
 * @property string $item_name
 * @property string $unit_of_measure
 * @property int $quantity_declared
 * @property int|null $quantity_accepted
 * @property int $quantity_committed
 * @property string $unit_cost
 * @property CarbonInterface|null $expiration_date
 * @property string|null $valuation_basis
 * @property string|null $condition
 * @property string|null $storage_location
 * @property string|null $inspection_remarks
 */
#[Fillable(['inventory_return_id', 'inventory_item_id', 'source_allocation_id', 'source_supply_request_id', 'source_issue_reference', 'stock_number', 'item_name', 'unit_of_measure', 'quantity_declared', 'quantity_accepted', 'quantity_committed', 'unit_cost', 'expiration_date', 'valuation_basis', 'condition', 'storage_location', 'inspection_remarks'])]
class InventoryReturnLine extends Model
{
    protected function casts(): array
    {
        return ['quantity_declared' => 'integer', 'quantity_accepted' => 'integer', 'quantity_committed' => 'integer', 'unit_cost' => 'decimal:2', 'expiration_date' => 'date'];
    }

    /** @return BelongsTo<InventoryReturn, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryReturn::class, 'inventory_return_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryItemStockOutAllocation, $this> */
    public function sourceAllocation(): BelongsTo
    {
        return $this->belongsTo(InventoryItemStockOutAllocation::class, 'source_allocation_id', 'inventory_item_stock_out_allocation_id');
    }

    /** @return HasOne<InventoryReturnAllocation, $this> */
    public function posting(): HasOne
    {
        return $this->hasOne(InventoryReturnAllocation::class);
    }
}
