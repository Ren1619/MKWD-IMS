<?php

use App\Models\AuditLog;
use App\Models\InventoryBatchPlacement;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryStockControl;
use App\Models\User;
use App\Services\InventoryStockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->manager = User::factory()->inventoryManager()->create(['name' => 'Warehouse Officer']);
    $this->item = InventoryItem::factory()->create(['name' => 'Warehouse material', 'quantity' => 0]);
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 10, 'unit_cost' => '12.25', 'received_at' => '2026-10-01', 'expiration_date' => '2027-10-01', 'reference_no' => 'RR-POSTED-1', 'source' => 'Supplier']);
    $this->batch = $this->item->batches()->sole();
    $this->actingAs($this->manager);
});
function placementIntake(array $overrides = []): array
{
    return ['latest_placement_id' => null, 'warehouse' => 'Manongol Reservoir Complex', 'area' => 'Project materials', 'rack' => 'Rack A', 'bin' => 'Bin 1', 'observed_quantity' => 10,
        'source_evidence_reference' => 'SIGNED-RR-POSTED-1', 'remarks' => 'Quantity and description counter-checked against signed RR.', 'attested' => true, ...$overrides];
}
function confirmWarehousePlacement(array $overrides = []): void
{
    test()->post(route('inventory.warehouse.store', test()->batch), placementIntake($overrides))->assertRedirect()->assertSessionHasNoErrors();
}
test('warehouse routes require inventory management permission', function () {
    $this->actingAs(User::factory()->employee()->create());
    foreach (['inventory.warehouse.index', 'inventory.warehouse.show', 'inventory.warehouse.label'] as $route) {
        $this->get(route($route, $route === 'inventory.warehouse.index' ? [] : $this->batch))->assertForbidden();
    }
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake())->assertForbidden();
    expect(InventoryBatchPlacement::query()->count())->toBe(0);
});
test('put-away confirmation freezes source evidence and officer without changing financial stock', function () {
    $before = $this->batch->getAttributes();
    confirmWarehousePlacement(['quantity_remaining' => 999, 'unit_cost' => 1, 'recorded_by_user_id' => 123, 'source_details' => ['item_name' => 'Injected']]);
    $placement = $this->batch->latestPlacement()->sole();
    expect($placement->observed_quantity)->toBe(10)->and($placement->version)->toBe(1)
        ->and($placement->source_details['item_name'])->toBe('Warehouse material')
        ->and($placement->source_details['source_reference'])->toBe('RR-POSTED-1')
        ->and($placement->source_details['expiration_date'])->toBe('2027-10-01')
        ->and($placement->recorded_by_user_id)->toBe($this->manager->id)
        ->and($this->batch->fresh()->getAttributes())->toBe($before)->and($this->item->fresh()->quantity)->toBe(10);
    $audit = AuditLog::query()->where('event', 'inventory.warehouse.placed')->sole();
    expect($audit->new_values['warehouse'])->toBe('Manongol Reservoir Complex')->and($audit->user_id)->toBe($this->manager->id);
    $this->get(route('inventory.warehouse.show', $this->batch))->assertInertia(fn (Assert $page) => $page->component('Inventory/Warehouse/Show')->has('batch.placements', 1));
});
test('location changes append immutable history with optimistic concurrency', function () {
    confirmWarehousePlacement();
    $original = $this->batch->latestPlacement()->sole();
    confirmWarehousePlacement(['latest_placement_id' => $original->id, 'warehouse' => 'Main Warehouse', 'bin' => 'Bin 2', 'remarks' => 'Moved to maintenance storage after counter-check.']);
    expect($this->batch->placements()->count())->toBe(2)->and($this->batch->latestPlacement()->sole()->version)->toBe(2)->and($original->fresh()->warehouse)->toBe('Manongol Reservoir Complex');
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake(['latest_placement_id' => $original->id]))->assertSessionHasErrors('latest_placement_id');
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake())->assertSessionHasErrors('latest_placement_id');
    expect($this->batch->placements()->count())->toBe(2);
    expect(fn () => $original->update(['warehouse' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $original->delete())->toThrow(LogicException::class);
});
test('physical mismatches never become automatic stock corrections', function (int $observed) {
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake(['observed_quantity' => $observed]))->assertSessionHasErrors('observed_quantity');
    expect($this->item->fresh()->quantity)->toBe(10)->and($this->batch->fresh()->quantity_remaining)->toBe(10)->and(InventoryBatchPlacement::query()->count())->toBe(0);
})->with([9, 11]);
test('stock consumed after a counter-check rejects stale observed quantities', function () {
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'ris_no' => 'RIS-1']);
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake())->assertSessionHasErrors('observed_quantity');
    confirmWarehousePlacement(['observed_quantity' => 8]);
    expect($this->batch->latestPlacement()->sole()->observed_quantity)->toBe(8)->and($this->item->fresh()->quantity)->toBe(8);
});
test('active physical-count cutoff blocks storage relocation', function () {
    $session = InventoryCountSession::factory()->create(['status' => 'counting']);
    InventoryStockControl::query()->updateOrCreate(['id' => 1], ['active_count_session_id' => $session->id]);
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake())->assertSessionHasErrors('quantity');
    expect(InventoryBatchPlacement::query()->count())->toBe(0);
});
test('warehouse confirmation requires evidence observed quantity and attestation', function (array $overrides, string $error) {
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake($overrides))->assertSessionHasErrors($error);
    expect(InventoryBatchPlacement::query()->count())->toBe(0);
})->with([
    [['source_evidence_reference' => ''], 'source_evidence_reference'],
    [['remarks' => ''], 'remarks'], [['attested' => false], 'attested'], [['warehouse' => ''], 'warehouse'], [['area' => ''], 'area'],
    [['rack' => str_repeat('a', 101)], 'rack'], [['latest_placement_id' => 0], 'latest_placement_id'],
]);
test('inactive archived depleted and future receipts cannot be placed', function (string $state) {
    if ($state === 'archived') {
        $this->item->delete();
    } elseif ($state === 'inactive') {
        $this->item->update(['status' => 'inactive']);
    } elseif ($state === 'depleted') {
        app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 10, 'ris_no' => 'RIS-EMPTY']);
    } else {
        $this->batch->update(['received_at' => '2026-10-13']);
    }
    $this->post(route('inventory.warehouse.store', $this->batch), placementIntake())->assertSessionHasErrors($state === 'depleted' ? 'observed_quantity' : 'warehouse');
    expect(InventoryBatchPlacement::query()->count())->toBe(0);
})->with(['archived', 'inactive', 'depleted', 'future']);
test('warehouse filters use the current placement rather than superseded locations', function () {
    confirmWarehousePlacement();
    $prior = $this->batch->latestPlacement()->sole();
    confirmWarehousePlacement(['latest_placement_id' => $prior->id, 'warehouse' => 'Main Warehouse']);
    $this->get(route('inventory.warehouse.index', ['warehouse' => 'Manongol Reservoir Complex']))->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
    $this->get(route('inventory.warehouse.index', ['warehouse' => 'Main Warehouse']))->assertInertia(fn (Assert $page) => $page->has('batches.data', 1));
    $this->get(route('inventory.warehouse.index', ['placement' => 'unplaced']))->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
    $this->get(route('inventory.warehouse.index', ['search' => 'RR-POSTED-1']))->assertInertia(fn (Assert $page) => $page->has('batches.data', 1));
    $this->item->delete();
    $this->get(route('inventory.warehouse.index', ['search' => 'Warehouse material']))->assertInertia(fn (Assert $page) => $page->has('batches.data', 1));
});
test('depleted batches remain queryable as historical storage evidence', function () {
    confirmWarehousePlacement();
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 10, 'ris_no' => 'RIS-EMPTY']);
    $this->get(route('inventory.warehouse.index'))->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
    $this->get(route('inventory.warehouse.index', ['include_depleted' => 1]))->assertInertia(fn (Assert $page) => $page->has('batches.data', 1));
    $this->get(route('inventory.warehouse.show', $this->batch))->assertInertia(fn (Assert $page) => $page->has('batch.placements', 1)->where('batch.quantity_remaining', 0));
});
test('batch labels expose traceable source expiry and latest storage with safe escaping', function () {
    $this->get(route('inventory.warehouse.label', $this->batch))->assertOk()->assertSee('Storage not yet recorded');
    confirmWarehousePlacement(['bin' => '<script>alert(1)</script>']);
    $this->get(route('inventory.warehouse.label', $this->batch))->assertOk()->assertSee('RR-POSTED-1')->assertSee('2027-10-01')->assertSee('Manongol Reservoir Complex')->assertSee('Warehouse Officer')->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    $this->manager->update(['name' => 'Renamed officer']);
    $this->batch->update(['expiration_date' => '2026-10-11']);
    $this->get(route('inventory.warehouse.label', $this->batch))->assertOk()->assertSee('EXPIRED')->assertSee('Warehouse Officer')->assertDontSee('Renamed officer');
});
test('warehouse pagination and invalid criteria are validated server side', function () {
    InventoryItemBatch::factory()->count(16)->create();
    $this->get(route('inventory.warehouse.index'))->assertInertia(fn (Assert $page) => $page->has('batches.data', 15)->where('batches.total', 17));
    $this->get(route('inventory.warehouse.index', ['placement' => 'invalid']))->assertSessionHasErrors('placement');
    $this->get(route('inventory.warehouse.show', 999999))->assertNotFound();
});
