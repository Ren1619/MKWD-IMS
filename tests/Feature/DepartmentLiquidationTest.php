<?php

use App\Models\DepartmentLiquidation;
use App\Models\InventoryItem;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\DepartmentLiquidationService;
use App\Services\InventoryStockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 9)->startOfDay());
    $this->recorder = User::factory()->inventoryManager()->create();
    $this->checker = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->superAdmin()->create();
    $this->item = InventoryItem::factory()->create();
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 30, 'unit_cost' => 25]);
    $this->request = SupplyRequest::factory()->create(['office_name' => 'Operations', 'isf_no' => '10-09-001', 'responsibility_center_code' => 'RC-1']);
    $this->line = $this->request->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 10, 'quantity_released' => 4]);
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 4, 'supply_request_line_id' => $this->line->id]);
    $this->otherRequest = SupplyRequest::factory()->create(['office_name' => 'Operations', 'isf_no' => '10-09-002']);
    $this->otherLine = $this->otherRequest->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 6, 'quantity_released' => 6]);
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 6, 'supply_request_line_id' => $this->otherLine->id]);
    $this->payload = ['supply_request_id' => $this->request->id, 'liquidation_date' => '2026-10-09', 'attested' => true, 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 3, 'usage_details' => 'Pipeline maintenance']]];
    $this->actingAs($this->recorder);
});

test('liquidation includes only actual issues from its selected ISF', function () {
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'department_name' => 'Operations']);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    expect($document->supply_request_id)->toBe($this->request->id)
        ->and($document->isf_no)->toBe('10-09-001')->and($document->department)->toBe('Operations')
        ->and($document->charge_code)->toBe('RC-1')->and($document->lines)->toHaveCount(1)
        ->and($document->lines[0]['quantity_issued'])->toBe(4)->and($document->lines[0]['quantity_utilized'])->toBe(3)
        ->and($document->lines[0]['balance'])->toBe(1)->and($document->lines[0]['source_stock_out_ids'])->toHaveCount(1)
        ->and($this->item->fresh()->quantity)->toBe(18);
    $this->get(route('inventory.department_liquidations.print', $document))->assertOk()->assertSee('10-09-001')->assertSee('Pipeline maintenance');
});

test('later releases and prior utilization stay scoped to the same ISF', function () {
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    $route = route('inventory.department_liquidations.transition', $document);
    $this->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->checker)->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->approver)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 5, 'supply_request_line_id' => $this->line->id]);
    $this->line->increment('quantity_released', 5);
    $this->payload['lines'][0]['quantity_utilized'] = 5;
    $this->actingAs($this->recorder)->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $next = DepartmentLiquidation::query()->latest('id')->first();
    expect($next->lines[0]['quantity_issued'])->toBe(9)->and($next->lines[0]['previously_utilized'])->toBe(3)
        ->and($next->lines[0]['quantity_utilized'])->toBe(5)->and($next->lines[0]['balance'])->toBe(4)
        ->and($document->fresh()->lines[0]['quantity_issued'])->toBe(4)->and($document->fresh()->lines[0]['balance'])->toBe(1)
        ->and($this->item->fresh()->quantity)->toBe(15);
});

test('invalid utilization and another ISF line cannot be submitted', function (int $quantity, bool $foreign) {
    $this->payload['lines'][0]['quantity_utilized'] = $quantity;
    if ($foreign) {
        $this->payload['lines'][0]['supply_request_line_id'] = $this->otherLine->id;
    }
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors();
    expect(DepartmentLiquidation::query()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(20);
})->with([[5, false], [-1, false], [2, true]]);

test('pending liquidation is limited per ISF rather than per department', function () {
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('supply_request_id');
    $this->payload['supply_request_id'] = $this->otherRequest->id;
    $this->payload['lines'][0]['supply_request_line_id'] = $this->otherLine->id;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->count())->toBe(2);
});

test('utilization cannot decrease below the approved total for the same ISF', function () {
    $service = app(DepartmentLiquidationService::class);
    $document = $service->submit($this->recorder, $this->payload);
    $service->transition($document, $this->checker, 'check', null);
    $service->transition($document, $this->approver, 'approve', null);
    $this->payload['lines'][0]['quantity_utilized'] = 2;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
});

test('date cutoff excludes subsequent releases and attestation is required', function () {
    $this->payload['liquidation_date'] = '2026-10-08';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
    $this->payload['liquidation_date'] = '2026-10-09';
    $this->payload['attested'] = false;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('attested');
});

