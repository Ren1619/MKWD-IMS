<?php

namespace App\Models;

use Database\Factories\InventoryClassCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['inv_mjr_cat_id', 'code', 'name', 'description', 'is_active'])]
class InventoryClassCategory extends Model
{
    /** @use HasFactory<InventoryClassCategoryFactory> */
    use HasFactory;

    protected $table = 'inv_class_cats';

    protected $primaryKey = 'inv_class_cat_id';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<InventoryClassCategory>  $query
     * @return Builder<InventoryClassCategory>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return BelongsTo<InventoryMajorCategory, $this> */
    public function majorCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryMajorCategory::class, 'inv_mjr_cat_id', 'inv_mjr_cat_id');
    }

    /** @return HasMany<InventorySeriesCategory, $this> */
    public function seriesCategories(): HasMany
    {
        return $this->hasMany(InventorySeriesCategory::class, 'inv_class_cat_id', 'inv_class_cat_id');
    }
}
