<?php

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnAction;
use App\Models\InventoryReturnAllocation;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryCountService;
use App\Services\InventoryReportService;
use App\Services\InventoryReturnService;
use App\Services\InventoryStockService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->recorder = User::factory()->inventoryManager()->create();
    $this->verifier = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main stockroom']);
    $this->manager = HrisReference::factory()->create(['type' => 'employee', 'name' => 'Department Manager', 'is_active' => true]);
    $this->employee = HrisReference::factory()->create(['type' => 'employee', 'name' => 'Returning Employee', 'is_active' => true]);
    $this->item = InventoryItem::factory()->create(['name' => 'Returned supplies', 'quantity' => 0, 'unit_of_measure' => 'pc']);
    $stock = app(InventoryStockService::class);
    $stock->stockIn($this->item, ['quantity' => 10, 'unit_cost' => 20, 'received_at' => '2026-10-01', 'expiration_date' => '2027-10-01', 'reference_no' => 'RR-1']);
    $stock->stockIn($this->item, ['quantity' => 10, 'unit_cost' => 30, 'received_at' => '2026-10-02', 'expiration_date' => '2027-10-02', 'reference_no' => 'RR-2']);
    $this->request = SupplyRequest::factory()->create(['status' => 'released']);
    $this->requestLine = SupplyRequestLine::factory()->create(['supply_request_id' => $this->request->id, 'inventory_item_id' => $this->item->getKey(), 'quantity_released' => 12]);
    $this->issue = $stock->stockOut($this->item, ['quantity' => 12, 'recipient_name' => $this->employee->name, 'recipient_reference_id' => $this->employee->id, 'department_name' => 'Operations', 'ris_no' => 'RIS-RETURN-1', 'stocked_out_at' => '2026-10-03', 'supply_request_line_id' => $this->requestLine->id]);
});

function returnIntake(array $overrides = []): array
{
    return ['idempotency_key' => (string) Str::uuid(), 'source_mode' => 'linked', 'return_date' => '2026-10-10', 'department_name' => 'Operations',
        'reason' => 'Unused materials endorsed for return.', 'attested' => true,
        'lines' => [['source_allocation_id' => test()->issue->allocations->first()->getKey(), 'quantity' => 4]], ...$overrides];
}

function submitMaterialReturn(array $overrides = []): InventoryReturn
{
    test()->actingAs(test()->recorder)->post(route('inventory.returns.store'), returnIntake($overrides))->assertRedirect()->assertSessionHasNoErrors();

    return InventoryReturn::query()->latest('id')->firstOrFail();
}

function acknowledgeMaterialReturn(InventoryReturn $document): void
{
    if ($document->fresh()->workflow_version < 2) {
        return;
    }
    test()->actingAs(test()->recorder)->patch(route('inventory.returns.transition', $document), [
        'action' => 'note', 'manager_reference_id' => test()->manager->id, 'remarks' => 'Department Manager acknowledged the signed RMS.', 'attested' => true,
    ])->assertSessionHasNoErrors();
    test()->actingAs(test()->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'receive', 'received_date' => '2026-10-12', 'remarks' => 'PMMD received the declared materials.', 'attested' => true,
    ])->assertSessionHasNoErrors();
}

function inspectMaterialReturn(InventoryReturn $document, int $accepted = 3, array $overrides = []): void
{
    if ($document->fresh()->status === 'submitted') {
        acknowledgeMaterialReturn($document);
    }
    test()->actingAs(test()->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Checked physical materials and evidence.', 'inspection_reference' => 'INSPECT-1', 'attested' => true,
        'lines' => $document->lines()->get()->map(fn ($line) => ['line_id' => $line->id, 'quantity_accepted' => $accepted, 'condition' => 'good', 'warehouse_id' => test()->warehouse->id, 'storage_location' => 'Shelf A1', 'inspection_remarks' => 'Serviceable accepted quantities; remainder rejected.'])->all(),
        ...$overrides,
    ])->assertSessionHasNoErrors();
}

function decideMaterialReturn(InventoryReturn $document, string $action, ?User $actor = null): TestResponse
{
    return test()->actingAs($actor ?? test()->approver)->patch(route('inventory.returns.transition', $document), ['action' => $action, 'remarks' => 'Decision supported by signed evidence.', 'attested' => true]);
}

