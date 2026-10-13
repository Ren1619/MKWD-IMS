<?php

use App\AssetAccountingStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryPropertyTransfer;
use App\Models\User;
use App\Services\InventoryAssetService;
use App\Services\InventoryPropertyCardService;
use App\Services\InventoryPropertyTransferService;
use App\Services\InventoryReportService;
use App\Services\PpeDepreciationCalculator;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    $this->preparer = User::factory()->inventoryManager()->create();
    $this->verifier = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->outgoing = HrisReference::factory()->create(['name' => 'Outgoing property officer']);
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 60000, 'acquisition_date' => '2025-01-01', 'name' => 'Agency transfer equipment', 'location' => 'Warehouse', 'fund_cluster' => '01']);
    app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->preparer);
    $this->source = $this->asset->accountabilityDocuments()->sole();
    $this->workflow = app(InventoryPropertyTransferService::class);
});

function externalTransferInput(array $overrides = []): array
{
    return [...['inventory_asset_id' => test()->asset->getKey(), 'transfer_type' => 'agency_transfer',
        'agency_name' => 'Receiving Water District', 'agency_officer_name' => 'Agency property officer', 'agency_officer_position' => 'Property Custodian',
        'reason' => 'Permanent agency transfer under approved authority.', 'destination_location' => 'Receiving district warehouse', 'desired_date' => '2026-10-12'], ...$overrides];
}
function externalApproveInput(array $overrides = []): array
{
    return [...['action' => 'approve', 'attestation' => 'Transfer authority and Accounting Unit clearance verified.', 'authority_reference' => 'BOARD-RESOLUTION-001', 'accounting_clearance_reference' => 'ACCOUNTING-CLEARANCE-001'], ...$overrides];
}
function externalHandoverInput(array $overrides = []): array
{
    return [...['action' => 'complete', 'attestation' => 'Permanent ownership, joint inspection and agency receipt confirmed.',
        'inspection_reference' => 'JOINT-AGENCY-001', 'condition_status' => 'good', 'handover_date' => '2026-10-12',
        'release_reference' => 'SIGNED-RELEASE', 'receipt_reference' => 'AGENCY-ACCEPTANCE', 'accounting_reference' => 'ACCOUNTING-JEV-001', 'ownership_transfer_confirmed' => true], ...$overrides];
}
function verifiedExternalTransfer(array $overrides = []): InventoryPropertyTransfer
{
    $transfer = test()->workflow->create(externalTransferInput($overrides), test()->preparer);
    test()->workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Property and ownership checked.', 'inspection_reference' => 'INSPECT-AGENCY'], test()->verifier);

    return $transfer->refresh();
}

