<?php

use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryDisposal;
use App\Models\User;
use App\SemiExpendablePhysicalStatus;
use App\Services\InventoryAssetService;
use App\Services\InventoryDisposalService;
use App\Services\InventoryReportService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    $this->preparer = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->people = HrisReference::factory()->count(5)->create();
    $this->workflow = app(InventoryDisposalService::class);
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 60000, 'acquisition_date' => '2025-01-01', 'condition_status' => AssetConditionStatus::NonUsable, 'lifecycle_status' => AssetLifecycleStatus::ForDisposal]);
});
function wmrInput(array $overrides = []): array
{
    return [...['place_of_storage' => 'Waste warehouse', 'method' => 'destroyed', 'reason' => 'Unserviceable equipment',
        'certifier_reference_id' => test()->people[0]->id, 'certification_reference' => 'SIGNED-CERT', 'certification_attestation' => 'Count and condition certified.',
        'lines' => [['inventory_asset_id' => test()->asset->getKey(), 'quantity' => test()->asset->quantity_per_property_card, 'unit' => 'unit', 'description' => 'Equipment', 'source_reference' => 'INSPECT-001']]], ...$overrides];
}
function wmrApproval(array $overrides = []): array
{
    return [...['action' => 'approve', 'attestation' => 'Committee approval verified.', 'chair_reference_id' => test()->people[1]->id, 'approval_no' => 'SIGNED-CHAIR', 'approval_date' => '2026-10-11'], ...$overrides];
}
function wmrCompletion(InventoryDisposal $document, array $overrides = []): array
{
    return [...['action' => 'complete', 'attestation' => 'Witnessed disposition and accounting confirmation verified.',
        'disposition_date' => '2026-10-11', 'disposition_reference' => 'DISPOSITION-001', 'inspector_reference_id' => test()->people[2]->id,
        'internal_control_reference_id' => test()->people[3]->id, 'accounting_witness_reference_id' => test()->people[4]->id,
        'inspection_certificate_reference' => 'INSPECTOR-SIGNED', 'internal_control_signature_reference' => 'CONTROL-SIGNED', 'accounting_signature_reference' => 'ACCOUNTING-SIGNED', 'accounting_reference' => 'ACCOUNTING-CONFIRM',
        'lines' => $document->lines()->get()->map(fn ($line) => ['id' => $line->id, 'proceeds' => '0.00', 'or_number' => null])->all()], ...$overrides];
}
test('WMR approval and completion post whole property exactly once with frozen signatures', function (float $cost) {
    if ($cost < 50000) {
        $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost, 'condition_status' => 'non_usable', 'lifecycle_status' => 'for_disposal', 'physical_status' => SemiExpendablePhysicalStatus::ForDisposal]);
    }
    $this->actingAs($this->preparer)->post(route('inventory.disposals.store'), wmrInput())->assertSessionHasNoErrors()->assertRedirect();
    $document = InventoryDisposal::query()->sole();
    expect($this->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::ForDisposal);
    $this->get(route('inventory.disposals.show', $document))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('Inventory/Disposals/Show')->where('allowedActions', ['cancel']));
    $this->get(route('inventory.disposals.print', $document))->assertSuccessful()->assertSee('NOT A COMPLETED');
    $this->actingAs($this->approver)->patch(route('inventory.disposals.transition', $document), wmrApproval())->assertSessionHasNoErrors();
    $this->actingAs($this->preparer)->patch(route('inventory.disposals.transition', $document), wmrCompletion($document))->assertSessionHasNoErrors();
    expect($document->fresh()->status)->toBe('completed')->and($this->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::Disposed)
        ->and($document->lines()->sole()->proceeds)->toBe('0.00');
    $event = $this->asset->events()->where('event_type', InventoryAssetEventType::Disposal)->sole();
    expect($event->event_date->toDateString())->toBe('2026-10-12')->and($event->details['disposal_evidence']['disposition_date'])->toBe('2026-10-11')
        ->and($event->details['disposal_evidence']['accounting_reference'])->toBe('ACCOUNTING-CONFIRM');
    expect($this->asset->events()->where('event_type', InventoryAssetEventType::Derecognition)->count())->toBe($cost >= 50000 ? 1 : 0);
    $this->people[2]->update(['name' => 'Changed HRIS name']);
    $this->get(route('inventory.disposals.print', $document))->assertSuccessful()->assertSee('INSPECTOR-SIGNED')->assertDontSee('Changed HRIS name');
    $this->patch(route('inventory.disposals.transition', $document), wmrCompletion($document))->assertSessionHasErrors('disposal');
    expect($this->asset->events()->where('event_type', InventoryAssetEventType::Disposal)->count())->toBe(1);
    expect(fn () => app(InventoryAssetService::class)->updateState($this->asset, ['lifecycle_status' => 'active', 'condition_status' => 'good'], $this->preparer))->toThrow(InvalidArgumentException::class);
})->with([60000.0, 8000.0]);
test('standalone sold components preserve the entire parent asset ledger', function () {
    $before = $this->asset->fresh()->getAttributes();
    $document = $this->workflow->create(wmrInput(['method' => 'private_sale', 'lines' => [['inventory_asset_id' => null, 'quantity' => 1, 'unit' => 'assembly', 'description' => 'Removed alternator', 'source_reference' => $this->asset->property_number]]]), $this->preparer);
    $this->workflow->transition($document, wmrApproval(), $this->approver);
    $input = wmrCompletion($document, ['lines' => [['id' => $document->lines()->sole()->id, 'proceeds' => '1200.25', 'or_number' => 'OR-001']]]);
    $this->workflow->transition($document, $input, $this->preparer);
    expect($this->asset->fresh()->getAttributes())->toBe($before)->and($this->asset->events()->count())->toBe(0)->and($document->lines()->sole()->disposal_event_id)->toBeNull();
    $this->actingAs($this->preparer)->get(route('inventory.disposals.print', $document))->assertSee('Removed alternator')->assertSee('OR-001')->assertSee('1,200.25');
});
test('self approval and duplicate open registered WMR are rejected', function () {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    expect(fn () => $this->workflow->transition($document, wmrApproval(), $this->preparer))->toThrow(ValidationException::class);
    expect(fn () => $this->workflow->create(wmrInput(), $this->approver))->toThrow(ValidationException::class);
    expect(fn () => $this->workflow->transition($document, wmrApproval(['chair_reference_id' => $this->people[0]->id]), $this->approver))->toThrow(ValidationException::class);
    expect(InventoryDisposal::count())->toBe(1)->and($this->asset->events()->count())->toBe(0);
});
test('changed registered evidence requires cancellation and a corrected WMR', function () {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    $this->asset->update(['name' => 'Updated property']);
    expect(fn () => $this->workflow->transition($document, wmrApproval(), $this->approver))->toThrow(ValidationException::class);
    $this->workflow->transition($document, ['action' => 'cancel', 'attestation' => 'Correct source details.'], $this->preparer);
    expect($document->fresh()->status)->toBe('cancelled');
});
test('completion rejects invalid sale evidence and rolls back all lines', function (string $method, string $amount, ?string $receipt) {
    $document = $this->workflow->create(wmrInput(['method' => $method]), $this->preparer);
    $this->workflow->transition($document, wmrApproval(), $this->approver);
    $input = wmrCompletion($document, ['lines' => [['id' => $document->lines()->sole()->id, 'proceeds' => $amount, 'or_number' => $receipt]]]);
    expect(fn () => $this->workflow->transition($document, $input, $this->preparer))->toThrow(ValidationException::class);
    expect($document->fresh()->status)->toBe('approved')->and($document->lines()->sole()->proceeds)->toBeNull()->and($this->asset->events()->count())->toBe(0);
})->with([['private_sale', '0.00', 'OR-1'], ['public_auction', '10.00', null], ['destroyed', '10.00', null]]);
test('WMR denies missing signatures foreign lines and duplicate witnesses', function (string $problem) {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    $this->workflow->transition($document, wmrApproval(), $this->approver);
    $input = wmrCompletion($document);
    if ($problem === 'signature') {
        unset($input['accounting_signature_reference']);
    }
    if ($problem === 'foreign') {
        $input['lines'][0]['id'] = 99999;
    }
    if ($problem === 'witness') {
        $input['accounting_witness_reference_id'] = $input['internal_control_reference_id'];
    }
    $this->actingAs($this->preparer)->patch(route('inventory.disposals.transition', $document), $input)->assertSessionHasErrors();
    expect($document->fresh()->status)->toBe('approved')->and($this->asset->events()->count())->toBe(0);
})->with(['signature', 'foreign', 'witness']);
test('WMR rejects serviceable assigned partial or permanently transferred property', function (string $problem) {
    if ($problem === 'serviceable') {
        $this->asset->update(['condition_status' => 'good', 'lifecycle_status' => 'active']);
    }
    if ($problem === 'assigned') {
        $this->asset->update(['current_custodian_reference_id' => $this->people[0]->id]);
    }
    if ($problem === 'external') {
        $this->asset->update(['lifecycle_status' => 'transferred_out']);
    }
    $input = wmrInput();
    if ($problem === 'partial') {
        $input['lines'][0]['quantity'] = 2;
    }
    expect(fn () => $this->workflow->create($input, $this->preparer))->toThrow(ValidationException::class);
    expect(InventoryDisposal::count())->toBe(0);
})->with(['serviceable', 'assigned', 'partial', 'external']);
test('employee cannot access disposal mutations or certificates and direct disposal cannot bypass WMR', function () {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    $this->actingAs(User::factory()->employee()->create());
    $this->get(route('inventory.disposals.index'))->assertForbidden();
    $this->get(route('inventory.disposals.print', $document))->assertForbidden();
    $this->post(route('inventory.disposals.store'), wmrInput())->assertForbidden();
    $this->patch(route('inventory.disposals.transition', $document), wmrApproval())->assertForbidden();
    $this->actingAs($this->preparer)->post(route('inventory.assets.events.store', $this->asset), ['event_type' => 'disposal', 'event_date' => '2026-10-12', 'reference_no' => 'BYPASS', 'disposal_method' => 'destroyed', 'approval_no' => 'AUTH', 'approval_date' => '2026-10-11', 'proceeds' => 0])->assertSessionHasErrors('event_type');
    expect($this->asset->events()->count())->toBe(0);
});
test('WMR lines and evidence cannot be rewritten or deleted', function () {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    expect(fn () => $document->update(['method' => 'public_auction']))->toThrow(LogicException::class);
    expect(fn () => $document->lines()->sole()->update(['description' => 'Replacement']))->toThrow(LogicException::class);
    expect(fn () => $document->actions()->sole()->delete())->toThrow(LogicException::class);
});