test('return routes authorize managers and expose linked issued allocations', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->get(route('inventory.returns.index'))->assertForbidden();
    $this->post(route('inventory.returns.store'), returnIntake())->assertForbidden();
    $this->actingAs($this->recorder)->get(route('inventory.returns.create', ['supply_request_id' => $this->request->id]))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Index')->has('documents.data')->has('preparation.sources.data', 2)->where('preparation.sources.data.0.available_quantity', 2)->where('preparation.filters.supply_request_id', (string) $this->request->id));
    $document = submitMaterialReturn();
    $this->get(route('inventory.returns.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Index')->has('documents.data', 1));
    $this->get(route('inventory.returns.show', $document))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Show')->has('document.lines', 1)->where('allowedActions', ['note', 'reject', 'cancel']));
    $this->actingAs($employee)->get(route('inventory.returns.print', $document))->assertForbidden();
    $this->patch(route('inventory.returns.transition', $document), ['action' => 'cancel', 'remarks' => 'Unauthorized', 'attested' => true])->assertForbidden();
});

test('submission and inspection do not post stock and approval preserves original batch cost and expiry', function () {
    $document = submitMaterialReturn();
    expect($this->item->fresh()->quantity)->toBe(8)->and($document->returned_by_reference_id)->toBe($this->employee->id);
    inspectMaterialReturn($document, 3);
    expect($this->item->fresh()->quantity)->toBe(8)->and($document->fresh()->status)->toBe('verified');
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $posting = $document->lines()->first()->posting()->with('batch')->firstOrFail();
    expect($this->item->fresh()->quantity)->toBe(11)->and($posting->quantity)->toBe(3)->and($posting->unit_cost)->toBe('20.00')
        ->and($posting->batch->expiration_date->toDateString())->toBe('2027-10-01')->and($posting->batch->received_at->toDateString())->toBe('2026-10-12')
        ->and($posting->batch->reference_no)->toBe($document->document_no)
        ->and($this->issue->fresh()->quantity)->toBe(12)->and($this->requestLine->fresh()->quantity_released)->toBe(12);
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('action');
    expect(InventoryReturnAllocation::query()->count())->toBe(1)->and($this->item->fresh()->quantity)->toBe(11);
});

test('different issue batches restore their individual costs instead of an average', function () {
    $document = submitMaterialReturn(['lines' => $this->issue->allocations->map(fn ($allocation) => ['source_allocation_id' => $allocation->getKey(), 'quantity' => 2])->all()]);
    inspectMaterialReturn($document, 2);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($document->lines()->with('posting')->get()->map(fn ($line) => $line->posting->unit_cost)->all())->toBe(['20.00', '30.00'])
        ->and($this->item->fresh()->quantity)->toBe(12);
});

test('pending and approved returns cannot exceed the original issue and rejected remainders release commitments', function () {
    $document = submitMaterialReturn(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 10]]]);
    $this->post(route('inventory.returns.store'), returnIntake())->assertSessionHasErrors('lines');
    inspectMaterialReturn($document, 6);
    $other = submitMaterialReturn();
    expect(InventoryReturn::query()->count())->toBe(2);
    decideMaterialReturn($other, 'cancel', $this->recorder)->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $this->post(route('inventory.returns.store'), returnIntake(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 5]]]))->assertSessionHasErrors('lines');
    submitMaterialReturn();
    expect($this->item->fresh()->quantity)->toBe(14);
});

