<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use Database\Seeders\InventorySeeder;

test('inventory seeder creates the requested sample inventory hierarchy and records', function () {
    $this->seed(InventorySeeder::class);

    expect(InventoryMajorCategory::query()->count())->toBe(5)
        ->and(InventoryClassCategory::query()->count())->toBe(10)
        ->and(InventorySeriesCategory::query()->count())->toBe(20)
        ->and(InventoryAssetCategory::query()->count())->toBe(5)
        ->and(InventoryAssetSubcategory::query()->count())->toBe(10)
        ->and(InventoryAsset::query()->whereNotNull('subcategory_id')->count())->toBe(10)
        ->and(InventoryItem::query()->count())->toBe(10)
        ->and(InventoryAsset::query()->distinct()->count('acquisition_date'))->toBeGreaterThan(5)
        ->and(InventoryAsset::query()->distinct()->count('condition_status'))->toBeGreaterThan(3)
        ->and(InventoryAsset::query()->whereHas('activeBorrowing')->count())->toBe(1)
        ->and(InventoryItemBatch::query()->whereNotNull('expiration_date')->distinct()->count('expiration_date'))->toBeGreaterThan(4)
        ->and(InventoryItemBatch::query()->distinct()->count('unit_cost'))->toBeGreaterThan(10)
        ->and(InventoryItemBatch::query()->whereNotNull('source')->count())->toBeGreaterThanOrEqual(10)
        ->and(InventoryItem::query()->distinct()->count('reorder_point'))->toBeGreaterThan(5)
        ->and(InventoryItem::query()->whereNotNull('reorder_quantity')->count())->toBe(10);

    $items = InventoryItem::query()->with('batches')->get();

    foreach ($items as $item) {
        expect($item->batches)->not->toBeEmpty()
            ->and($item->quantity)->toBe($item->batches->sum('quantity_remaining'))
            ->and($item->inventory_value)->toBe(number_format(
                $item->batches->sum(fn (InventoryItemBatch $batch): float => $batch->quantity_remaining * (float) $batch->unit_cost),
                2,
                '.',
                '',
            ));
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
        ->and(InventoryItemBatch::query()->count())->toBe(13)
        ->and(InventoryItem::query()->has('batches', '>', 1)->count())->toBe(3);
});
