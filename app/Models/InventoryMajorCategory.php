<?php

namespace App\Models;

use Database\Factories\InventoryMajorCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class InventoryMajorCategory extends Model
{
    /** @use HasFactory<InventoryMajorCategoryFactory> */
    use HasFactory;

    protected $table = 'inv_mjr_cats';

    protected $primaryKey = 'inv_mjr_cat_id';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<InventoryMajorCategory>  $query
     * @return Builder<InventoryMajorCategory>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<InventoryClassCategory, $this> */
    public function classCategories(): HasMany
    {
        return $this->hasMany(InventoryClassCategory::class, 'inv_mjr_cat_id', 'inv_mjr_cat_id');
    }
}