test('idempotent submission retries preserve one document and mismatched retries are rejected', function () {
    $data = returnIntake();
    $this->actingAs($this->recorder)->post(route('inventory.returns.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('inventory.returns.store'), $data)->assertSessionHasNoErrors();
    expect(InventoryReturn::query()->count())->toBe(1)->and(InventoryReturnAction::query()->count())->toBe(1);
    $this->post(route('inventory.returns.store'), [...$data, 'reason' => 'Changed content'])->assertSessionHasErrors('idempotency_key');
});

test('the recorder accountable employee and verifier cannot approve their own return', function () {
    $document = submitMaterialReturn();
    acknowledgeMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('action');
    $verify = ['action' => 'verify', 'remarks' => 'Self inspection', 'attested' => true, 'inspection_reference' => 'I-1', 'lines' => [['line_id' => $document->lines()->first()->id, 'quantity_accepted' => 1, 'condition' => 'good', 'storage_location' => 'A', 'inspection_remarks' => 'Checked']]];
    $this->actingAs($this->recorder)->patch(route('inventory.returns.transition', $document), $verify)->assertSessionHasErrors('action');
    $returningActor = User::factory()->inventoryManager()->create(['hris_reference_id' => $this->employee->id]);
    $this->actingAs($returningActor)->patch(route('inventory.returns.transition', $document), $verify)->assertSessionHasErrors('action');
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve', $this->verifier)->assertSessionHasErrors('action');
    decideMaterialReturn($document, 'approve', $returningActor)->assertSessionHasErrors('action');
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
});

test('inspection rejects defective expired excess and foreign line acceptance', function (string $failure) {
    $document = submitMaterialReturn();
    $line = $document->lines()->first();
    if ($failure === 'expired') {
        $line->update(['expiration_date' => '2026-10-11']);
    }
    acknowledgeMaterialReturn($document);
    $inspection = ['line_id' => $line->id, 'quantity_accepted' => $failure === 'excess' ? 5 : 1, 'condition' => $failure === 'defective' ? 'defective' : 'good', 'warehouse_id' => $failure === 'location' ? null : $this->warehouse->id, 'storage_location' => 'A1', 'inspection_remarks' => 'Observed'];
    if ($failure === 'foreign') {
        $inspection['line_id'] = $line->id + 999;
    }
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), ['action' => 'verify', 'remarks' => 'Inspected', 'inspection_reference' => 'I-1', 'attested' => true, 'lines' => [$inspection]])->assertSessionHasErrors('lines');
    expect($document->fresh()->status)->toBe('received')->and($this->item->fresh()->quantity)->toBe(8)->and($line->fresh()->quantity_accepted)->toBeNull();
})->with(['defective', 'expired', 'excess', 'foreign', 'location']);

test('wholly rejected materials preserve findings without creating usable stock', function () {
    $document = submitMaterialReturn();
    inspectMaterialReturn($document, 0);
    expect($document->fresh()->status)->toBe('rejected')->and($this->item->fresh()->quantity)->toBe(8)->and($document->lines()->first()->quantity_committed)->toBe(0);
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('action');
    submitMaterialReturn(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 10]]]);
});

test('linked returns reject altered costs wrong department early dates and mixed accountable persons', function () {
    $this->actingAs($this->recorder)->post(route('inventory.returns.store'), returnIntake(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 1, 'unit_cost' => 999]]]))->assertSessionHasErrors('lines.0.unit_cost');
    $this->post(route('inventory.returns.store'), returnIntake(['department_name' => 'Wrong department']))->assertSessionHasErrors('department_name');
    $this->post(route('inventory.returns.store'), returnIntake(['return_date' => '2026-10-02']))->assertSessionHasErrors('return_date');
    $other = app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 1, 'recipient_name' => 'Another person', 'department_name' => 'Operations', 'stocked_out_at' => '2026-10-03']);
    $this->post(route('inventory.returns.store'), returnIntake(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 1], ['source_allocation_id' => $other->allocations->first()->getKey(), 'quantity' => 1]]]))->assertSessionHasErrors('lines');
    expect(InventoryReturn::query()->count())->toBe(0);
});

test('legacy returns require issue evidence and valuation basis and use independent approval', function () {
    $legacy = ['source_mode' => 'legacy', 'returned_by_name' => 'Legacy accountable employee', 'lines' => [['inventory_item_id' => $this->item->getKey(), 'quantity' => 2, 'unit_cost' => 12.5]]];
    $this->actingAs($this->recorder)->post(route('inventory.returns.store'), returnIntake($legacy))->assertSessionHasErrors(['legacy_issue_reference', 'lines.0.valuation_basis']);
    $legacy['legacy_issue_reference'] = 'SIGNED-OLD-IS-1';
    $legacy['lines'][0]['valuation_basis'] = 'Original signed stock card cost under OLD-IS-1';
    $document = submitMaterialReturn($legacy);
    inspectMaterialReturn($document, 2);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($document->lines()->first()->source_allocation_id)->toBeNull()->and($document->lines()->first()->posting->unit_cost)->toBe('12.50')->and($this->item->fresh()->quantity)->toBe(10);
});

