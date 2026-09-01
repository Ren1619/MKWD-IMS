<?php

namespace App\Models;

use Database\Factories\InventoryItemStockOutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'inventory_item_id',
    'recipient_reference_id',
    'recipient_name',
    'ris_no',
    'responsibility_center_code',
    'quantity',
    'stocked_out_at',
    'notes',
    'supply_request_line_id',
])]
class InventoryItemStockOut extends Model
{
    /** @use HasFactory<InventoryItemStockOutFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_stock_out_id';

    protected $appends = ['total_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stocked_out_at' => 'date',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function recipientReference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'recipient_reference_id');
    }

    /** @return HasMany<InventoryItemStockOutAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryItemStockOutAllocation::class, 'inventory_item_stock_out_id', 'inventory_item_stock_out_id');
    }

    /** @return BelongsTo<SupplyRequestLine, $this> */
    public function supplyRequestLine(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestLine::class);
    }

    public function getTotalCostAttribute(): string
    {
        $value = $this->relationLoaded('allocations')
            ? $this->allocations->sum(fn (InventoryItemStockOutAllocation $allocation): float => $allocation->quantity * (float) $allocation->unit_cost)
            : (float) $this->allocations()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS value')->value('value');

        return number_format($value, 2, '.', '');
    }
}
