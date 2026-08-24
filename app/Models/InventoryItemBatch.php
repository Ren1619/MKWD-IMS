<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $quantity_in
 * @property int $quantity_remaining
 * @property string $unit_cost
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $expiration_date
 */
#[Fillable([
    'inventory_item_id',
    'batch_number',
    'quantity_in',
    'quantity_remaining',
    'unit_cost',
    'received_at',
    'expiration_date',
    'source',
    'reference_no',
    'notes',
])]
class InventoryItemBatch extends Model
{
    /** @use HasFactory<InventoryItemBatchFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_batch_id';

    protected function casts(): array
    {
        return [
            'quantity_in' => 'integer',
            'quantity_remaining' => 'integer',
            'unit_cost' => 'decimal:2',
            'received_at' => 'date',
            'expiration_date' => 'date',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return HasMany<InventoryItemStockOutAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryItemStockOutAllocation::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }
}