test('employees cannot submit review or print ISF liquidation', function () {
    $document = app(DepartmentLiquidationService::class)->submit($this->recorder, $this->payload);
    $this->actingAs(User::factory()->employee()->create())->post(route('inventory.department_liquidations.store'), $this->payload)->assertForbidden();
    $this->patch(route('inventory.department_liquidations.transition', $document), ['action' => 'check', 'attested' => true])->assertForbidden();
    $this->get(route('inventory.department_liquidations.print', $document))->assertForbidden();
    $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page->has('issuanceAllocations', 0)->has('liquidationDocuments', 0));
});

test('materials preview recalculates only the selected ISF for the selected date', function () {
    $this->getJson(route('inventory.department_liquidations.allocations', ['supply_request_id' => $this->request->id, 'date' => '2026-10-09']))
        ->assertOk()->assertJsonPath('lines.0.quantity_issued', 4);
    $this->getJson(route('inventory.department_liquidations.allocations', ['supply_request_id' => $this->request->id, 'date' => '2026-10-08']))
        ->assertOk()->assertJsonPath('lines', []);
    $this->getJson(route('inventory.department_liquidations.allocations', ['supply_request_id' => $this->request->id, 'date' => '2026-10-10']))
        ->assertUnprocessable();
    $this->actingAs(User::factory()->employee()->create())->getJson(route('inventory.department_liquidations.allocations', ['supply_request_id' => $this->request->id, 'date' => '2026-10-09']))->assertForbidden();
});

test('all issued lines are required even when two lines contain the same catalog material', function () {
    $line = $this->request->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => 'Separate purpose', 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 2, 'quantity_released' => 2]);
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'supply_request_line_id' => $line->id]);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
    $this->payload['lines'][] = ['supply_request_line_id' => $line->id, 'quantity_utilized' => 1];
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->lines)->toHaveCount(2);
});

test('approved legacy per release usage remains attributed to its ISF', function () {
    $this->request->issuanceSlips()->create([
        'slip_no' => '10-09-001', 'issued_at' => '2026-10-09', 'issued_by_user_id' => $this->recorder->id,
        'snapshot' => ['department' => 'Operations'], 'status' => 'approved', 'history' => [],
        'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_issued' => 4, 'quantity_utilized' => 2]],
    ]);
    $allocation = app(DepartmentLiquidationService::class)->allocation($this->request, '2026-10-09');
    expect($allocation['lines'][0]['previously_utilized'])->toBe(2);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->lines[0]['previously_utilized'])->toBe(2);
});

test('an optional return slip is stored separately and does not reduce liquidation balance', function () {
    $this->payload['return_slip_no'] = 'RS-001';
    $this->payload['return_slip_date'] = '2026-10-09';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    expect($document->return_slip_no)->toBe('RS-001')->and($document->lines[0]['balance'])->toBe(1)->and($this->item->fresh()->quantity)->toBe(20);
    $this->get(route('inventory.department_liquidations.print', $document))->assertOk()->assertSee('RS-001');
});

test('return slip references require a date within issuance and liquidation', function () {
    $this->payload['return_slip_no'] = 'RS-001';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('return_slip_date');
    $this->payload['return_slip_date'] = '2026-10-08';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('return_slip_date');
});

test('RIS liquidation can be submitted without a return slip reference', function () {
    $this->payload['return_slip_no'] = '';
    $this->payload['return_slip_date'] = '';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    expect($document->return_slip_no)->toBeNull()
        ->and($document->return_slip_date)->toBeNull()
        ->and($document->lines[0]['balance'])->toBe(1);
});

test('an unreleased form or a non ISF request cannot be liquidated', function () {
    $unreleased = SupplyRequest::factory()->create(['isf_no' => '10-09-003']);
    $this->payload['supply_request_id'] = $unreleased->id;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
    $this->request->update(['isf_no' => null]);
    $this->payload['supply_request_id'] = $this->request->id;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('supply_request_id');
});

test('the RIS return slip reference is saved on only its selected request and carried into liquidation', function () {
    $this->patch(route('inventory.requests.return_slip', $this->request), ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => true])->assertSessionHasNoErrors();
    expect($this->request->fresh()->return_slip_no)->toBe('RS-100')->and($this->otherRequest->fresh()->return_slip_no)->toBeNull()->and($this->item->fresh()->quantity)->toBe(20);
    $this->assertDatabaseHas('supply_request_actions', ['supply_request_id' => $this->request->id, 'action' => 'record_return_slip']);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->return_slip_no)->toBe('RS-100')->and(DepartmentLiquidation::query()->sole()->lines[0]['balance'])->toBe(1);
});

test('return slip references require attestation and cannot precede an actual release', function () {
    $route = route('inventory.requests.return_slip', $this->request);
    $this->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => false])->assertSessionHasErrors('attested');
    $this->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-08', 'attested' => true])->assertSessionHasErrors('return_slip_date');
    $this->actingAs(User::factory()->employee()->create())->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => true])->assertForbidden();
});
