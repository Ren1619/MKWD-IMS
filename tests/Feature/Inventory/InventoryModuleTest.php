<?php

use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\Models\AuditLog;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetCustodian;
use App\Models\InventoryAssetSubcategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('inventory routes require authentication', function () {
    $this->get(route('inventory.items.index'))->assertRedirect(route('login'));
    $this->get(route('inventory.assets.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the IMS dashboard', function () {
    $asset = InventoryAsset::factory()->create();
    InventoryAssetBorrowing::factory()->create([
        'inventory_asset_id' => $asset->inventory_asset_id,
        'status' => 'borrowed',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Dashboard')
            ->has('metrics')
            ->where('metrics.borrowed_assets', 1)
            ->has('recentStockOuts'));
});

test('inventory lists apply independent state filters', function () {
    $user = User::factory()->create();
    $activeItem = InventoryItem::factory()->create([
        'name' => 'Searchable Paper',
        'status' => 'active',
    ]);
    InventoryItem::factory()->create([
        'name' => 'Other Ink',
        'status' => 'inactive',
    ]);
    $availableAsset = InventoryAsset::factory()->create([
        'name' => 'Searchable Laptop',
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
    ]);
    $disposedAsset = InventoryAsset::factory()->create([
        'name' => 'Other Projector',
        'lifecycle_status' => 'disposed',
        'condition_status' => 'non_usable',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $activeItem->inventory_item_id)
            ->where('filters.status', 'active'));

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['search' => 'Searchable Paper']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $activeItem->inventory_item_id)
            ->where('filters.search', 'Searchable Paper'));

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['lifecycle_status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $availableAsset->inventory_asset_id)
            ->where('filters.lifecycle_status', 'active'));

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['condition_status' => 'non_usable']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $disposedAsset->inventory_asset_id)
            ->where('filters.condition_status', 'non_usable'));

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['custody_status' => 'available']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 2)
            ->where('filters.custody_status', 'available'));

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['search' => 'Searchable Laptop']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $availableAsset->inventory_asset_id)
            ->where('filters.search', 'Searchable Laptop'));
});

test('consumable inventory can be filtered by class category', function () {
    $user = User::factory()->create();
    $matchingClass = InventoryClassCategory::factory()->create(['name' => 'Office Supplies']);
    $otherClass = InventoryClassCategory::factory()->create(['name' => 'Medical Supplies']);
    $matchingSeries = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $matchingClass->getKey()]);
    $otherSeries = InventorySeriesCategory::factory()->create(['inv_class_cat_id' => $otherClass->getKey()]);
    $matchingItem = InventoryItem::factory()->create(['series_category_id' => $matchingSeries->getKey()]);
    InventoryItem::factory()->create(['series_category_id' => $otherSeries->getKey()]);

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['class_category_id' => $matchingClass->getKey()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $matchingItem->getKey())
            ->where('filters.class_category_id', (string) $matchingClass->getKey())
            ->has('classCategories', 2));
});