test('completed WMR reporting preserves standalone details and excludes pending and future postings', function () {
    $document = $this->workflow->create(wmrInput(['lines' => [['inventory_asset_id' => null, 'quantity' => 1, 'unit' => 'assembly', 'description' => 'Removed alternator', 'source_reference' => 'VEHICLE-001']]]), $this->preparer);
    $filters = ['as_of' => '2026-10-12', 'from' => null, 'to' => null, 'custodian' => null, 'fund_cluster' => '01', 'period' => 'custom', 'period_label' => 'October', 'year' => 2026];
    $reports = app(InventoryReportService::class);
    expect($reports->printable('wmr', $filters)['rows'])->toBe([]);
    $this->workflow->transition($document, wmrApproval(), $this->approver);
    $this->workflow->transition($document, wmrCompletion($document), $this->preparer);
    $report = $reports->printable('wmr', $filters);
    $row = array_combine($report['columns'], $report['rows'][0]);
    expect($row['Description / source'])->toBe('Removed alternator / VEHICLE-001')->and($row['Quantity'])->toBe(1)->and($row['Proceeds'])->toBe('0.00')->and($row['Accounting reference'])->toBe('ACCOUNTING-CONFIRM');
    expect($reports->printable('wmr', [...$filters, 'as_of' => '2026-10-11'])['rows'])->toBe([]);
    expect($reports->printable('wmr', [...$filters, 'custodian' => $this->people[1]->id])['rows'])->toBe([]);
    $this->actingAs($this->preparer)->get(route('inventory.disposals.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('Inventory/Disposals/Index')->has('documents.data', 1));
});
test('multi-line completion rolls back earlier postings when a later sale line lacks its receipt', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 70000, 'condition_status' => 'non_usable', 'lifecycle_status' => 'for_disposal']);
    $input = wmrInput(['method' => 'public_auction']);
    $input['lines'][] = [...$input['lines'][0], 'inventory_asset_id' => $asset->getKey()];
    $document = $this->workflow->create($input, $this->preparer);
    $this->workflow->transition($document, wmrApproval(), $this->approver);
    $completion = wmrCompletion($document);
    $completion['lines'][0]['proceeds'] = '10.00';
    $completion['lines'][0]['or_number'] = 'OR-FIRST';
    $completion['lines'][1]['proceeds'] = '20.00';
    expect(fn () => $this->workflow->transition($document, $completion, $this->preparer))->toThrow(ValidationException::class);
    expect($this->asset->events()->count())->toBe(0)->and($asset->events()->count())->toBe(0)->and($document->fresh()->status)->toBe('approved');
    expect($document->lines()->whereNotNull('proceeds')->count())->toBe(0);
});
test('cancelled WMR releases its property while retaining the original evidence', function () {
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    $this->workflow->transition($document, ['action' => 'cancel', 'attestation' => 'Correct certification.'], $this->preparer);
    $replacement = $this->workflow->create(wmrInput(), $this->preparer);
    expect($replacement->id)->not->toBe($document->id)->and($document->fresh()->lines()->count())->toBe(1);
});
test('WMR dates cannot precede approval and standalone waste needs source evidence', function () {
    $input = wmrInput(['lines' => [['inventory_asset_id' => null, 'quantity' => 1, 'unit' => 'part', 'description' => 'Component']]]);
    $this->actingAs($this->preparer)->post(route('inventory.disposals.store'), $input)->assertSessionHasErrors('lines.0.source_reference');
    $document = $this->workflow->create(wmrInput(), $this->preparer);
    $this->workflow->transition($document,wmrApproval(),$this->approver);
    expect(fn () => $this->workflow->transition($document,wmrCompletion($document,['disposition_date' => '2026-10-10']),$this->preparer))->toThrow(ValidationException::class);
    expect($this->asset->events()->count())->toBe(0);
});
