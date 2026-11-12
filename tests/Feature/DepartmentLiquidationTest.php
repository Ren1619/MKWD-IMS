<?php

use App\Models\DepartmentLiquidation;
use App\Models\InventoryItem;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\DepartmentLiquidationService;
use App\Services\InventoryStockService;
use App\Services\IssuanceLiquidationService;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

function createLiquidationTestIssue(SupplyRequest $request, SupplyRequestLine $line, User $actor, int $quantity): IssuanceSlip
{
    $stockOut = app(InventoryStockService::class)->stockOut($line->item, ['quantity' => $quantity, 'supply_request_line_id' => $line->id]);
    $slip = app(IssuanceLiquidationService::class)->issue($request, $actor, [['supply_request_line_id' => $line->id, 'item_description' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure, 'quantity_issued' => $quantity]]);
    $stockOut->issuanceSlip()->associate($slip);
    $stockOut->save();

    return $slip;
}

beforeEach(function () {
    $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 9)->startOfDay());
    $this->recorder = User::factory()->inventoryManager()->create();
    $this->checker = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->superAdmin()->create();
    $this->item = InventoryItem::factory()->create();
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 30, 'unit_cost' => 25]);
    $this->request = SupplyRequest::factory()->create(['office_name' => 'Operations', 'irf_no' => '10-09-001', 'responsibility_center_code' => 'RC-1']);
    $this->line = $this->request->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 10, 'quantity_released' => 4]);
    $this->slip = createLiquidationTestIssue($this->request, $this->line, $this->recorder, 4);
    $this->otherRequest = SupplyRequest::factory()->create(['office_name' => 'Operations', 'irf_no' => '10-09-002']);
    $this->otherLine = $this->otherRequest->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 6, 'quantity_released' => 6]);
    $this->otherSlip = createLiquidationTestIssue($this->otherRequest, $this->otherLine, $this->recorder, 6);
    $this->payload = ['issuance_slip_id' => $this->slip->id, 'return_slip_no' => 'RS-001', 'return_slip_date' => '2026-10-09', 'liquidation_date' => '2026-10-09', 'attested' => true, 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 3, 'usage_details' => 'Pipeline maintenance']]];
    $this->actingAs($this->recorder);
});

test('liquidation links only its selected IS while the IS links its request form', function () {
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'department_name' => 'Operations']);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    expect($document->supply_request_id)->toBeNull()
        ->and($document->irf_no)->toBeNull()->and($document->issuance_slip_id)->toBe($this->slip->id)
        ->and($document->is_no)->toBe($this->slip->slip_no)
        ->and($document->issuanceSlip->supplyRequest->irf_no)->toBe('10-09-001')->and($document->department)->toBe('Operations')
        ->and($document->charge_code)->toBe('RC-1')->and($document->lines)->toHaveCount(1)
        ->and($document->lines[0]['quantity_issued'])->toBe(4)->and($document->lines[0]['quantity_utilized'])->toBe(3)
        ->and($document->lines[0]['balance'])->toBe(1)->and($document->lines[0]['source_stock_out_ids'])->toHaveCount(1)
        ->and($this->item->fresh()->quantity)->toBe(18);
    $this->get(route('inventory.department_liquidations.print', $document))->assertOk()->assertSee('IS NO.:')->assertSee('Pipeline maintenance')->assertDontSee('ISSUANCE REQUEST FORM NO.');
});

test('later releases on the same request retain independent liquidation quantities', function () {
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    $route = route('inventory.department_liquidations.transition', $document);
    $this->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->checker)->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->approver)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    $nextSlip = createLiquidationTestIssue($this->request, $this->line, $this->recorder, 5);
    $this->line->increment('quantity_released', 5);
    $this->payload['issuance_slip_id'] = $nextSlip->id;
    $this->payload['lines'][0]['quantity_utilized'] = 4;
    $this->actingAs($this->recorder)->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $next = DepartmentLiquidation::query()->latest('id')->first();
    expect($next->lines[0]['quantity_issued'])->toBe(5)->and($next->lines[0]['previously_utilized'])->toBe(0)
        ->and($next->lines[0]['quantity_utilized'])->toBe(4)->and($next->lines[0]['balance'])->toBe(1)
        ->and($document->fresh()->lines[0]['quantity_issued'])->toBe(4)->and($document->fresh()->lines[0]['balance'])->toBe(1)
        ->and($this->item->fresh()->quantity)->toBe(15);
});

test('invalid utilization and another IRF line cannot be submitted', function (int $quantity, bool $foreign) {
    $this->payload['lines'][0]['quantity_utilized'] = $quantity;
    if ($foreign) {
        $this->payload['lines'][0]['supply_request_line_id'] = $this->otherLine->id;
    }
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors();
    expect(DepartmentLiquidation::query()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(20);
})->with([[5, false], [-1, false], [2, true]]);

test('pending liquidation is limited per issuance slip rather than per request or department', function () {
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('issuance_slip_id');
    $nextSlip = createLiquidationTestIssue($this->request, $this->line, $this->recorder, 4);
    $this->payload['issuance_slip_id'] = $nextSlip->id;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->count())->toBe(2);
});