test('permanent agency transfers and donations preserve evidence without creating internal recipient documents', function (float $cost, string $type) {
    if ($cost < 50000) {
        $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost, 'acquisition_date' => '2025-01-01', 'location' => 'Warehouse', 'fund_cluster' => '01']);
        app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->preparer);
        $this->source = $this->asset->accountabilityDocuments()->sole();
    }
    $transfer = verifiedExternalTransfer(['transfer_type' => $type]);
    $this->workflow->transition($transfer, externalApproveInput(), $this->approver);
    expect($this->asset->fresh()->current_custodian_reference_id)->toBe($this->outgoing->id);
    $completed = $this->workflow->transition($transfer, externalHandoverInput(), $this->preparer);
    $event = $this->asset->events()->where('event_type', InventoryAssetEventType::Transfer)->sole();
    expect($completed->status)->toBe('completed')->and($completed->recipient_reference_id)->toBeNull()
        ->and($completed->replacement_document_id)->toBeNull()->and($this->asset->accountabilityDocuments()->count())->toBe(1)
        ->and($this->source->fresh()->status)->toBe('transferred_out')
        ->and($this->asset->fresh()->current_custodian_reference_id)->toBeNull()
        ->and($this->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::TransferredOut)
        ->and($this->asset->fresh()->is_assignable)->toBeFalse()
        ->and($this->asset->fresh()->custody_status)->toBe('transferred_out')
        ->and($this->asset->custodianAssignments()->whereNull('unassigned_at')->count())->toBe(0)
        ->and($event->details['transfer_evidence']['recipient']['office'])->toBe('Receiving Water District')
        ->and($event->details['transfer_evidence']['accounting_reference'])->toBe('ACCOUNTING-JEV-001')
        ->and($this->asset->events()->where('event_type', InventoryAssetEventType::Disposal)->exists())->toBeFalse();
    if ($cost >= 50000) {
        expect($this->asset->fresh()->accounting_status)->toBe(AssetAccountingStatus::Derecognized)
            ->and($this->asset->events()->where('event_type', InventoryAssetEventType::Derecognition)->count())->toBe(1)
            ->and(app(PpeDepreciationCalculator::class)->calculate($this->asset->fresh(), '2026-10-12')->isRecognized)->toBeFalse();
    } else {
        expect($this->asset->fresh()->accounting_status)->toBe(AssetAccountingStatus::IssuedAndExpensed)
            ->and($this->asset->events()->where('event_type', InventoryAssetEventType::Issuance)->count())->toBe(1)
            ->and($this->asset->events()->where('event_type', InventoryAssetEventType::Derecognition)->exists())->toBeFalse();
    }
    $filters = ['as_of' => '2026-10-12', 'from' => null, 'to' => null, 'fund_cluster' => '01', 'custodian' => null];
    $card = app(InventoryPropertyCardService::class)->build($filters, $this->asset->accounting_classification);
    $rows = collect(array_map(fn ($row) => array_combine($card['columns'], $row), $card['rows']));
    $out = $rows->firstWhere('Transaction', 'Permanent transfer to another agency');
    expect($out['Recorded property qty.'])->toBe(0)->and($out['Qty. moved (custody)'])->toBe(1)
        ->and($out['Qty. disposed'])->toBe(0)->and($rows->last()['Recorded property qty.'])->toBe(0);
    $this->actingAs($this->preparer)->get(route('inventory.transfers.print', $completed))->assertSuccessful()->assertSee('Receiving Water District')->assertSee('ACCOUNTING-JEV-001')->assertSee('BOARD-RESOLUTION-001');
    $registry = $cost >= 50000 ? 'inventory.assets.index' : 'inventory.semi_expendable.index';
    $this->get(route($registry, ['custody_status' => 'available']))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->has('assets.data', 0));
    $report = app(InventoryReportService::class)->printable('property-transfer', [...$filters, 'period' => 'custom', 'period_label' => 'October', 'year' => 2026]);
    $row = array_combine($report['columns'], $report['rows'][0]);
    expect($row['To accountable officer'])->toBe('Agency property officer')->and($row['To office'])->toBe('Receiving Water District')->and($row['Replacement PAR / ICS'])->toBeNull();
})->with([[60000.0, 'agency_transfer'], [60000.0, 'donation'], [8000.0, 'agency_transfer'], [8000.0, 'donation']]);

test('external approval requires transfer authority and Accounting Unit clearance', function (string $field) {
    $transfer = verifiedExternalTransfer();
    $input = externalApproveInput();
    unset($input[$field]);
    $this->actingAs($this->approver)->patch(route('inventory.transfers.transition', $transfer), $input)->assertSessionHasErrors($field);
    expect(fn () => $this->workflow->transition($transfer, $input, $this->approver))->toThrow(ValidationException::class)
        ->and($transfer->fresh()->status)->toBe('verified');
})->with(['authority_reference', 'accounting_clearance_reference']);

test('external handover requires accounting posting and explicit ownership confirmation', function (string $field) {
    $transfer = verifiedExternalTransfer();
    $this->workflow->transition($transfer, externalApproveInput(), $this->approver);
    $input = externalHandoverInput();
    unset($input[$field]);
    $this->actingAs($this->preparer)->patch(route('inventory.transfers.transition', $transfer), $input)->assertSessionHasErrors($field);
    expect(fn () => $this->workflow->transition($transfer, $input, $this->preparer))->toThrow(ValidationException::class)
        ->and($transfer->fresh()->status)->toBe('approved')
        ->and($this->source->fresh()->status)->toBe('pending_recipient');
})->with(['accounting_reference', 'ownership_transfer_confirmed']);

