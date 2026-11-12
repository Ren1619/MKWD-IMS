<?php

use App\Models\AuditLog;
use App\Models\InventoryBatchPlacement;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryStockService;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->inventoryManager()->create());
});

test('stockrooms use their primary id and only name address and description attributes', function () {
    expect(Schema::hasColumn('warehouses', 'code'))->toBeFalse();

    $this->post(route('inventory.warehouse.registry.store'), [
        'name' => 'Maintenance stockroom',
        'address' => 'Ground floor, east wing',
        'description' => 'Repair supplies',
    ])->assertSessionHasNoErrors();

    $stockroom = Warehouse::query()->sole();
    expect($stockroom->id)->toBeInt()->and($stockroom->address)->toBe('Ground floor, east wing')
        ->and($stockroom->description)->toBe('Repair supplies');

    $this->patch(route('inventory.warehouse.registry.update', $stockroom), ['name' => 'Renamed stockroom'])
        ->assertSessionHasNoErrors();
    expect($stockroom->fresh()->id)->toBe($stockroom->id);
});

test('stockroom management is accessed within storage instead of the sidebar', function () {
    $source = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
    $operations = str($source)->after('label="Inventory Operations"')->before('label="Procurement"')->toString();
    $operations = preg_replace('/\s+/', ' ', $operations);
    $configuration = str($source)->after('const configurationNavItems: NavItem[] = [')->before('const administrationNavItems')->toString();

    expect($operations)->toContain('auth.permissions.manage_inventory', "component === 'Inventory/Warehouse/Index'", "component === 'Inventory/Warehouse/Show'")
        ->not->toContain("title: 'Warehouse Storage'", "title: 'Stockroom Management'", 'href: warehouseIndex()')
        ->and($configuration)->not->toContain('warehouseIndex()', 'warehouseRegistryIndex()');
});