test('utilization cannot decrease below the approved total for the same issuance slip', function () {
    $service = app(DepartmentLiquidationService::class);
    $document = $service->submit($this->recorder, $this->payload);
    $service->transition($document, $this->checker, 'check', null);
    $service->transition($document, $this->approver, 'approve', null);
    $this->payload['lines'][0]['quantity_utilized'] = 2;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
});

test('date cutoff excludes subsequent releases and attestation is required', function () {
    $this->payload['liquidation_date'] = '2026-10-08';
    $this->payload['return_slip_date'] = '2026-10-08';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
    $this->payload['liquidation_date'] = '2026-10-09';
    $this->payload['return_slip_date'] = '2026-10-09';
    $this->payload['attested'] = false;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('attested');
});

test('employees cannot submit review or print issuance liquidation', function () {
    $document = app(DepartmentLiquidationService::class)->submit($this->recorder, $this->payload);
    $this->actingAs(User::factory()->employee()->create())->post(route('inventory.department_liquidations.store'), $this->payload)->assertForbidden();
    $this->patch(route('inventory.department_liquidations.transition', $document), ['action' => 'check', 'attested' => true])->assertForbidden();
    $this->get(route('inventory.department_liquidations.print', $document))->assertForbidden();
    $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page->has('issuanceAllocations', 0)->has('liquidationDocuments', 0));
});

test('materials preview recalculates only the selected issuance slip for the selected date', function () {
    $this->getJson(route('inventory.department_liquidations.allocations', ['issuance_slip_id' => $this->slip->id, 'date' => '2026-10-09']))
        ->assertOk()->assertJsonPath('lines.0.quantity_issued', 4);
    $this->getJson(route('inventory.department_liquidations.allocations', ['issuance_slip_id' => $this->slip->id, 'date' => '2026-10-08']))
        ->assertOk()->assertJsonPath('lines', []);
    $this->getJson(route('inventory.department_liquidations.allocations', ['issuance_slip_id' => $this->slip->id, 'date' => '2026-10-10']))
        ->assertUnprocessable();
    $this->actingAs(User::factory()->employee()->create())->getJson(route('inventory.department_liquidations.allocations', ['issuance_slip_id' => $this->slip->id, 'date' => '2026-10-09']))->assertForbidden();
});

test('all issued lines are required even when two lines contain the same catalog material', function () {
    $line = $this->request->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => 'Separate purpose', 'unit_of_measure' => $this->item->unit_of_measure, 'quantity_requested' => 2, 'quantity_released' => 2]);
    $stockOut = app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'supply_request_line_id' => $line->id]);
    $stockOut->issuanceSlip()->associate($this->slip);
    $stockOut->save();
    $this->slip->update(['lines' => [...$this->slip->lines, ['supply_request_line_id' => $line->id, 'item_description' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure, 'quantity_issued' => 2]]]);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('lines');
    $this->payload['lines'][] = ['supply_request_line_id' => $line->id, 'quantity_utilized' => 1];
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->lines)->toHaveCount(2);
});

test('approved legacy per release usage remains attributed to its issuance slip', function () {
    $this->slip->update([
        'status' => 'approved',
        'lines' => [['supply_request_line_id' => $this->line->id, 'item_description' => $this->line->item_name, 'unit_of_measure' => $this->line->unit_of_measure, 'quantity_issued' => 4, 'quantity_utilized' => 2]],
    ]);
    $allocation = app(DepartmentLiquidationService::class)->allocation($this->slip, '2026-10-09');
    expect($allocation['lines'][0]['previously_utilized'])->toBe(2);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->lines[0]['previously_utilized'])->toBe(2);
});

test('the required return slip is stored separately and does not reduce liquidation balance', function () {
    $this->payload['return_slip_no'] = 'RS-001';
    $this->payload['return_slip_date'] = '2026-10-09';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    $document = DepartmentLiquidation::query()->sole();
    expect($document->return_slip_no)->toBe('RS-001')->and($document->lines[0]['balance'])->toBe(1)->and($this->item->fresh()->quantity)->toBe(20);
    $this->get(route('inventory.department_liquidations.print', $document))->assertOk()->assertSee('RS-001');
});

test('return slip references require a date within issuance and liquidation', function () {
    $this->payload['return_slip_no'] = 'RS-001';
    unset($this->payload['return_slip_date']);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('return_slip_date');
    $this->payload['return_slip_date'] = '2026-10-08';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('return_slip_date');
});

test('RIS liquidation requires a return slip reference', function () {
    $this->payload['return_slip_no'] = '';
    $this->payload['return_slip_date'] = '';
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors(['return_slip_no', 'return_slip_date']);
    expect(DepartmentLiquidation::query()->count())->toBe(0);
});