test('the asset registry provides the complete record for its details modal', function () {
    $user = User::factory()->create();
    $custodian = HrisReference::factory()->create([
        'code' => 'EMP-1042',
        'name' => 'Maria Santos',
    ]);
    $subcategory = InventoryAssetSubcategory::factory()->create([
        'name' => 'Notebook computer',
    ]);
    $asset = InventoryAsset::factory()->create([
        'category_id' => $subcategory->inventory_asset_category_id,
        'subcategory_id' => $subcategory->getKey(),
        'current_custodian_reference_id' => $custodian->id,
        'unit_of_measure' => 'unit',
        'fund_cluster' => '01',
        'quantity_per_property_card' => 2,
        'quantity_per_physical_count' => 1,
        'description' => 'Assigned field laptop with charging accessories.',
        'nature_of_occupancy' => 'Owned',
        'acquisition_date' => '2024-01-15',
        'acquisition_cost' => 120000,
        'depreciation_useful_life_months' => 60,
        'appraised_value' => 95000,
        'appraisal_date' => '2026-01-10',
        'impairment_losses' => 2500,
        'physical_count_remarks' => 'One unit is undergoing maintenance.',
        'loss_report_no' => 'LR-2026-015',
        'loss_report_date' => '2026-07-20',
        'loss_type' => 'partial_damage',
        'loss_circumstances' => 'Screen damaged during field inspection.',
        'lifecycle_status' => 'under_maintenance',
        'condition_status' => 'needs_repair',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['search' => $asset->serial_number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Assets/Index')
            ->has('assets.data', 1)
            ->has('assets.data.0', fn (Assert $assetData) => $assetData
                ->where('inventory_asset_id', $asset->inventory_asset_id)
                ->where('subcategory.name', 'Notebook computer')
                ->where('fund_cluster', '01')
                ->where('quantity_per_property_card', 2)
                ->where('quantity_per_physical_count', 1)
                ->where('description', 'Assigned field laptop with charging accessories.')
                ->where('nature_of_occupancy', 'Owned')
                ->where('acquisition_cost', '120000.00')
                ->where('depreciation_useful_life_months', 60)
                ->where('depreciation_amount', fn (mixed $value): bool => is_numeric($value))
                ->where('book_value', fn (mixed $value): bool => is_numeric($value))
                ->where('appraised_value', '95000.00')
                ->where('impairment_losses', '2500.00')
                ->where('physical_count_remarks', 'One unit is undergoing maintenance.')
                ->where('loss_report_no', 'LR-2026-015')
                ->where('loss_type', 'partial_damage')
                ->where('loss_circumstances', 'Screen damaged during field inspection.')
                ->where('lifecycle_status', 'under_maintenance')
                ->where('condition_status', 'needs_repair')
                ->where('custody_status', 'assigned')
                ->where('current_custodian.name', 'Maria Santos')
                ->where('current_custodian.code', 'EMP-1042')
                ->etc()));
});

test('inventory list filters reject invalid input', function (string $routeName, array $query, string $field) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName, $query))
        ->assertSessionHasErrors($field);
})->with([
    'item search length' => ['inventory.items.index', ['search' => str_repeat('a', 101)], 'search'],
    'item status' => ['inventory.items.index', ['status' => 'unknown'], 'status'],
    'item records' => ['inventory.items.index', ['records' => 'deleted'], 'records'],
    'item class category' => ['inventory.items.index', ['class_category_id' => 999999], 'class_category_id'],
    'asset search length' => ['inventory.assets.index', ['search' => str_repeat('a', 101)], 'search'],
    'asset lifecycle' => ['inventory.assets.index', ['lifecycle_status' => 'unknown'], 'lifecycle_status'],
    'asset condition' => ['inventory.assets.index', ['condition_status' => 'damaged'], 'condition_status'],
    'asset custody' => ['inventory.assets.index', ['custody_status' => 'held'], 'custody_status'],
    'asset records' => ['inventory.assets.index', ['records' => 'deleted'], 'records'],
]);