test('count cutoff prevents return posting atomically until the count is cancelled', function () {
    $document = submitMaterialReturn();
    inspectMaterialReturn($document);
    $count = app(InventoryCountService::class)->create(['title' => 'Active count', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'M-1', 'team_details' => 'Authorized count team'], $this->recorder);
    $this->actingAs($this->recorder)->patch(route('inventory.counts.transition', $count), ['action' => 'start', 'attested' => true])->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('quantity');
    expect($document->fresh()->status)->toBe('verified')->and(InventoryReturnAllocation::query()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(8);
    $this->actingAs($this->recorder)->patch(route('inventory.counts.transition', $count), ['action' => 'cancel', 'remarks' => 'Rescheduled count', 'attested' => true])->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
});

test('approval rejects archived or newly expired stock without partially posting', function (string $failure) {
    $document = submitMaterialReturn();
    inspectMaterialReturn($document);
    if ($failure === 'archived') {
        $this->item->delete();
    } else {
        $document->lines()->first()->update(['expiration_date' => '2026-10-11']);
    }
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('action');
    expect($document->fresh()->status)->toBe('verified')->and(InventoryReturnAllocation::query()->count())->toBe(0);
})->with(['archived', 'expired']);

test('return journal uses approval date and supplier receipt reports exclude returned stock', function () {
    $document = submitMaterialReturn();
    $filters = ['as_of' => '2026-10-12', 'period' => 'monthly', 'period_label' => 'October', 'year' => 2026, 'from' => '2026-10-12', 'to' => '2026-10-12', 'fund_cluster' => '01', 'custodian' => null];
    $reports = app(InventoryReportService::class);
    expect($reports->printable('return-register', $filters)['rows'])->toHaveCount(0);
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $rows = $reports->printable('return-journal', $filters)['rows'];
    expect($rows)->toHaveCount(1)->and($rows->first())->toContain('2026-10-10', '2026-10-12', '60.00');
    expect($reports->printable('receipt-register', $filters)['rows'])->toHaveCount(0);
    $ledger = $reports->printable('stock-card', $filters)['rows'];
    expect(collect($ledger)->flatten()->all())->toContain('Return to stock');
    expect($reports->printable('return-journal', [...$filters, 'as_of' => '2026-10-11'])['rows'])->toHaveCount(0);
});

test('RMS prints supporting findings and accounting endorsement is recorded once without reposting', function () {
    $document = submitMaterialReturn();
    $this->get(route('inventory.returns.print', $document))->assertOk()->assertSee('WORKING DOCUMENT')->assertSee('RETURN MATERIALS SLIP');
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $data = ['action' => 'endorse_accounting', 'remarks' => 'Forwarded signed RMS to Accounting.', 'accounting_reference' => 'ACCT-RMS-1', 'accounting_recipient' => 'Accounting Unit', 'attested' => true];
    $this->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasNoErrors();
    $this->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasErrors('action');
    $this->get(route('inventory.returns.print', $document))->assertOk()->assertDontSee('WORKING DOCUMENT')->assertSee('ACCT-RMS-1');
    expect($this->item->fresh()->quantity)->toBe(11)->and($document->fresh()->accounting_endorsed_at)->not->toBeNull();
});

test('return postings and action evidence cannot be rewritten or deleted', function () {
    $document = submitMaterialReturn();
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $posting = $document->lines()->first()->posting;
    expect(fn () => $posting->update(['quantity' => 99]))->toThrow(LogicException::class);
    expect(fn () => $posting->delete())->toThrow(LogicException::class);
    $action = $document->actions()->first();
    expect(fn () => $action->update(['remarks' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $action->delete())->toThrow(LogicException::class);
});

test('manual legacy accountable names also prevent self approval', function () {
    $document = submitMaterialReturn(['source_mode' => 'legacy', 'returned_by_name' => $this->approver->name, 'legacy_issue_reference' => 'OLD-IS-2', 'lines' => [['inventory_item_id' => $this->item->getKey(), 'quantity' => 2, 'unit_cost' => 15, 'valuation_basis' => 'Signed original issue costing']]]);
    inspectMaterialReturn($document, 2);
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('action');
    expect(app(InventoryReturnService::class)->allowedActions($document->fresh(), $this->approver))->toBe(['reject', 'cancel']);
});

test('invalid source allocations and inspection without evidence are rejected by validation', function () {
    $this->actingAs($this->recorder)->post(route('inventory.returns.store'), returnIntake(['lines' => [['source_allocation_id' => 99999, 'quantity' => 1]]]))->assertSessionHasErrors('lines.0.source_allocation_id');
    $document = submitMaterialReturn();
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), ['action' => 'verify', 'attested' => true])->assertSessionHasErrors(['remarks', 'lines']);
    expect($document->fresh()->status)->toBe('submitted')->and($this->item->fresh()->quantity)->toBe(8);
});

test('RMS dialog submission stays in preparation and validation preserves the form context', function () {
    $prepareUrl = route('inventory.returns.create', ['supply_request_id' => $this->request->id]);
    $this->actingAs($this->recorder)->from($prepareUrl)->withHeader('X-RMS-Dialog', 'true')
        ->post(route('inventory.returns.store'), returnIntake(['reason' => '']))
        ->assertRedirect($prepareUrl)->assertSessionHasErrors('reason');
    expect(InventoryReturn::query()->count())->toBe(0);
    $payload = returnIntake();
    $this->post(route('inventory.returns.store'), $payload)->assertRedirect($prepareUrl)->assertSessionHasNoErrors();
    $this->post(route('inventory.returns.store'), $payload)->assertRedirect($prepareUrl)->assertSessionHasNoErrors();
    expect(InventoryReturn::query()->count())->toBe(1)->and($this->item->fresh()->quantity)->toBe(8);
    $this->get($prepareUrl)->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Index')->has('preparation.sources.data'));
    $this->get(route('inventory.returns.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Index')->missing('preparation')->has('documents.data', 1));
});

test('request RMS preparation returns only its own issued batches and rejects cross-request submissions', function () {
    $otherRequest = SupplyRequest::factory()->create(['status' => 'released']);
    $otherLine = SupplyRequestLine::factory()->create(['supply_request_id' => $otherRequest->id, 'inventory_item_id' => $this->item->getKey(), 'quantity_released' => 1]);
    $otherIssue = app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 1, 'recipient_name' => $this->employee->name, 'recipient_reference_id' => $this->employee->id, 'department_name' => 'Operations', 'ris_no' => 'OTHER-REQUEST', 'stocked_out_at' => '2026-10-03', 'supply_request_line_id' => $otherLine->id]);
    $url = route('inventory.returns.create', ['supply_request_id' => $this->request->id]);
    $response = $this->actingAs($this->recorder)->getJson($url)->assertSuccessful()->assertJsonPath('supplyRequest.id', $this->request->id)->assertJsonCount(2, 'sources.data');
    expect(collect($response->json('sources.data'))->pluck('id')->all())->not->toContain($otherIssue->allocations->first()->getKey());
    $this->getJson($url.'&search=OTHER-REQUEST')->assertSuccessful()->assertJsonCount(0, 'sources.data');
    $this->post(route('inventory.returns.store'), returnIntake(['supply_request_id' => $this->request->id, 'lines' => [['source_allocation_id' => $otherIssue->allocations->first()->getKey(), 'quantity' => 1]]]))->assertSessionHasErrors('lines');
    expect(InventoryReturn::query()->count())->toBe(0);
    $this->post(route('inventory.returns.store'), returnIntake(['supply_request_id' => $this->request->id, 'source_mode' => 'legacy']))->assertSessionHasErrors('source_mode');
    $document = submitMaterialReturn(['supply_request_id' => $this->request->id]);
    expect($document->lines()->first()->source_supply_request_id)->toBe($this->request->id)->and($this->item->fresh()->quantity)->toBe(7);
    $this->post(route('inventory.returns.store'), returnIntake(['supply_request_id' => $this->request->id]))
        ->assertSessionHasErrors('supply_request_id');
    expect(InventoryReturn::query()->count())->toBe(1);
    $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->where('requests.data.0.has_active_return', true));
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->getJson($url)->assertForbidden();
});

