<?php

namespace App\Models;

use Database\Factories\InventoryCountLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $inventory_count_session_id
 * @property int $inventory_item_id
 * @property string|null $tag_no
 * @property string|null $stock_number
 * @property string|null $item_name
 * @property string|null $unit_of_measure
 * @property int $book_quantity
 * @property string|null $unit_cost
 * @property int $before_count_issues
 * @property int $after_count_issues
 * @property int $segregated_receipts
 * @property string|null $adjustment_unit_cost
 * @property string|null $valuation_basis
 */
#[Fillable(['inventory_count_session_id', 'inventory_item_id', 'tag_no', 'stock_number', 'item_name', 'unit_of_measure', 'book_quantity', 'unit_cost', 'before_count_issues', 'after_count_issues', 'segregated_receipts', 'adjustment_unit_cost', 'valuation_basis'])]
class InventoryCountLine extends Model
{
    /** @use HasFactory<InventoryCountLineFactory> */
    use HasFactory;

    protected $attributes = ['before_count_issues' => 0, 'after_count_issues' => 0, 'segregated_receipts' => 0];

    protected function casts(): array
    {
        return ['book_quantity' => 'integer', 'unit_cost' => 'decimal:2', 'before_count_issues' => 'integer', 'after_count_issues' => 'integer', 'segregated_receipts' => 'integer', 'adjustment_unit_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<InventoryCountSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'inventory_count_session_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return HasMany<InventoryCountEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(InventoryCountEntry::class);
    }

    /** @return HasMany<InventoryCountAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryCountAdjustment::class);
    }

    /** @return HasOne<InventoryCountEntry, $this> */
    public function latestEntry(): HasOne
    {
        return $this->hasOne(InventoryCountEntry::class)->latestOfMany();
    }
}