test('inventory records can be archived and restored without losing their histories', function () {
    $user = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create([
        'name' => 'Completed Stock Record',
        'quantity' => 0,
    ]);
    $batch = InventoryItemBatch::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'quantity_in' => 5,
        'quantity_remaining' => 0,
    ]);
    $stockOut = InventoryItemStockOut::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'quantity' => 5,
    ]);
    $asset = InventoryAsset::factory()->create(['name' => 'Retired Laptop']);
    $custodian = InventoryAssetCustodian::factory()->create([
        'inventory_asset_id' => $asset->inventory_asset_id,
        'unassigned_at' => now()->subDay(),
    ]);
    $borrowing = InventoryAssetBorrowing::factory()->create([
        'inventory_asset_id' => $asset->inventory_asset_id,
        'status' => 'returned',
        'returned_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->delete(route('inventory.items.destroy', $item))
        ->assertRedirect(route('inventory.items.index'));
    $this->delete(route('inventory.assets.destroy', $asset))
        ->assertRedirect(route('inventory.assets.index'));

    $this->assertSoftDeleted($item);
    $this->assertSoftDeleted($asset);
    expect($batch->fresh())->not->toBeNull()
        ->and($stockOut->fresh())->not->toBeNull()
        ->and($custodian->fresh())->not->toBeNull()
        ->and($borrowing->fresh())->not->toBeNull();

    $this->get(route('inventory.items.index', ['search' => $item->name]))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 0));
    $this->get(route('inventory.items.index', [
        'records' => 'archived',
        'search' => $item->name,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.inventory_item_id', $item->inventory_item_id)
        ->where('filters.records', 'archived'));
    $this->get(route('inventory.assets.index', [
        'records' => 'archived',
        'search' => $asset->name,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('assets.data', 1)
        ->where('assets.data.0.inventory_asset_id', $asset->inventory_asset_id)
        ->where('filters.records', 'archived'));

    $this->patch(route('inventory.items.restore', $item->inventory_item_id))
        ->assertRedirect(route('inventory.items.index', ['records' => 'archived']));
    $this->patch(route('inventory.assets.restore', $asset->inventory_asset_id))
        ->assertRedirect(route('inventory.assets.index', ['records' => 'archived']));

    $this->assertNotSoftDeleted($item);
    $this->assertNotSoftDeleted($asset);
    expect(AuditLog::query()
        ->where('auditable_type', InventoryItem::class)
        ->where('auditable_id', $item->inventory_item_id)
        ->pluck('event'))
        ->toContain('archived', 'restored');
});

test('operational inventory records cannot be archived', function () {
    $user = User::factory()->inventoryManager()->create();
    $stockedItem = InventoryItem::factory()->create(['quantity' => 4]);
    $assignedAsset = InventoryAsset::factory()->create([
        'current_custodian_reference_id' => HrisReference::factory(),
    ]);
    $borrowedAsset = InventoryAsset::factory()->create();
    InventoryAssetBorrowing::factory()->create([
        'inventory_asset_id' => $borrowedAsset->inventory_asset_id,
        'status' => 'borrowed',
    ]);

    $this->actingAs($user)->delete(route('inventory.items.destroy', $stockedItem));
    $this->delete(route('inventory.assets.destroy', $assignedAsset));
    $this->delete(route('inventory.assets.destroy', $borrowedAsset));

    $this->assertNotSoftDeleted($stockedItem);
    $this->assertNotSoftDeleted($assignedAsset);
    $this->assertNotSoftDeleted($borrowedAsset);
});

test('category hierarchy and opening stock can be created', function () {
    $user = User::factory()->inventoryManager()->create();

    $this->actingAs($user)->post(route('inventory.categories.store'), [
        'type' => 'major',
        'code' => 'SUP',
        'name' => 'Supplies',
    ])->assertRedirect(route('inventory.categories.index'));

    $major = InventoryMajorCategory::query()->sole();

    $this->actingAs($user)->post(route('inventory.categories.store'), [
        'type' => 'class',
        'parent_id' => $major->getKey(),
        'code' => 'OFF',
        'name' => 'Office Supplies',
    ])->assertRedirect(route('inventory.categories.index'));

    $class = InventoryClassCategory::query()->sole();

    $this->actingAs($user)->post(route('inventory.categories.store'), [
        'type' => 'series',
        'parent_id' => $class->getKey(),
        'code' => 'PAPER',
        'name' => 'Paper',
    ])->assertRedirect(route('inventory.categories.index'));

    $series = InventorySeriesCategory::query()->sole();

    $this->actingAs($user)->post(route('inventory.items.store'), [
        'series_category_id' => $series->getKey(),
        'name' => 'A4 Copy Paper',
        'stock_number' => 'PAPER-001',
        'unit_of_measure' => 'ream',
        'quantity' => 25,
        'reorder_point' => 10,
        'reorder_quantity' => 50,
        'unit_cost' => 245.50,
        'received_at' => '2026-08-01',
        'expiration_date' => '2028-08-01',
        'source' => 'Paperline Commercial',
        'reference_no' => 'DR-100',
        'status' => 'active',
    ])->assertRedirect(route('inventory.items.index'));

    $item = InventoryItem::query()->with('batches')->sole();

    expect($item->quantity)->toBe(25)
        ->and($item->batches)->toHaveCount(1)
        ->and($item->batches->first()->quantity_remaining)->toBe(25)
        ->and($item->batches->first()->unit_cost)->toBe('245.50')
        ->and($item->batches->first()->source)->toBe('Paperline Commercial')
        ->and($item->inventory_value)->toBe('6137.50')
        ->and($item->next_expiration_date)->toBe('2028-08-01');
});

test('stock releases consume FIFO batches and retain an HRIS recipient snapshot', function () {
    $user = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create(['name' => 'Maria Santos']);
    $item = InventoryItem::factory()->create();

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 10,
        'unit_cost' => 100,
        'received_at' => '2026-08-01',
    ])->assertRedirect(route('inventory.items.index'));

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 8,
        'unit_cost' => 125,
        'received_at' => '2026-08-02',
    ])->assertRedirect(route('inventory.items.index'));

    $this->actingAs($user)->patch(route('inventory.items.stock_out', $item), [
        'quantity' => 12,
        'recipient_reference_id' => $recipient->id,
        'ris_no' => 'RIS-100',
    ])->assertRedirect(route('inventory.items.index'));

    $item->refresh()->load(['batches', 'stockOuts.allocations']);
    $stockOut = $item->stockOuts->first();

    expect($item->quantity)->toBe(6)
        ->and($item->batches[0]->quantity_remaining)->toBe(0)
        ->and($item->batches[1]->quantity_remaining)->toBe(6)
        ->and($stockOut->recipient_name)->toBe('Maria Santos')
        ->and($stockOut->allocations)->toHaveCount(2)
        ->and($stockOut->allocations[0]->quantity)->toBe(10)
        ->and($stockOut->allocations[0]->unit_cost)->toBe('100.00')
        ->and($stockOut->allocations[1]->quantity)->toBe(2)
        ->and($stockOut->allocations[1]->unit_cost)->toBe('125.00')
        ->and($stockOut->total_cost)->toBe('1250.00')
        ->and($item->inventory_value)->toBe('750.00');

    $this->actingAs($user)->patch(route('inventory.items.stock_out', $item), [
        'quantity' => 7,
        'recipient_name' => 'Walk-in recipient',
    ])->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(6);
});