test('one RMS progresses through manager acknowledgment receipt inspection and stockroom posting', function () {
    $document = submitMaterialReturn(['supply_request_id' => $this->request->id]);
    $number = $document->document_no;
    expect($document->workflow_version)->toBe(2);
    decideMaterialReturn($document, 'receive')->assertSessionHasErrors('received_date');
    $inspection = ['lines' => [['line_id' => $document->lines()->first()->id, 'quantity_accepted' => 3, 'condition' => 'good', 'warehouse_id' => $this->warehouse->id, 'inspection_remarks' => 'Serviceable']]];
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Inspected', 'attested' => true, ...$inspection,
    ])->assertSessionHasErrors('action');
    acknowledgeMaterialReturn($document);
    expect($document->fresh()->status)->toBe('received')->and($this->item->fresh()->quantity)->toBe(8);
    $this->post(route('inventory.returns.store'), returnIntake(['supply_request_id' => $this->request->id]))->assertSessionHasErrors('supply_request_id');
    inspectMaterialReturn($document, 3, ['inspection_reference' => null]);
    expect($document->fresh()->inspection_reference)->toBe($number)
        ->and($document->lines()->first()->posting)->toBeNull();
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    $line = $document->lines()->with('posting.batch')->firstOrFail();
    $placement = $line->posting->batch->placements()->sole();
    expect($document->fresh()->document_no)->toBe($number)
        ->and($document->actions()->pluck('action')->all())->toBe(['submit', 'note', 'receive', 'verify', 'approve'])
        ->and($placement->warehouse_id)->toBe($this->warehouse->id)
        ->and($placement->warehouse)->toBe('Main stockroom')
        ->and($placement->area)->toBe('Shelf A1')
        ->and($placement->source_evidence_reference)->toBe($number)
        ->and($placement->observed_quantity)->toBe(3);
    $this->get(route('inventory.returns.print', $document))->assertSuccessful()
        ->assertSee('RETURN MATERIALS SLIP')->assertSee('Description of Item')->assertSee('Purpose / Reason for return')
        ->assertSee('Department Manager')->assertSee($this->manager->name)->assertSee('PMMD Office')->assertSee('Main stockroom');
    $this->get(route('inventory.returns.index', ['status' => 'approved']))->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
});

