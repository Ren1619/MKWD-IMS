<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $inventory_item_id
 * @property int $quantity
 * @property int $reorder_point
 * @property int|null $reorder_quantity
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
    'reorder_point',
    'reorder_quantity',
    'status',
])]
class InventoryItem extends Model
{
    public const EXPIRATION_WARNING_DAYS = 30;

    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'inventory_item_id';

    protected $attributes = [
        'quantity' => 0,
        'reorder_point' => 10,
        'status' => 'active',
        'unit_of_measure' => 'pc',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reorder_point' => 'integer',
            'reorder_quantity' => 'integer',
        ];
    }

    protected $appends = ['inventory_value', 'next_expiration_date', 'is_low_stock', 'expiration_status'];

    public function getIsLowStockAttribute(): bool
    {
        return $this->status === 'active' && $this->quantity <= $this->reorder_point;
    }

    public function getExpirationStatusAttribute(): ?string
    {
        if ($this->next_expiration_date === null) {
            return null;
        }

        $expirationDate = CarbonImmutable::parse($this->next_expiration_date)->startOfDay();
        $today = CarbonImmutable::today();

        if ($expirationDate->isBefore($today)) {
            return 'expired';
        }

        if ($expirationDate->lessThanOrEqualTo($today->addDays(self::EXPIRATION_WARNING_DAYS))) {
            return 'expiring';
        }

        return 'current';
    }

    public function getInventoryValueAttribute(): string
    {
        $value = $this->relationLoaded('batches')
            ? $this->batches->sum(fn (InventoryItemBatch $batch): float => $batch->quantity_remaining * (float) $batch->unit_cost)
            : (float) $this->batches()->selectRaw('COALESCE(SUM(quantity_remaining * unit_cost), 0) AS value')->value('value');

        return number_format($value, 2, '.', '');
    }

    public function getNextExpirationDateAttribute(): ?string
    {
        if ($this->relationLoaded('batches')) {
            return $this->batches
                ->where('quantity_remaining', '>', 0)
                ->filter(fn (InventoryItemBatch $batch): bool => $batch->expiration_date !== null)
                ->min(fn (InventoryItemBatch $batch): string => $batch->expiration_date->toDateString());
        }

        return $this->batches()
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiration_date')
            ->min('expiration_date');
    }

    /**
     * @param  Builder<InventoryItem>  $query
     * @return Builder<InventoryItem>
     */
    #[Scope]
    protected function lowStock(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereColumn('quantity', '<=', 'reorder_point');
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
