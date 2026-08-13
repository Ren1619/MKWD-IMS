<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('inventory routes require authentication', function () {
    $this->get(route('inventory.items.index'))->assertRedirect(route('login'));
    $this->get(route('inventory.assets.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the IMS dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Dashboard')
            ->has('metrics')
            ->has('recentStockOuts'));
});

test('inventory lists apply status filters', function () {
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
        'status' => 'available',
    ]);
    InventoryAsset::factory()->create([
        'name' => 'Other Projector',
        'status' => 'disposed',
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
        ->get(route('inventory.assets.index', ['status' => 'available']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $availableAsset->inventory_asset_id)
            ->where('filters.status', 'available'));

    $this->actingAs($user)
        ->get(route('inventory.assets.index', ['search' => 'Searchable Laptop']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $availableAsset->inventory_asset_id)
            ->where('filters.search', 'Searchable Laptop'));
});

test('inventory list filters reject invalid input', function (string $routeName, array $query, string $field) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName, $query))
        ->assertSessionHasErrors($field);
})->with([
    'item search length' => ['inventory.items.index', ['search' => str_repeat('a', 101)], 'search'],
    'item status' => ['inventory.items.index', ['status' => 'unknown'], 'status'],
    'asset search length' => ['inventory.assets.index', ['search' => str_repeat('a', 101)], 'search'],
    'asset status' => ['inventory.assets.index', ['status' => 'unknown'], 'status'],
]);

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
        'price' => 245.50,
        'status' => 'active',
    ])->assertRedirect(route('inventory.items.index'));

    $item = InventoryItem::query()->with('batches')->sole();

    expect($item->quantity)->toBe(25)
        ->and($item->batches)->toHaveCount(1)
        ->and($item->batches->first()->quantity_remaining)->toBe(25);
});

test('stock releases consume FIFO batches and retain an HRIS recipient snapshot', function () {
    $user = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create(['name' => 'Maria Santos']);
    $item = InventoryItem::factory()->create();

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 10,
        'received_at' => '2026-08-01',
    ])->assertRedirect(route('inventory.items.index'));

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 8,
        'received_at' => '2026-08-02',
    ])->assertRedirect(route('inventory.items.index'));

    $this->actingAs($user)->patch(route('inventory.items.stock_out', $item), [
        'quantity' => 12,
        'recipient_reference_id' => $recipient->id,
        'ris_no' => 'RIS-100',
    ])->assertRedirect(route('inventory.items.index'));

    $item->refresh()->load(['batches', 'stockOuts']);

    expect($item->quantity)->toBe(6)
        ->and($item->batches[0]->quantity_remaining)->toBe(0)
        ->and($item->batches[1]->quantity_remaining)->toBe(6)
        ->and($item->stockOuts->first()->recipient_name)->toBe('Maria Santos');

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
        ->and($asset->fresh()->status)->toBe('assigned');

    $this->actingAs($user)->post(route('inventory.assets.borrow', $asset), [
        'borrower_reference_id' => $borrower->id,
        'due_at' => now()->addDay()->toDateTimeString(),
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->status)->toBe('borrowed')
        ->and($asset->fresh()->activeBorrowing)->not->toBeNull();

    $this->actingAs($user)->patch(route('inventory.assets.return', $asset), [
        'return_notes' => 'Returned in good condition.',
    ])->assertRedirect(route('inventory.assets.index'));

    expect($asset->fresh()->status)->toBe('assigned')
        ->and($asset->fresh()->activeBorrowing)->toBeNull();
});