test('manager acknowledgment requires a valid independent manager and cannot be repeated', function () {
    $document = submitMaterialReturn();
    $data = ['action' => 'note', 'remarks' => 'Acknowledged signed RMS', 'attested' => true];
    $this->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasErrors('manager_reference_id');
    $this->patch(route('inventory.returns.transition', $document), [...$data, 'manager_reference_id' => $this->employee->id])->assertSessionHasErrors('manager_reference_id');
    $this->patch(route('inventory.returns.transition', $document), [...$data, 'manager_reference_id' => $this->manager->id])->assertSessionHasNoErrors();
    expect($document->fresh()->status)->toBe('noted');
    $this->patch(route('inventory.returns.transition', $document), [...$data, 'manager_reference_id' => $this->manager->id])->assertSessionHasErrors('action');
    $this->manager->update(['name' => 'Renamed Manager']);
    expect($document->actions()->where('action', 'note')->sole()->metadata['manager_name'])->toBe('Department Manager');
    $this->get(route('inventory.returns.index', ['status' => 'noted']))->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
});

test('PMMD receipt rejects future dates early dates and the returning end-user', function () {
    $document = submitMaterialReturn();
    $this->patch(route('inventory.returns.transition', $document), ['action' => 'note', 'manager_reference_id' => $this->manager->id, 'remarks' => 'Manager acknowledged', 'attested' => true])->assertSessionHasNoErrors();
    $data = ['action' => 'receive', 'received_date' => '2026-10-12', 'remarks' => 'Physical receipt', 'attested' => true];
    $this->patch(route('inventory.returns.transition', $document), [...$data, 'received_date' => '2026-10-13'])->assertSessionHasErrors('received_date');
    $this->patch(route('inventory.returns.transition', $document), [...$data, 'received_date' => '2026-10-09'])->assertSessionHasErrors('received_date');
    $returner = User::factory()->inventoryManager()->create(['hris_reference_id' => $this->employee->id]);
    $this->actingAs($returner)->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasErrors('action');
    expect(app(InventoryReturnService::class)->allowedActions($document->fresh(), $returner))->toBe(['reject', 'cancel']);
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasNoErrors();
    $this->patch(route('inventory.returns.transition', $document), $data)->assertSessionHasErrors('action');
    $this->get(route('inventory.returns.index', ['status' => 'received']))->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
});

