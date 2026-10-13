<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $supply_request_line_id
 * @property int|null $inventory_item_id
 * @property int|null $series_category_id
 * @property int|null $asset_category_id
 * @property int|null $asset_subcategory_id
 * @property string $item_kind
 * @property int $quantity
 * @property int $quantity_received
 * @property string|null $actual_unit_cost
 * @property string $estimated_unit_cost
 * @property CarbonImmutable|null $received_at
 * @property string|null $delivery_reference
 * @property int|null $delivery_committed_quantity
 */
#[Fillable(['procurement_request_id', 'supply_request_line_id', 'inventory_item_id', 'item_kind', 'series_category_id', 'asset_category_id', 'asset_subcategory_id', 'item_name', 'specifications', 'unit_of_measure', 'quantity', 'estimated_unit_cost', 'quantity_received', 'actual_unit_cost', 'received_at', 'delivery_reference'])]
class ProcurementRequestLine extends Model
{
    protected $attributes = ['item_kind' => 'consumable'];

    protected function casts(): array
    {
        return ['estimated_unit_cost' => 'decimal:2', 'actual_unit_cost' => 'decimal:2', 'received_at' => 'date'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return BelongsTo<SupplyRequestLine, $this> */
    public function supplyRequestLine(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestLine::class);
    }

    /** @return BelongsTo<InventoryAssetCategory, $this> */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCategory::class, 'asset_category_id', 'inv_asset_cat_id');
    }

    /** @return BelongsTo<InventoryAssetSubcategory, $this> */
    public function assetSubcategory(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetSubcategory::class, 'asset_subcategory_id', 'inventory_asset_subcategory_id');
    }

    /** @return HasMany<InventoryAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(InventoryAsset::class, 'procurement_request_line_id');
    }

    /** @return HasMany<ProcurementDeliveryLine, $this> */
    public function deliveryLines(): HasMany
    {
        return $this->hasMany(ProcurementDeliveryLine::class);
    }
}
