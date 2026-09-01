<?php

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryStockService;

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