test('assets can be assigned borrowed and returned with HRIS references', function () {
    $user = User::factory()->inventoryManager()->create();
    $custodian = HrisReference::factory()->create();
    $borrower = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create([
        'category_id' => InventoryAssetCategory::factory(),
    ]);

    $this->actingAs($user)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $custodian->id,
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->current_custodian_reference_id)->toBe($custodian->id)
        ->and($asset->fresh()->custody_status)->toBe('assigned');

    $this->actingAs($user)->post(route('inventory.assets.borrow', $asset), [
        'borrower_reference_id' => $borrower->id,
        'due_at' => now()->addDay()->toDateTimeString(),
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->custody_status)->toBe('borrowed')
        ->and($asset->fresh()->activeBorrowing)->not->toBeNull();

    $this->actingAs($user)->patch(route('inventory.assets.return', $asset), [
        'return_notes' => 'Returned in good condition.',
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->custody_status)->toBe('assigned')
        ->and($asset->fresh()->activeBorrowing)->toBeNull();
});

test('asset lifecycle and condition transitions control operational actions', function () {
    $user = User::factory()->inventoryManager()->create();
    $borrower = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create();

    $this->actingAs($user)->patch(route('inventory.assets.update_state', $asset), [
        'lifecycle_status' => 'under_maintenance',
        'condition_status' => 'needs_repair',
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::UnderMaintenance)
        ->and($asset->fresh()->condition_status)->toBe(AssetConditionStatus::NeedsRepair)
        ->and($asset->fresh()->is_borrowable)->toBeFalse();

    $this->post(route('inventory.assets.borrow', $asset), [
        'borrower_reference_id' => $borrower->id,
    ])->assertSessionHasErrors('borrower_reference_id');

    $this->patch(route('inventory.assets.update_state', $asset), [
        'lifecycle_status' => 'active',
        'condition_status' => 'fair',
    ])->assertRedirect(route('inventory.assets.index'));

    $this->post(route('inventory.assets.borrow', $asset), [
        'borrower_reference_id' => $borrower->id,
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->custody_status)->toBe('borrowed');

    $this->patch(route('inventory.assets.update_state', $asset), [
        'lifecycle_status' => 'disposed',
        'condition_status' => 'non_usable',
    ])->assertSessionHasErrors('lifecycle_status');
});