test('a request id cannot substitute for the required issuance slip link', function () {
    $unreleased = SupplyRequest::factory()->create(['irf_no' => '10-09-003']);
    $this->payload['supply_request_id'] = $unreleased->id;
    unset($this->payload['issuance_slip_id']);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('issuance_slip_id');
    $this->payload['issuance_slip_id'] = 999999;
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('issuance_slip_id');
});

test('the RIS return slip reference is saved on only its selected request and carried into liquidation', function () {
    $this->patch(route('inventory.requests.return_slip', $this->request), ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => true])->assertSessionHasNoErrors();
    expect($this->request->fresh()->return_slip_no)->toBe('RS-100')->and($this->otherRequest->fresh()->return_slip_no)->toBeNull()->and($this->item->fresh()->quantity)->toBe(20);
    $this->assertDatabaseHas('supply_request_actions', ['supply_request_id' => $this->request->id, 'action' => 'record_return_slip']);
    $allocation = app(DepartmentLiquidationService::class)->allocation($this->slip, '2026-10-09');
    $this->payload['return_slip_no'] = $allocation['return_slip_no'];
    $this->payload['return_slip_date'] = $allocation['return_slip_date'];
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasNoErrors();
    expect(DepartmentLiquidation::query()->sole()->return_slip_no)->toBe('RS-100')->and(DepartmentLiquidation::query()->sole()->lines[0]['balance'])->toBe(1);
});

test('return slip references require attestation and cannot precede an actual release', function () {
    $route = route('inventory.requests.return_slip', $this->request);
    $this->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => false])->assertSessionHasErrors('attested');
    $this->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-08', 'attested' => true])->assertSessionHasErrors('return_slip_date');
    $this->actingAs(User::factory()->employee()->create())->patch($route, ['return_slip_no' => 'RS-100', 'return_slip_date' => '2026-10-09', 'attested' => true])->assertForbidden();
});

test('new slip linked liquidations remain visible from the originating request without storing an IRF link', function () {
    $document = app(DepartmentLiquidationService::class)->submit($this->recorder, $this->payload);
    $this->actingAs($this->request->requester)->get(route('inventory.requests.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('liquidationDocuments', 1)
            ->where('liquidationDocuments.0.issuance_slip_id', $this->slip->id)
            ->where('liquidationDocuments.0.supply_request_id', null)
            ->where('liquidationDocuments.0.irf_no', null)
            ->where('liquidationDocuments.0.source_supply_request_id', $this->request->id));
    $this->actingAs($this->otherRequest->requester)->get(route('inventory.requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('liquidationDocuments', 0));
});

test('historical request liquidation is not backfilled or counted again for a slip', function () {
    $allocation = app(DepartmentLiquidationService::class)->allocation($this->slip, '2026-10-09');
    $historical = DepartmentLiquidation::query()->create([
        'supply_request_id' => $this->request->id, 'irf_no' => $this->request->irf_no,
        'document_no' => 'LEGACY-1', 'liquidation_date' => '2026-10-09', 'department' => 'Operations',
        'requested_by_user_id' => $this->recorder->id, 'requested_by_name' => $this->recorder->name,
        'status' => 'approved', 'lines' => array_map(fn (array $line): array => [...$line, 'quantity_utilized' => 3, 'balance' => 1, 'usage_details' => null], $allocation['lines']), 'history' => [],
    ]);
    $this->post(route('inventory.department_liquidations.store'), $this->payload)->assertSessionHasErrors('issuance_slip_id');
    expect($historical->fresh()->issuance_slip_id)->toBeNull()->and($historical->fresh()->irf_no)->toBe($this->request->irf_no)
        ->and(DepartmentLiquidation::query()->count())->toBe(1)->and($this->item->fresh()->quantity)->toBe(20);
    $this->get(route('inventory.department_liquidations.print', $historical))->assertOk()->assertSee('Historical request-linked liquidation');
});

test('IRF and issuance slip numbers use independent counters and explicit record links', function () {
    $this->post(route('inventory.requests.store'), [
        'issuance_form' => true, 'purpose' => 'Separate request and issue sequences', 'responsibility_center_code' => '',
        'lines' => [['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => $this->item->unit_of_measure, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();
    $request = SupplyRequest::query()->latest('id')->first();
    $slip = createLiquidationTestIssue($request, $request->lines()->sole(), $this->recorder, 1);
    expect($request->irf_no)->toBe('10-09-001')->and($slip->slip_no)->toBe('10-09-003')
        ->and($slip->supply_request_id)->toBe($request->id)->and($slip->snapshot['irf_no'])->toBe($request->irf_no)
        ->and($this->slip->fresh()->slip_no)->toBe('10-09-001');
});

test('liquidation UI selects issuance slips and keeps request scope as navigation only', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/IssuanceLiquidation.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});
