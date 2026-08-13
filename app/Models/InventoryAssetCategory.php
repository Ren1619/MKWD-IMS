<?php

namespace App\Models;

use Database\Factories\InventoryAssetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class InventoryAssetCategory extends Model
{
    /** @use HasFactory<InventoryAssetCategoryFactory> */
    use HasFactory;

    protected $table = 'inv_asset_cats';

    protected $primaryKey = 'inv_asset_cat_id';

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

    /** @return HasMany<InventoryAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(InventoryAsset::class, 'category_id', 'inv_asset_cat_id');
    }
}
