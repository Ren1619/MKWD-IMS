<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function canIssueOn(string $date): bool
    {
        $today = now('Asia/Manila')->toDateString();

        return $this->quantity_remaining > 0
            && $this->received_at->toDateString() <= min($date, $today)
            && ($this->expiration_date === null || $this->expiration_date->toDateString() >= max($date, $today));
    }

    /** @param Builder<InventoryItemBatch> $query */
    #[Scope]
    protected function eligibleForIssue(Builder $query, string $date): void
    {
        $today = now('Asia/Manila')->toDateString();
        $query->where('quantity_remaining', '>', 0)
            ->whereDate('received_at', '<=', min($date, $today))
            ->where(fn ($expiry) => $expiry->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', max($date, $today)));
    }

    /** @return HasMany<InventoryBatchPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(InventoryBatchPlacement::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }

    /** @return HasOne<InventoryBatchPlacement, $this> */
    public function latestPlacement(): HasOne
    {
        return $this->hasOne(InventoryBatchPlacement::class, 'inventory_item_batch_id', 'inventory_item_batch_id')->latestOfMany();
    }

    /** @return HasOne<ProcurementDeliveryLine, $this> */
    public function deliveryLine(): HasOne
    {
        return $this->hasOne(ProcurementDeliveryLine::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }

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

    /** @return HasMany<InventoryCountAdjustment, $this> */
    public function countAdjustments(): HasMany
    {
        return $this->hasMany(InventoryCountAdjustment::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }

    /** @return HasMany<InventoryReturnAllocation, $this> */
    public function returnPostings(): HasMany
    {
        return $this->hasMany(InventoryReturnAllocation::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }
}
