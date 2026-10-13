<?php

use App\AssetConditionStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryPropertyTransfer;
use App\Models\User;
use App\Services\InventoryAssetService;
use App\Services\InventoryPropertyTransferService;
use App\Services\InventoryReportService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    $this->requestor = User::factory()->inventoryManager()->create();
    $this->verifier = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->outgoing = HrisReference::factory()->create(['name' => 'Outgoing officer']);
    $this->incoming = HrisReference::factory()->create(['name' => 'Incoming officer']);
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 60000, 'name' => 'Transfer pump', 'location' => 'Warehouse', 'fund_cluster' => '01']);
    app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->requestor);
    $this->source = $this->asset->accountabilityDocuments()->sole();
    $this->workflow = app(InventoryPropertyTransferService::class);
});

function propertyTransferInput(array $overrides = []): array
{
    return [...[
        'inventory_asset_id' => test()->asset->getKey(), 'recipient_reference_id' => test()->incoming->id,
        'transfer_type' => 'reassignment', 'reason' => 'Successor appointed',
        'destination_location' => 'Pump station', 'desired_date' => '2026-10-12',
    ], ...$overrides];
}

function prepareApprovedPropertyTransfer(array $overrides = []): InventoryPropertyTransfer
{
    $test = test();
    $transfer = $test->workflow->create(propertyTransferInput($overrides), $test->requestor);
    $test->workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Serial and ownership checked.', 'inspection_reference' => 'INSPECT-001'], $test->verifier);
    $test->workflow->transition($transfer, ['action' => 'approve', 'attestation' => 'Transfer authorized.'], $test->approver);

    return $transfer->refresh();
}

function propertyHandoverInput(array $overrides = []): array
{
    return [...['action' => 'complete', 'attestation' => 'Joint inspection performed and both officers signed.', 'inspection_reference' => 'JOINT-001',
        'condition_status' => 'fair', 'handover_date' => '2026-10-12', 'release_reference' => 'SIGNED-RELEASE-001', 'receipt_reference' => 'SIGNED-RECEIPT-001'], ...$overrides];
}

test('preparation verification and approval do not change custody or issue replacement documents', function () {
    $transfer = prepareApprovedPropertyTransfer();
    expect($transfer->status)->toBe('approved')->and($transfer->actions()->count())->toBe(3)
        ->and($this->asset->fresh()->current_custodian_reference_id)->toBe($this->outgoing->id)
        ->and($this->asset->accountabilityDocuments()->count())->toBe(1)
        ->and($this->asset->fresh()->location)->toBe('Warehouse');
});

test('only completed approved handover atomically transfers custody and produces frozen PTR evidence', function (float $cost) {
    if ($cost < 50000) {
        $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost, 'location' => 'Warehouse', 'fund_cluster' => '01']);
        app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->requestor);
        $this->source = $this->asset->accountabilityDocuments()->sole();
    }
    $transfer = prepareApprovedPropertyTransfer();
    $completed = $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor);
    $replacement = $this->asset->accountabilityDocuments()->where('supersedes_document_id', $this->source->id)->sole();
    $event = $this->asset->events()->where('event_type', InventoryAssetEventType::Transfer)->sole();
    expect($completed->status)->toBe('completed')->and($completed->replacement_document_id)->toBe($replacement->id)
        ->and($this->source->fresh()->status)->toBe('superseded')
        ->and($this->asset->fresh()->current_custodian_reference_id)->toBe($this->incoming->id)
        ->and($this->asset->fresh()->location)->toBe('Pump station')
        ->and($this->asset->fresh()->condition_status)->toBe(AssetConditionStatus::Fair)
        ->and($replacement->recipient_name)->toBe('Incoming officer')
        ->and($event->reference_no)->toBe($completed->document_no)
        ->and($event->from_location)->toBe('Warehouse')->and($event->to_location)->toBe('Pump station')
        ->and($event->details['transfer_evidence']['receipt_reference'])->toBe('SIGNED-RECEIPT-001');
    $this->actingAs($this->requestor)->get(route('inventory.transfers.print', $completed))->assertSuccessful()
        ->assertSee('SIGNED-RELEASE-001')->assertSee($replacement->document_no)->assertDontSee('NOT A COMPLETED HANDOVER');
    $report = app(InventoryReportService::class)->printable('property-transfer', ['as_of' => '2026-10-12', 'period' => 'custom', 'period_label' => 'October', 'year' => 2026, 'from' => null, 'to' => null, 'fund_cluster' => '01', 'custodian' => null]);
    $row = array_combine($report['columns'], $report['rows'][0]);
    expect($row['Transfer approval'])->toContain($this->approver->name)
        ->and($row['Joint inspection / handover'])->toContain('JOINT-001', 'SIGNED-RECEIPT-001');
})->with([60000.0, 8000.0]);