test('agency recipient details are mandatory and cannot be replaced with an internal employee reference', function () {
    $this->actingAs($this->preparer)->post(route('inventory.transfers.store'), externalTransferInput(['agency_name' => null]))->assertSessionHasErrors('agency_name');
    $this->post(route('inventory.transfers.store'), externalTransferInput(['recipient_reference_id' => $this->outgoing->id]))->assertSessionHasErrors('recipient_reference_id');
    $this->post(route('inventory.transfers.store'), externalTransferInput())->assertSessionHasNoErrors();
    $transfer = InventoryPropertyTransfer::query()->sole();
    expect($transfer->recipient_snapshot['position'])->toBe('Property Custodian');
});

test('permanent transfer cannot be undone by internal reassignment return or a generic state edit', function () {
    $transfer = verifiedExternalTransfer();
    $this->workflow->transition($transfer, externalApproveInput(), $this->approver);
    $this->workflow->transition($transfer, externalHandoverInput(), $this->preparer);
    $this->actingAs($this->preparer)->post(route('inventory.assets.assign', $this->asset), ['hris_reference_id' => $this->outgoing->id])->assertSessionHasErrors('hris_reference_id');
    $this->post(route('inventory.assets.unassign', $this->asset))->assertSessionHasErrors('asset');
    expect(fn () => app(InventoryAssetService::class)->updateState($this->asset, ['lifecycle_status' => 'active', 'condition_status' => 'good'], $this->preparer))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(InventoryAssetService::class)->updateDetails($this->asset, ['location' => 'Warehouse'], $this->preparer))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->workflow->transition($transfer, externalHandoverInput(), $this->preparer))->toThrow(ValidationException::class);
});

test('current physical registers exclude permanently transferred semi expendable property but retain earlier cutoff coverage', function () {
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 8000, 'acquisition_date' => '2025-01-01', 'fund_cluster' => '01']);
    app(InventoryAssetService::class)->assign($this->asset, $this->outgoing, $this->preparer);
    $transfer = verifiedExternalTransfer();
    $this->workflow->transition($transfer, externalApproveInput(), $this->approver);
    $this->workflow->transition($transfer, externalHandoverInput(), $this->preparer);
    $filters = ['as_of' => '2026-10-12', 'from' => null, 'to' => null, 'fund_cluster' => '01', 'custodian' => null, 'period' => 'custom', 'period_label' => 'October', 'year' => 2026];
    expect(app(InventoryReportService::class)->printable('rpcsp', $filters)['rows'])->toBeEmpty()
        ->and(app(InventoryReportService::class)->printable('rpcsp', [...$filters, 'as_of' => '2026-10-11'])['rows'])->toHaveCount(1);
    $this->actingAs($this->preparer)->post(route('inventory.semi_expendable.events.store', $this->asset), ['event_type' => 'physical_count', 'event_date' => '2026-10-12', 'reference_no' => 'FORBIDDEN-COUNT', 'quantity' => 1, 'counted_by' => 'IMS staff', 'count_location' => 'Warehouse', 'condition_status' => 'good'])->assertSessionHasErrors('event_type');
});

test('external source changes block completion without closing the original document', function () {
    $transfer = verifiedExternalTransfer();
    $this->workflow->transition($transfer, externalApproveInput(), $this->approver);
    $this->asset->update(['serial_number' => 'CHANGED-AFTER-APPROVAL']);
    expect(fn () => $this->workflow->transition($transfer, externalHandoverInput(), $this->preparer))->toThrow(ValidationException::class)
        ->and($this->source->fresh()->status)->toBe('pending_recipient')
        ->and($transfer->fresh()->status)->toBe('approved');
});
