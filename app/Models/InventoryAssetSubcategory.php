<?php

namespace App\Models;

use Database\Factories\InventoryAssetSubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['inventory_asset_category_id', 'code', 'name', 'description', 'is_active'])]
class InventoryAssetSubcategory extends Model
{
    /** @use HasFactory<InventoryAssetSubcategoryFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_asset_subcategory_id';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return BelongsTo<InventoryAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCategory::class, 'inventory_asset_category_id', 'inv_asset_cat_id');
    }

    /** @return HasMany<InventoryAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(InventoryAsset::class, 'subcategory_id', 'inventory_asset_subcategory_id');
    }
}