test('requestor cannot verify and neither requestor nor verifier can approve their own transfer', function () {
    $transfer = $this->workflow->create(propertyTransferInput(), $this->requestor);
    expect(fn () => $this->workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Checked.'], $this->requestor))->toThrow(ValidationException::class);
    $this->workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Checked.', 'inspection_reference' => 'INSP'], $this->verifier);
    foreach ([$this->requestor, $this->verifier] as $actor) {
        expect(fn () => $this->workflow->transition($transfer, ['action' => 'approve', 'attestation' => 'Approved.'], $actor))->toThrow(ValidationException::class);
    }
});

test('out of order and duplicate decisions cannot change custody', function () {
    $transfer = $this->workflow->create(propertyTransferInput(), $this->requestor);
    expect(fn () => $this->workflow->transition($transfer, propertyHandoverInput(), $this->approver))->toThrow(ValidationException::class);
    $this->workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Checked.', 'inspection_reference' => 'INSP'], $this->verifier);
    $this->workflow->transition($transfer, ['action' => 'approve', 'attestation' => 'Approved.'], $this->approver);
    $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor);
    expect(fn () => $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor))->toThrow(ValidationException::class)
        ->and($this->asset->accountabilityDocuments()->count())->toBe(2)
        ->and($transfer->actions()->count())->toBe(4);
});

test('a second open request is rejected until the first is cancelled', function () {
    $transfer = $this->workflow->create(propertyTransferInput(), $this->requestor);
    expect(fn () => $this->workflow->create(propertyTransferInput(), $this->verifier))->toThrow(ValidationException::class);
    $this->workflow->transition($transfer, ['action' => 'cancel', 'attestation' => 'Correct recipient required.'], $this->requestor);
    expect($this->workflow->create(propertyTransferInput(), $this->requestor)->status)->toBe('submitted');
});

test('stale transfer details cannot be handed over and remain cancellable', function (string $change) {
    $transfer = prepareApprovedPropertyTransfer();
    match ($change) {
        'asset' => $this->asset->update(['serial_number' => 'CHANGED-SERIAL']),
        'recipient' => $this->incoming->update(['name' => 'Renamed officer']),
        'inactive' => $this->incoming->update(['is_active' => false]),
        'return' => app(InventoryAssetService::class)->unassign($this->asset, $this->requestor),
        'archived' => $this->asset->delete(),
    };
    expect(fn () => $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor))->toThrow(ValidationException::class)
        ->and($transfer->fresh()->status)->toBe('approved')
        ->and($transfer->actions()->count())->toBe(3);
    $this->workflow->transition($transfer, ['action' => 'cancel', 'attestation' => 'Details changed.'], $this->requestor);
    expect($transfer->fresh()->status)->toBe('cancelled');
})->with(['asset', 'recipient', 'inactive', 'return', 'archived']);

test('handover completion requires all physical inspection and signature evidence', function (string $field) {
    $transfer = prepareApprovedPropertyTransfer();
    $input = propertyHandoverInput();
    unset($input[$field]);
    $this->actingAs($this->requestor)->patch(route('inventory.transfers.transition', $transfer), $input)->assertSessionHasErrors($field);
    expect($transfer->fresh()->status)->toBe('approved')->and($this->asset->accountabilityDocuments()->count())->toBe(1);
})->with(['inspection_reference', 'condition_status', 'handover_date', 'release_reference', 'receipt_reference', 'attestation']);

test('handover cannot predate approval', function () {
    $transfer = prepareApprovedPropertyTransfer();
    expect(fn () => $this->workflow->transition($transfer, propertyHandoverInput(['handover_date' => '2026-10-11']), $this->requestor))->toThrow(ValidationException::class);
});

test('approved relocation preserves the accountable officer and document', function () {
    $transfer = prepareApprovedPropertyTransfer(['recipient_reference_id' => $this->outgoing->id, 'transfer_type' => 'relocation']);
    $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor);
    expect($this->asset->fresh()->current_custodian_reference_id)->toBe($this->outgoing->id)
        ->and($this->asset->accountabilityDocuments()->count())->toBe(1)
        ->and($this->source->fresh()->status)->toBe('pending_recipient')
        ->and($this->asset->fresh()->location)->toBe('Pump station');
});

