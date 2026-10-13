<?php

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnAction;
use App\Models\InventoryReturnAllocation;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\InventoryReportService;
use App\Services\InventoryReturnService;
use App\Services\InventoryStockService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->recorder = User::factory()->inventoryManager()->create();
    $this->verifier = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
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
        'endorsement_reference' => 'ENDORSE-1', 'reason' => 'Unused materials endorsed for return.', 'attested' => true,
        'lines' => [['source_allocation_id' => test()->issue->allocations->first()->getKey(), 'quantity' => 4]], ...$overrides];
}

function submitMaterialReturn(array $overrides = []): InventoryReturn
{
    test()->actingAs(test()->recorder)->post(route('inventory.returns.store'), returnIntake($overrides))->assertRedirect()->assertSessionHasNoErrors();

    return InventoryReturn::query()->latest('id')->firstOrFail();
}

function inspectMaterialReturn(InventoryReturn $document, int $accepted = 3, array $overrides = []): void
{
    test()->actingAs(test()->verifier)->patch(route('inventory.returns.transition', $document), [
        'action' => 'verify', 'remarks' => 'Checked physical materials and evidence.', 'inspection_reference' => 'INSPECT-1', 'attested' => true,
        'lines' => $document->lines()->get()->map(fn ($line) => ['line_id' => $line->id, 'quantity_accepted' => $accepted, 'condition' => 'good', 'storage_location' => 'Main stockroom A1', 'inspection_remarks' => 'Serviceable accepted quantities; remainder rejected.'])->all(),
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
    $this->actingAs($this->recorder)->get(route('inventory.returns.create', ['supply_request_id' => $this->request->id]))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Create')->has('sources.data', 2)->where('sources.data.0.available_quantity', 2));
    $document = submitMaterialReturn();
    $this->get(route('inventory.returns.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Index')->has('documents.data', 1));
    $this->get(route('inventory.returns.show', $document))->assertInertia(fn (Assert $page) => $page->component('Inventory/Returns/Show')->has('document.lines', 1)->where('allowedActions', ['reject', 'cancel']));
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
    $inspection = ['line_id' => $line->id, 'quantity_accepted' => $failure === 'excess' ? 5 : 1, 'condition' => $failure === 'defective' ? 'defective' : 'good', 'storage_location' => $failure === 'location' ? null : 'A1', 'inspection_remarks' => 'Observed'];
    if ($failure === 'foreign') {
        $inspection['line_id'] = $line->id + 999;
    }
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), ['action' => 'verify', 'remarks' => 'Inspected', 'inspection_reference' => 'I-1', 'attested' => true, 'lines' => [$inspection]])->assertSessionHasErrors('lines');
    expect($document->fresh()->status)->toBe('submitted')->and($this->item->fresh()->quantity)->toBe(8)->and($line->fresh()->quantity_accepted)->toBeNull();
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
    $this->get(route('inventory.returns.print', $document))->assertOk()->assertSee('WORKING DOCUMENT')->assertSee('Returned Materials Slip');
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
    $this->actingAs($this->verifier)->patch(route('inventory.returns.transition', $document), ['action' => 'verify', 'attested' => true])->assertSessionHasErrors(['remarks', 'inspection_reference', 'lines']);
    expect($document->fresh()->status)->toBe('submitted')->and($this->item->fresh()->quantity)->toBe(8);
});
