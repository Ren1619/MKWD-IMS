<?php

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryStockService;
use Inertia\Testing\AssertableInertia as Assert;

test('a request may contain multiple existing and new supply lines and returns visible feedback', function () {
    $employee = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($employee)
        ->post(route('inventory.requests.store'), [
            'office_name' => 'Engineering Division',
            'purpose' => 'Field operations',
            'lines' => [
                [
                    'inventory_item_id' => $item->getKey(),
                    'is_new_item' => false,
                    'quantity' => 2,
                ],
                [
                    'is_new_item' => true,
                    'item_name' => 'Specialized test strips',
                    'unit_of_measure' => 'box',
                    'quantity' => 3,
                    'estimated_unit_cost' => 125,
                    'justification' => 'No equivalent item exists in the current catalog.',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Supply request submitted.');

    $request = SupplyRequest::query()->with('lines')->sole();

    expect($request->lines)->toHaveCount(2)
        ->and($request->lines->pluck('item_name')->all())
        ->toContain($item->name, 'Specialized test strips');
});

test('available items provide their weighted-average unit cost for supply requests', function () {
    $employee = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    $stockService = app(InventoryStockService::class);

    $stockService->stockIn($item, ['quantity' => 10, 'unit_cost' => 10, 'received_at' => '2026-08-20']);
    $stockService->stockIn($item, ['quantity' => 30, 'unit_cost' => 20, 'received_at' => '2026-08-21']);

    $this->actingAs($employee)
        ->get(route('inventory.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Requests/Index')
            ->has('items', 1)
            ->where('items.0.inventory_item_id', $item->getKey())
            ->where('items.0.weighted_average_unit_cost', '17.50'));
});

test('request and procurement queues can be searched and limited to records needing action', function () {
    $manager = User::factory()->inventoryManager()->create();

    SupplyRequest::factory()->create([
        'ris_no' => 'RIS-2026-NEEDLE',
        'requester_user_id' => $manager->id,
        'status' => 'submitted',
    ]);
    SupplyRequest::factory()->create([
        'ris_no' => 'RIS-2026-CLOSED',
        'requester_user_id' => $manager->id,
        'status' => 'released',
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.requests.index', [
            'search' => 'NEEDLE',
            'queue' => 'needs_action',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Requests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.ris_no', 'RIS-2026-NEEDLE')
            ->where('filters.queue', 'needs_action'));

    ProcurementRequest::factory()->create([
        'pr_no' => 'PR-2026-NEEDLE',
        'created_by_user_id' => $manager->id,
        'status' => 'for_approval',
    ]);
    ProcurementRequest::factory()->create([
        'pr_no' => 'PR-2026-CLOSED',
        'created_by_user_id' => $manager->id,
        'status' => 'accepted',
    ]);

    $this->get(route('inventory.procurement.index', [
        'search' => 'NEEDLE',
        'queue' => 'needs_action',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Procurement/Index')
            ->has('procurementRequests.data', 1)
            ->where('procurementRequests.data.0.pr_no', 'PR-2026-NEEDLE')
            ->where('filters.queue', 'needs_action'));
});

test('employees submit requests but only a different inventory manager can authorize and release stock', function () {
    $employee = User::factory()->employee()->create();
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    app(InventoryStockService::class)->stockIn($item, ['quantity' => 20, 'unit_cost' => 10, 'received_at' => '2026-08-20']);
    $this->actingAs($employee)->post(route('inventory.requests.store'), ['purpose' => 'Official office operations', 'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 6]]])->assertRedirect();
    $request = SupplyRequest::query()->with('lines')->sole();
    expect($request->ris_no)->toMatch('/^RIS-\d{4}-\d{6}$/')->and($request->status)->toBe('submitted');
    foreach (['approve', 'review', 'release'] as $action) {
        $this->actingAs($manager)->patch(route('inventory.requests.transition', $request), ['action' => $action, 'attested' => true, 'remarks' => 'Reviewed supporting records.'])->assertRedirect();
    }
    expect($request->fresh()->status)->toBe('released')->and($request->lines()->first()->quantity_released)->toBe(6)->and($item->fresh()->quantity)->toBe(14)->and($request->actions()->count())->toBe(4);
});
test('requesters cannot authorize their own supply request', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();
    $this->actingAs($manager)->post(route('inventory.requests.store'), ['purpose' => 'Manager request', 'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 1]]]);
    $this->patch(route('inventory.requests.transition', SupplyRequest::query()->sole()), ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
});
test('procurement stocks goods only after planning budget and inspection attestations', function () {
    $preparer = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    $this->actingAs($preparer)->post(route('inventory.procurement.store'), ['type' => 'replenishment', 'source' => 'low_stock', 'purpose' => 'Replenish operational stock', 'funding_source' => 'MOOE', 'ppmp_reference' => 'PPMP-2026-01', 'app_reference' => 'APP-2026-01', 'lines' => [['inventory_item_id' => $item->getKey(), 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure, 'quantity' => 10, 'estimated_unit_cost' => 25]]])->assertRedirect();
    $procurement = ProcurementRequest::query()->sole();
    $actions = [['action' => 'submit'], ['action' => 'budget_review'], ['action' => 'approve'], ['action' => 'forward'], ['action' => 'order', 'procurement_mode' => 'Applicable mode', 'purchase_order_no' => 'PO-100'], ['action' => 'record_delivery', 'delivery_reference' => 'DR-100', 'received_at' => '2026-08-27', 'actual_unit_cost' => 24]];
    foreach ($actions as $data) {
        $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), [...$data, 'attested' => true])->assertRedirect();
    }
    expect($item->fresh()->quantity)->toBe(0);
    $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), ['action' => 'accept', 'attested' => true, 'inspection_acceptance_no' => 'IAR-100'])->assertRedirect();
    $procurement->refresh();
    expect($procurement->status)->toBe('accepted')->and($procurement->inspection_acceptance_no)->toBe('IAR-100')->and($item->fresh()->quantity)->toBe(10);
});

test('a procurement request may contain existing and new catalog items together', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($manager)
        ->post(route('inventory.procurement.store'), [
            'type' => 'mixed',
            'source' => 'manual',
            'purpose' => 'Replenish stock and acquire a new operational supply.',
            'lines' => [
                [
                    'inventory_item_id' => $item->getKey(),
                    'item_name' => $item->name,
                    'unit_of_measure' => $item->unit_of_measure,
                    'quantity' => 10,
                    'estimated_unit_cost' => 25,
                ],
                [
                    'item_name' => 'New water quality reagent',
                    'unit_of_measure' => 'bottle',
                    'quantity' => 5,
                    'estimated_unit_cost' => 150,
                ],
            ],
        ])
        ->assertRedirect();

    $procurement = ProcurementRequest::query()->with('lines')->sole();

    expect($procurement->type)->toBe('mixed')
        ->and($procurement->lines)->toHaveCount(2)
        ->and($procurement->lines->pluck('inventory_item_id')->all())
        ->toContain($item->getKey(), null);
});
