<?php

namespace App\Models;

use Database\Factories\SupplyRequestLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supply_request_id', 'inventory_item_id', 'is_new_item', 'item_name', 'specifications', 'unit_of_measure', 'quantity_requested', 'quantity_approved', 'quantity_reserved', 'quantity_released', 'estimated_unit_cost', 'justification', 'planning_classification'])]
class SupplyRequestLine extends Model
{
    /** @use HasFactory<SupplyRequestLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_new_item' => 'boolean', 'estimated_unit_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<SupplyRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return HasMany<InventoryItemStockOut, $this> */
    public function stockOuts(): HasMany
    {
        return $this->hasMany(InventoryItemStockOut::class);
    }
}