test('employee accounts cannot prepare decide view or print manager transfer evidence', function () {
    $transfer = $this->workflow->create(propertyTransferInput(), $this->requestor);
    $this->actingAs(User::factory()->create());
    $this->get(route('inventory.transfers.index'))->assertForbidden();
    $this->get(route('inventory.transfers.show', $transfer))->assertForbidden();
    $this->get(route('inventory.transfers.print', $transfer))->assertForbidden();
    $this->post(route('inventory.transfers.store'), propertyTransferInput())->assertForbidden();
    $this->patch(route('inventory.transfers.transition', $transfer), propertyHandoverInput())->assertForbidden();
});

test('direct reassignment is blocked while initial custody assignment remains available', function () {
    $this->actingAs($this->requestor)->post(route('inventory.assets.assign', $this->asset), ['hris_reference_id' => $this->incoming->id])->assertSessionHasErrors('hris_reference_id');
    expect($this->asset->fresh()->current_custodian_reference_id)->toBe($this->outgoing->id);
    $new = InventoryAsset::factory()->create(['acquisition_cost' => 60000]);
    $this->post(route('inventory.assets.assign', $new), ['hris_reference_id' => $this->incoming->id])->assertSessionHasNoErrors();
    expect($new->fresh()->current_custodian_reference_id)->toBe($this->incoming->id);
});

test('transfer requests decisions and print screens expose frozen evidence and pending status', function () {
    $this->actingAs($this->requestor)->get(route('inventory.transfers.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('Inventory/Transfers/Index'));
    $this->post(route('inventory.transfers.store'), propertyTransferInput())->assertSessionHasNoErrors();
    $transfer = InventoryPropertyTransfer::query()->sole();
    $this->get(route('inventory.transfers.show', $transfer))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('Inventory/Transfers/Show')->where('transfer.status', 'submitted'));
    $this->get(route('inventory.transfers.print', $transfer))->assertSuccessful()->assertSee('NOT A COMPLETED HANDOVER')->assertSee('Outgoing officer');
    $action = $transfer->actions()->sole();
    expect(fn () => $action->update(['attestation' => 'Rewritten']))->toThrow(LogicException::class);
    expect(fn () => $transfer->update(['reason' => 'Rewritten']))->toThrow(LogicException::class);
});

test('assigned location changes cannot bypass PTR through generic events or property edits', function (float $cost) {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost, 'location' => 'Original location']);
    app(InventoryAssetService::class)->assign($asset, $this->outgoing, $this->requestor);
    $route = $cost >= 50000 ? 'inventory.assets.events.store' : 'inventory.semi_expendable.events.store';
    $this->actingAs($this->requestor)->post(route($route, $asset), ['event_type' => 'transfer', 'event_date' => '2026-10-12', 'reference_no' => 'BYPASS', 'to_location' => 'Unauthorized location'])->assertSessionHasErrors('event_type');
    expect(fn () => app(InventoryAssetService::class)->updateDetails($asset, ['location' => 'Unauthorized location'], $this->requestor))->toThrow(InvalidArgumentException::class)
        ->and($asset->fresh()->location)->toBe('Original location')
        ->and($asset->events()->where('event_type', InventoryAssetEventType::Transfer)->exists())->toBeFalse();
})->with([60000.0, 8000.0]);

test('failed low value reissue rolls back handover location condition and documents together', function () {
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 4000, 'location' => 'Warehouse']);
    app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->requestor);
    $this->source = $this->asset->accountabilityDocuments()->sole();
    $other = InventoryAsset::factory()->create(['acquisition_cost' => 4000, 'category_id' => $this->asset->category_id, 'subcategory_id' => $this->asset->subcategory_id]);
    app(InventoryAssetService::class)->assign($other, $this->incoming, $this->requestor);
    $transfer = prepareApprovedPropertyTransfer();
    $condition = $this->asset->condition_status;
    expect(fn () => $this->workflow->transition($transfer, propertyHandoverInput(), $this->requestor))->toThrow(ValidationException::class)
        ->and($this->asset->fresh()->location)->toBe('Warehouse')
        ->and($this->asset->fresh()->condition_status)->toBe($condition)
        ->and($this->source->fresh()->status)->toBe('pending_recipient')
        ->and($transfer->fresh()->status)->toBe('approved')
        ->and($this->asset->accountabilityDocuments()->count())->toBe(1);
});

test('initial assignment guard uses locked current custody rather than a stale model', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 60000]);
    $stale = $asset->fresh();
    app(InventoryAssetService::class)->assign($asset, $this->outgoing, $this->requestor);
    expect(fn () => app(InventoryAssetService::class)->assign($stale, $this->incoming, $this->requestor, initialAssignmentOnly: true))->toThrow(InvalidArgumentException::class);
});
