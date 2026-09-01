<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['procurement_request_id', 'supply_request_line_id', 'inventory_item_id', 'series_category_id', 'item_name', 'specifications', 'unit_of_measure', 'quantity', 'estimated_unit_cost', 'quantity_received', 'actual_unit_cost', 'received_at', 'delivery_reference'])]
class ProcurementRequestLine extends Model
{
    protected function casts(): array
    {
        return ['estimated_unit_cost' => 'decimal:2', 'actual_unit_cost' => 'decimal:2', 'received_at' => 'date'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }
}
