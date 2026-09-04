<?php

namespace App;

use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Eloquent\Model;

enum InventoryCategoryType: string
{
    case Major = 'major';
    case ClassCategory = 'class';
    case Series = 'series';
    case Asset = 'asset';
    case AssetSubcategory = 'asset_subcategory';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Major => InventoryMajorCategory::class,
            self::ClassCategory => InventoryClassCategory::class,
            self::Series => InventorySeriesCategory::class,
            self::Asset => InventoryAssetCategory::class,
            self::AssetSubcategory => InventoryAssetSubcategory::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Major => 'inv_mjr_cats',
            self::ClassCategory => 'inv_class_cats',
            self::Series => 'inv_series_cats',
            self::Asset => 'inv_asset_cats',
            self::AssetSubcategory => 'inventory_asset_subcategories',
        };
    }

    public function primaryKey(): string
    {
        return match ($this) {
            self::Major => 'inv_mjr_cat_id',
            self::ClassCategory => 'inv_class_cat_id',
            self::Series => 'inv_series_cat_id',
            self::Asset => 'inv_asset_cat_id',
            self::AssetSubcategory => 'inventory_asset_subcategory_id',
        };
    }

    public function parentColumn(): ?string
    {
        return match ($this) {
            self::ClassCategory => 'inv_mjr_cat_id',
            self::Series => 'inv_class_cat_id',
            self::AssetSubcategory => 'inventory_asset_category_id',
            default => null,
        };
    }

    public function parentTable(): ?string
    {
        return match ($this) {
            self::ClassCategory => 'inv_mjr_cats',
            self::Series => 'inv_class_cats',
            self::AssetSubcategory => 'inv_asset_cats',
            default => null,
        };
    }

    public function parentPrimaryKey(): ?string
    {
        return match ($this) {
            self::ClassCategory => 'inv_mjr_cat_id',
            self::Series => 'inv_class_cat_id',
            self::AssetSubcategory => 'inv_asset_cat_id',
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Major => 'major category',
            self::ClassCategory => 'class category',
            self::Series => 'series category',
            self::Asset => 'asset category',
            self::AssetSubcategory => 'asset subcategory',
        };
    }
}