test('stockroom deletion after inspection rolls back the whole approval', function () {
    $document = submitMaterialReturn();
    inspectMaterialReturn($document);
    $this->warehouse->delete();
    decideMaterialReturn($document, 'approve')->assertSessionHasErrors('warehouse_id');
    expect($this->item->fresh()->quantity)->toBe(8)
        ->and($document->fresh()->status)->toBe('verified')
        ->and(InventoryReturnAllocation::query()->count())->toBe(0)
        ->and($document->actions()->where('action', 'approve')->count())->toBe(0);
});

test('accepted materials can use a stockroom without a shelf and rejected materials need no stockroom', function () {
    $document = submitMaterialReturn();
    acknowledgeMaterialReturn($document);
    $line = $document->lines()->firstOrFail();
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Accepted at receiving area', 'attested' => true,
        'lines' => [['line_id' => $line->id, 'quantity_accepted' => 2, 'condition' => 'good', 'warehouse_id' => $this->warehouse->id, 'inspection_remarks' => 'Serviceable']],
    ])->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($line->fresh()->posting->batch->placements()->sole()->area)->toBe('Receiving area');
    $rejected = submitMaterialReturn();
    acknowledgeMaterialReturn($rejected);
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $rejected), [
        'action' => 'verify', 'remarks' => 'All rejected', 'attested' => true,
        'lines' => [['line_id' => $rejected->lines()->first()->id, 'quantity_accepted' => 0, 'condition' => 'defective', 'inspection_remarks' => 'Damaged']],
    ])->assertSessionHasNoErrors();
    expect($rejected->fresh()->status)->toBe('rejected');
});

test('returns prepared from an IS freeze its actual slip number and source values', function () {
    $slip = IssuanceSlip::query()->create([
        'supply_request_id' => $this->request->id, 'slip_no' => 'IS-10-03-001', 'issued_at' => '2026-10-03',
        'issued_by_user_id' => $this->recorder->id, 'snapshot' => [], 'lines' => [],
    ]);
    $this->issue->issuanceSlip()->associate($slip)->save();
    $this->actingAs($this->recorder)->getJson(route('inventory.returns.create', ['supply_request_id' => $this->request->id, 'search' => $slip->slip_no]))
        ->assertSuccessful()->assertJsonCount(2, 'sources.data')->assertJsonPath('sources.data.0.issue_reference', $slip->slip_no);
    $document = submitMaterialReturn(['supply_request_id' => $this->request->id]);
    expect($document->lines()->sole()->source_issue_reference)->toBe($slip->slip_no)
        ->and($document->returned_by_name)->toBe($this->employee->name)
        ->and($document->department_name)->toBe('Operations');
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($this->issue->fresh()->quantity)->toBe(12)->and($this->requestLine->fresh()->quantity_released)->toBe(12);
});

test('historical RMS records retain their old verification workflow without invented acknowledgments', function () {
    $document = submitMaterialReturn();
    $document->update(['workflow_version' => 1]);
    expect(app(InventoryReturnService::class)->allowedActions($document, $this->verifier))->toBe(['verify', 'reject', 'cancel']);
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Legacy inspection', 'attested' => true,
        'lines' => [['line_id' => $document->lines()->first()->id, 'quantity_accepted' => 1, 'condition' => 'good', 'storage_location' => 'Original stockroom evidence', 'inspection_remarks' => 'Legacy findings']],
    ])->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($document->actions()->pluck('action')->all())->toBe(['submit', 'verify', 'approve']);
    $this->get(route('inventory.returns.print', $document))->assertSuccessful()->assertSee('Not recorded');
});

test('acknowledged and received returns retain their source quantity commitments until cancellation', function (string $stage) {
    $document = submitMaterialReturn(['lines' => [['source_allocation_id' => $this->issue->allocations->first()->getKey(), 'quantity' => 10]]]);
    $this->patch(route('inventory.returns.transition', $document), ['action' => 'note', 'manager_reference_id' => $this->manager->id, 'remarks' => 'Acknowledged', 'attested' => true])->assertSessionHasNoErrors();
    if ($stage === 'received') {
        $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), ['action' => 'receive', 'received_date' => '2026-10-12', 'remarks' => 'Received', 'attested' => true])->assertSessionHasNoErrors();
    }
    $this->post(route('inventory.returns.store'), returnIntake())->assertSessionHasErrors('lines');
    decideMaterialReturn($document, 'cancel')->assertSessionHasNoErrors();
    submitMaterialReturn();
    expect($this->item->fresh()->quantity)->toBe(8);
})->with(['noted', 'received']);

