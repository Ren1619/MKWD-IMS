<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function itemDescriptionRegistrationData(): array
{
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create(['inventory_asset_category_id' => $category->getKey()]);

    return ['category_id' => $category->getKey(), 'subcategory_id' => $subcategory->getKey(),
        'serial_number' => 'ITEM-DESC-001', 'property_number' => 'PPE-DESC-001',
        'item_description' => 'Network server — rack-mounted, 8 cores', 'unit_of_measure' => 'unit',
        'acquisition_date' => '2026-01-01', 'available_for_use_date' => '2026-01-01', 'acquisition_cost' => 75000,
        'residual_value_percentage' => 5, 'residual_value_basis' => 'COA default residual value.',
        'depreciation_useful_life_months' => 60, 'lifecycle_status' => 'active', 'condition_status' => 'good'];
}

test('registration stores item description and exposes it in the searchable registry', function () {
    $manager = User::factory()->inventoryManager()->create();
    $data = itemDescriptionRegistrationData();
    $this->actingAs($manager)->post(route('inventory.assets.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
    $asset = InventoryAsset::query()->sole();
    expect($asset->item_description)->toBe($data['item_description'])
        ->and($asset->getAttributes())->not->toHaveKey('name')
        ->and(Schema::hasColumn('inventory_assets', 'item_description'))->toBeTrue()
        ->and(Schema::hasColumn('inventory_assets', 'name'))->toBeFalse();
    $this->get(route('inventory.assets.index', ['search' => 'rack-mounted', 'sort' => 'item_description']))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)
        ->where('assets.data.0.item_description', $data['item_description'])->missing('assets.data.0.name'));
    $this->get(route('inventory.assets.show', $asset))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('asset.item_description', $data['item_description']));
});

test('item description has field-specific required and length validation', function (mixed $value) {
    $data = itemDescriptionRegistrationData();
    $data['item_description'] = $value;
    $data['name'] = 'Legacy label must not satisfy the new required field';
    $this->actingAs(User::factory()->inventoryManager()->create())->post(route('inventory.assets.store'), $data)
        ->assertSessionHasErrors('item_description');
    expect(InventoryAsset::query()->exists())->toBeFalse();
})->with(['missing' => null, 'empty' => '', 'too long' => str_repeat('x', 256)]);

test('editing uses item description without changing the additional description', function () {
    $data = itemDescriptionRegistrationData();
    $asset = InventoryAsset::factory()->create([...$data, 'description' => 'Separate supplementary details']);
    $data['item_description'] = 'Updated rack-mounted network server';
    $data['description'] = $asset->description;
    $data['location'] = $asset->location;
    $this->actingAs(User::factory()->inventoryManager()->create())->patch(route('inventory.assets.update', $asset), $data)
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($asset->fresh()->item_description)->toBe($data['item_description'])
        ->and($asset->fresh()->description)->toBe('Separate supplementary details');
});

test('the reversible column rename preserves existing property descriptions', function () {
    $asset = InventoryAsset::factory()->create(['item_description' => 'Preserved property description']);
    $migration = require database_path('migrations/2026_11_10_034053_rename_name_to_item_description_on_inventory_assets_table.php');
    $migration->down();
    $historicalAttributes = (array) DB::table('inventory_assets')->where('inventory_asset_id', $asset->getKey())->first();
    expect(DB::table('inventory_assets')->where('inventory_asset_id', $asset->getKey())->value('name'))->toBe('Preserved property description');
    $migration->up();
    expect($asset->fresh()->item_description)->toBe('Preserved property description')
        ->and($asset->fresh()->historicalSnapshotAttributes())->toBe($historicalAttributes)
        ->and($asset->fresh()->getAttributes())->not->toHaveKey('name');
});

test('the registration dialog and asset types use the new field contract', function () {
    $page = file_get_contents(resource_path('js/pages/Inventory/Assets/Index.tsx'));
    expect($page)->toContain('Item description', 'name="item_description"', 'errors.item_description')
        ->not->toContain('Asset name', 'name="name"', 'asset.name');
});
