<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use Database\Seeders\InventorySeeder;

test('inventory seeder creates the requested sample inventory hierarchy and records', function () {
    $this->seed(InventorySeeder::class);

    expect(InventoryMajorCategory::query()->count())->toBe(5)
        ->and(InventoryClassCategory::query()->count())->toBe(10)
        ->and(InventorySeriesCategory::query()->count())->toBe(20)
        ->and(InventoryAssetCategory::query()->count())->toBe(5)
        ->and(InventoryAsset::query()->where('type', 'Property')->count())->toBe(5)
        ->and(InventoryAsset::query()->where('type', 'Equipment')->count())->toBe(5)
        ->and(InventoryItem::query()->count())->toBe(10)
        ->and(InventoryAsset::query()->distinct()->count('acquisition_date'))->toBeGreaterThan(5)
        ->and(InventoryItem::query()->whereNotNull('expiration_date')->distinct()->count('expiration_date'))->toBeGreaterThan(4);

    $items = InventoryItem::query()->with('batches')->get();

    foreach ($items as $item) {
        expect($item->batches)->toHaveCount(1)
            ->and($item->quantity)->toBe($item->batches->sum('quantity_remaining'));
    }
});

test('inventory seeder can be safely run more than once', function () {
    $this->seed(InventorySeeder::class);
    $this->seed(InventorySeeder::class);

    expect(InventoryMajorCategory::query()->count())->toBe(5)
        ->and(InventoryClassCategory::query()->count())->toBe(10)
        ->and(InventorySeriesCategory::query()->count())->toBe(20)
        ->and(InventoryAssetCategory::query()->count())->toBe(5)
        ->and(InventoryAsset::query()->count())->toBe(10)
        ->and(InventoryItem::query()->count())->toBe(10)
        ->and(InventoryItem::query()->withCount('batches')->get()->pluck('batches_count')->unique()->all())->toBe([1]);
});
