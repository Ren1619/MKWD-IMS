<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $inventory_item_id
 * @property int $quantity
 * @property string $status
 */
#[Fillable([
    'series_category_id',
    'accountable_reference_id',
    'name',
    'stock_number',
    'unit_of_measure',
    'uacs_object_code',
    'description',
    'quantity',
    'price',
    'expiration_date',
    'status',
])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_id';

    protected $attributes = [
        'quantity' => 0,
        'price' => 0,
        'status' => 'active',
        'unit_of_measure' => 'pc',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'expiration_date' => 'date',
        ];
    }

    /** @return BelongsTo<InventorySeriesCategory, $this> */
    public function seriesCategory(): BelongsTo
    {
        return $this->belongsTo(InventorySeriesCategory::class, 'series_category_id', 'inv_series_cat_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function accountableReference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'accountable_reference_id');
    }

    /** @return HasMany<InventoryItemBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryItemBatch::class, 'inventory_item_id', 'inventory_item_id')
            ->orderBy('batch_number');
    }

    /** @return HasMany<InventoryItemStockOut, $this> */
    public function stockOuts(): HasMany
    {
        return $this->hasMany(InventoryItemStockOut::class, 'inventory_item_id', 'inventory_item_id')
            ->latest('stocked_out_at');
    }
}
