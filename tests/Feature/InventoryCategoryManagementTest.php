<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('category page returns the complete hierarchy with usage counts', function () {
    $user = User::factory()->create();
    $major = InventoryMajorCategory::factory()->create();
    $class = InventoryClassCategory::factory()->create(['inv_mjr_cat_id' => $major->getKey()]);
    $series = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $class->getKey()]);
    InventoryItem::factory()->count(2)->create(['series_category_id' => $series->getKey()]);
    $assetCategory = InventoryAssetCategory::factory()->create();
    InventoryAsset::factory()->create(['category_id' => $assetCategory->getKey()]);

    $this->actingAs($user)
        ->get(route('inventory.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Categories/Index')
            ->has('majorCategories', 1)
            ->where('majorCategories.0.inv_mjr_cat_id', $major->getKey())
            ->where('majorCategories.0.class_categories_count', 1)
            ->where('majorCategories.0.class_categories.0.series_categories_count', 1)
            ->where('majorCategories.0.class_categories.0.series_categories.0.items_count', 2)
            ->where('assetCategories.0.assets_count', 1));
});

test('categories can be created and edited with normalized unique values', function () {
    $user = User::factory()->inventoryManager()->create();
    $major = InventoryMajorCategory::factory()->create();

    $this->actingAs($user)->post(route('inventory.categories.store'), [
        'type' => 'class',
        'parent_id' => $major->getKey(),
        'code' => '  ict  ',
        'name' => '  Information   Technology  ',
        'description' => '  Computing   supplies  ',
    ])->assertRedirect(route('inventory.categories.index'));

    $category = InventoryClassCategory::query()->sole();

    expect($category->code)->toBe('ICT')
        ->and($category->name)->toBe('Information Technology')
        ->and($category->description)->toBe('Computing supplies')
        ->and($category->is_active)->toBeTrue();

    $this->actingAs($user)->patch(
        route('inventory.categories.update', ['type' => 'class', 'category' => $category->getKey()]),
        [
            'parent_id' => $major->getKey(),
            'code' => 'it',
            'name' => 'IT Equipment',
            'description' => 'Managed technology equipment',
        ],
    )->assertRedirect(route('inventory.categories.index'));

    expect($category->fresh())
        ->code->toBe('IT')
        ->name->toBe('IT Equipment')
        ->description->toBe('Managed technology equipment');

    $this->actingAs($user)->post(route('inventory.categories.store'), [
        'type' => 'class',
        'parent_id' => $major->getKey(),
        'code' => 'OTHER',
        'name' => 'IT Equipment',
    ])->assertSessionHasErrors('name');
});

test('archiving a hierarchy cascades downward and activation restores required parents', function () {
    $user = User::factory()->inventoryManager()->create();
    $major = InventoryMajorCategory::factory()->create();
    $class = InventoryClassCategory::factory()->create(['inv_mjr_cat_id' => $major->getKey()]);
    $series = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $class->getKey()]);

    $this->actingAs($user)->patch(
        route('inventory.categories.status', ['type' => 'major', 'category' => $major->getKey()]),
        ['is_active' => false],
    )->assertRedirect(route('inventory.categories.index'));

    expect($major->fresh()->is_active)->toBeFalse()
        ->and($class->fresh()->is_active)->toBeFalse()
        ->and($series->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch(
        route('inventory.categories.status', ['type' => 'series', 'category' => $series->getKey()]),
        ['is_active' => true],
    )->assertRedirect(route('inventory.categories.index'));

    expect($major->fresh()->is_active)->toBeTrue()
        ->and($class->fresh()->is_active)->toBeTrue()
        ->and($series->fresh()->is_active)->toBeTrue();
});

test('only unused leaf categories can be permanently deleted', function () {
    $user = User::factory()->inventoryManager()->create();
    $major = InventoryMajorCategory::factory()->create();
    $class = InventoryClassCategory::factory()->create(['inv_mjr_cat_id' => $major->getKey()]);
    $usedSeries = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $class->getKey()]);
    InventoryItem::factory()->create(['series_category_id' => $usedSeries->getKey()]);
    $unusedSeries = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $class->getKey()]);

    $this->actingAs($user)->delete(
        route('inventory.categories.destroy', ['type' => 'major', 'category' => $major->getKey()]),
    )->assertRedirect(route('inventory.categories.index'));

    $this->actingAs($user)->delete(
        route('inventory.categories.destroy', ['type' => 'series', 'category' => $usedSeries->getKey()]),
    )->assertRedirect(route('inventory.categories.index'));

    expect($major->fresh())->not->toBeNull()
        ->and($usedSeries->fresh())->not->toBeNull();

    $this->actingAs($user)->delete(
        route('inventory.categories.destroy', ['type' => 'series', 'category' => $unusedSeries->getKey()]),
    )->assertRedirect(route('inventory.categories.index'));

    expect($unusedSeries->fresh())->toBeNull();
});

test('archived categories cannot be selected for new inventory records', function () {
    $user = User::factory()->inventoryManager()->create();
    $series = InventorySeriesCategory::factory()->create(['is_active' => false]);
    $assetCategory = InventoryAssetCategory::factory()->create(['is_active' => false]);

    $this->actingAs($user)->post(route('inventory.items.store'), [
        'series_category_id' => $series->getKey(),
        'name' => 'Archived category item',
        'unit_of_measure' => 'pc',
        'quantity' => 0,
        'reorder_point' => 10,
        'unit_cost' => 0,
        'status' => 'active',
    ])->assertSessionHasErrors('series_category_id');

    $this->actingAs($user)->post(route('inventory.assets.store'), [
        'category_id' => $assetCategory->getKey(),
        'serial_number' => 'ARCHIVED-001',
        'name' => 'Archived category asset',
        'unit_of_measure' => 'unit',
        'acquisition_date' => now()->toDateString(),
        'depreciation_useful_life_months' => 60,
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
    ])->assertSessionHasErrors('category_id');

    $this->actingAs($user)
        ->get(route('inventory.items.index'))
        ->assertInertia(fn (Assert $page) => $page->has('seriesCategories', 0));

    $this->actingAs($user)
        ->get(route('inventory.assets.index'))
        ->assertInertia(fn (Assert $page) => $page->has('categories', 0));
});