test('a super admin recorder may verify a received RMS but cannot also approve it', function () {
    $this->recorder = User::factory()->superAdmin()->create();
    $document = submitMaterialReturn();
    acknowledgeMaterialReturn($document);
    expect(app(InventoryReturnService::class)->allowedActions($document->fresh(), $this->recorder))->toContain('verify');
    $this->actingAs($this->recorder)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Independent inspection', 'attested' => true,
        'lines' => [['line_id' => $document->lines()->first()->id, 'quantity_accepted' => 1, 'condition' => 'good', 'warehouse_id' => $this->warehouse->id, 'inspection_remarks' => 'Serviceable']],
    ])->assertSessionHasNoErrors();
    decideMaterialReturn($document, 'approve', $this->recorder)->assertSessionHasErrors('action');
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
});

test('RMS preparation and printing do not require or expose an endorsement letter', function () {
    expect(Schema::hasColumn('inventory_returns', 'endorsement_reference'))->toBeFalse();
    $document = submitMaterialReturn(['supply_request_id' => $this->request->id]);
    $this->get(route('inventory.returns.show', $document))->assertInertia(fn (Assert $page) => $page
        ->component('Inventory/Returns/Show')->missing('document.endorsement_reference'));
    $this->get(route('inventory.returns.print', $document))->assertSuccessful()->assertDontSee('Endorsement reference:');
    inspectMaterialReturn($document);
    decideMaterialReturn($document, 'approve')->assertSessionHasNoErrors();
    expect($document->fresh()->status)->toBe('approved');
});

test('RMS retains the selected purpose and detailed explanation together on the form and printout', function (string $reason) {
    $document = submitMaterialReturn(['reason' => $reason, 'supply_request_id' => $this->request->id]);
    expect($document->fresh()->reason)->toBe($reason);
    $this->get(route('inventory.returns.show', $document))->assertInertia(fn (Assert $page) => $page->where('document.reason', $reason));
    $this->get(route('inventory.returns.print', $document))->assertSuccessful()->assertSee($reason);
})->with([
    'common reason only' => 'Excess or unused materials',
    'common reason with details' => "Project or work completed\n\nPipe repair at Zone 3 was completed. Returning the unused fittings.",
    'custom purpose' => 'Returning sealed supplies after reassignment to another work site.',
]);

test('RMS print follows the supplied form and separates optional operational details', function () {
    $document = submitMaterialReturn();
    acknowledgeMaterialReturn($document);
    $response = $this->get(route('inventory.returns.print', $document))->assertSuccessful();
    $html = new DOMDocument;
    @$html->loadHTML($response->getContent());
    $xpath = new DOMXPath($html);
    $main = $xpath->query('//main')->item(0);
    expect($main)->not->toBeNull();
    $headers = $xpath->query('.//table[@class="materials"]/thead/tr/th', $main);
    expect(collect(iterator_to_array($headers))->map(fn (DOMNode $node): string => trim($node->textContent))->all())
        ->toBe(['No', 'Qty', 'UOM', 'Description of Item', 'Condition', 'Remarks']);
    expect($xpath->query('.//table[@class="materials"]/tbody/tr', $main)->length)->toBe(18)
        ->and($xpath->query('.//section[@aria-label="RMS signatories"]/div', $main)->length)->toBe(3)
        ->and($main->textContent)->toContain($document->document_no, $this->employee->name, $this->manager->name, $this->verifier->name)
        ->and($main->textContent)->not->toContain('Unit cost', 'Stock posting date:', 'Inspection reference:');
    $response->assertSee('print-letterhead-row')->assertSee('print-letterfoot-row')
        ->assertSee('Include inspection and posting appendix')->assertSee('215.9mm 330.2mm');
    expect($xpath->query('//section[contains(@class,"appendix")]//th')->length)->toBe(6);
});
