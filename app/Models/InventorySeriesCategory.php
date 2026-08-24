<?php

namespace App\Models;

use Database\Factories\InventorySeriesCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['inv_class_cat_id', 'code', 'name', 'description', 'is_active'])]
class InventorySeriesCategory extends Model
{
    /** @use HasFactory<InventorySeriesCategoryFactory> */
    use HasFactory;

    protected $table = 'inv_series_cats';

    protected $primaryKey = 'inv_series_cat_id';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<InventorySeriesCategory>  $query
     * @return Builder<InventorySeriesCategory>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return BelongsTo<InventoryClassCategory, $this> */
    public function classCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryClassCategory::class, 'inv_class_cat_id', 'inv_class_cat_id');
    }

    /** @return HasMany<InventoryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'series_category_id', 'inv_series_cat_id');
    }
}