test('managers can register search edit and delete warehouses with an audit trail', function () {
    $this->from(route('inventory.warehouse.registry.index'))->post(route('inventory.warehouse.registry.store'), [
        'name' => 'Main warehouse', 'address' => 'Main compound', 'description' => 'Consumables storage',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $warehouse = Warehouse::query()->sole();
    $this->get(route('inventory.warehouse.registry.index', ['search' => 'Main compound']))->assertInertia(fn (Assert $page) => $page->component('Inventory/Warehouse/Registry')->has('warehouses.data', 1));
    $this->get(route('inventory.warehouse.registry.index', ['search' => 'Missing']))->assertInertia(fn (Assert $page) => $page->has('warehouses.data', 0));
    $this->patch(route('inventory.warehouse.registry.update', $warehouse), ['name' => 'Central warehouse', 'address' => 'New compound'])->assertSessionHasNoErrors();
    expect($warehouse->fresh()->name)->toBe('Central warehouse');
    $this->delete(route('inventory.warehouse.registry.destroy', $warehouse))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($warehouse);
    expect(AuditLog::query()->whereIn('event', ['inventory.warehouse.created', 'inventory.warehouse.updated', 'inventory.warehouse.deleted'])->count())->toBe(3);
});

test('warehouse mutations validate input and uniqueness', function () {
    $warehouse = Warehouse::factory()->create();
    $this->post(route('inventory.warehouse.registry.store'), [])->assertSessionHasErrors(['name']);
    $this->post(route('inventory.warehouse.registry.store'), ['name' => $warehouse->name])->assertSessionHasErrors(['name']);
    $this->patch(route('inventory.warehouse.registry.update', $warehouse), ['name' => $warehouse->name])->assertSessionHasNoErrors();
});

test('employees cannot access or mutate the warehouse registry', function () {
    $warehouse = Warehouse::factory()->create();
    $this->actingAs(User::factory()->employee()->create());
    $this->get(route('inventory.warehouse.registry.index'))->assertForbidden();
    $this->post(route('inventory.warehouse.registry.store'), [])->assertForbidden();
    $this->patch(route('inventory.warehouse.registry.update', $warehouse), [])->assertForbidden();
    $this->delete(route('inventory.warehouse.registry.destroy', $warehouse))->assertForbidden();
    expect($warehouse->fresh()->deleted_at)->toBeNull();
});

test('placements use registered warehouses and preserve names after edits and deletion', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Original warehouse']);
    $destination = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    app(InventoryStockService::class)->stockIn($item, ['quantity' => 10, 'unit_cost' => 5, 'received_at' => now()->toDateString()]);
    $batch = $item->batches()->sole();
    $data = ['latest_placement_id' => null, 'warehouse_id' => $warehouse->id, 'warehouse' => 'Injected name', 'area' => 'Supplies', 'observed_quantity' => 10, 'source_evidence_reference' => 'RR-1', 'remarks' => 'Counter-checked', 'attested' => true];
    $this->post(route('inventory.warehouse.store', $batch), [...$data, 'warehouse_id' => 999999])->assertSessionHasErrors('warehouse_id');
    $this->post(route('inventory.warehouse.store', $batch), $data)->assertSessionHasNoErrors();
    $placement = $batch->latestPlacement()->sole();
    expect($placement->warehouse)->toBe('Original warehouse')->and($placement->warehouse_id)->toBe($warehouse->id);
    $this->delete(route('inventory.warehouse.registry.destroy', $warehouse))->assertSessionHasErrors('warehouse');
    $this->patch(route('inventory.warehouse.registry.update', $warehouse), ['name' => 'Renamed warehouse'])->assertSessionHasNoErrors();
    expect($placement->fresh()->warehouse)->toBe('Original warehouse');
    $this->post(route('inventory.warehouse.store', $batch), [...$data, 'latest_placement_id' => $placement->id, 'warehouse_id' => $destination->id])->assertSessionHasNoErrors();
    $this->delete(route('inventory.warehouse.registry.destroy', $warehouse))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($warehouse);
    $this->post(route('inventory.warehouse.store', $batch), [...$data, 'latest_placement_id' => $batch->latestPlacement()->sole()->id])->assertSessionHasErrors('warehouse_id');
    expect(InventoryBatchPlacement::query()->count())->toBe(2)->and($placement->fresh()->warehouse)->toBe('Original warehouse');
});

test('legacy warehouse import links placements without rewriting historical evidence', function () {
    $placement = InventoryBatchPlacement::factory()->create(['warehouse' => 'Legacy depot', 'warehouse_id' => null]);
    $before = $placement->getAttributes();
    $migration = require database_path('migrations/2026_10_21_024305_register_existing_placement_warehouses.php');
    $migration->up();
    $migration->up();
    $warehouse = Warehouse::query()->where('name', 'Legacy depot')->sole();
    expect($placement->fresh()->warehouse_id)->toBe($warehouse->id)
        ->and($placement->fresh()->getAttributes())->toEqual([...$before, 'warehouse_id' => $warehouse->id]);
});

test('warehouse filtering follows identity after the warehouse name changes', function () {
    $warehouse = Warehouse::factory()->create();
    InventoryBatchPlacement::factory()->create(['warehouse_id' => $warehouse->id, 'warehouse' => $warehouse->name]);
    $warehouse->update(['name' => 'New warehouse name']);
    $this->get(route('inventory.warehouse.index', ['warehouse_id' => $warehouse->id]))
        ->assertInertia(fn (Assert $page) => $page->has('batches.data', 1)->where('warehouses.0.name', 'New warehouse name'));
});

test('stockroom dialog receives searched paginated registry data as json', function () {
    Warehouse::factory()->count(16)->create(['address' => 'Main compound']);
    Warehouse::factory()->create(['address' => 'Other location']);
    $this->getJson(route('inventory.warehouse.registry.index', ['search' => 'Main compound']))
        ->assertSuccessful()->assertJsonCount(15, 'warehouses.data')->assertJsonPath('warehouses.total', 16)
        ->assertJsonPath('filters.search', 'Main compound');
    $this->getJson(route('inventory.warehouse.registry.index', ['search' => 'Main compound', 'page' => 2]))
        ->assertSuccessful()->assertJsonCount(1, 'warehouses.data');
    $this->getJson(route('inventory.warehouse.registry.index', ['search' => 'Missing']))
        ->assertSuccessful()->assertJsonCount(0, 'warehouses.data');
    $this->actingAs(User::factory()->employee()->create());
    $this->getJson(route('inventory.warehouse.registry.index'))->assertForbidden();
});
