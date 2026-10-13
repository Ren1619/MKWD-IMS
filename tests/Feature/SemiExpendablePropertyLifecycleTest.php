<?php

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableReliefStatus;
use App\SemiExpendableValueClass;
use App\Services\AssetClassificationService;
use App\Services\InventoryPropertyTransferService;
use App\Services\SemiExpendablePropertyService;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

function validSemiExpendableRegistration(array $overrides = []): array
{
    $category = InventoryAssetCategory::factory()->create([
        'default_useful_life_months' => 36,
    ]);
    $subcategory = InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $category->getKey(),
    ]);

    return [
        'category_id' => $category->getKey(),
        'subcategory_id' => $subcategory->getKey(),
        'serial_number' => 'SEP-SERIAL-001',
        'property_number' => 'SEP-2026-001',
        'name' => 'Semi-Expendable Monitor',
        'unit_of_measure' => 'unit',
        'fund_cluster' => '01',
        'location' => 'Property Unit',
        'acquisition_date' => '2026-01-05',
        'acquisition_method' => 'purchase',
        'supplier_name' => 'Government Supplier Inc.',
        'purchase_order_no' => 'PO-2026-001',
        'inspection_acceptance_report_no' => 'IAR-2026-001',
        'invoice_no' => 'INV-2026-001',
        'delivery_date' => '2026-01-06',
        'inspection_date' => '2026-01-07',
        'acceptance_date' => '2026-01-08',
        'acquisition_cost' => 30000,
        'directly_attributable_costs' => 0,
        'depreciation_useful_life_months' => 36,
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
        ...$overrides,
    ];
}

test('the shared classification policy enforces all low high and PPE boundaries per individual item', function (string $cost, AssetAccountingClassification $classification, ?SemiExpendableValueClass $valueClass) {
    $service = app(AssetClassificationService::class);

    expect($service->classifyDurableItem($cost))->toBe($classification);

    if ($valueClass !== null) {
        expect($service->classifySemiExpendableValue($cost))->toBe($valueClass);
    }
})->with([
    'one peso' => ['1.00', AssetAccountingClassification::SemiExpendable, SemiExpendableValueClass::Low],
    'below low ceiling' => ['4999.99', AssetAccountingClassification::SemiExpendable, SemiExpendableValueClass::Low],
    'low ceiling inclusive' => ['5000.00', AssetAccountingClassification::SemiExpendable, SemiExpendableValueClass::Low],
    'high starts above ceiling' => ['5000.01', AssetAccountingClassification::SemiExpendable, SemiExpendableValueClass::High],
    'below capitalization threshold' => ['49999.99', AssetAccountingClassification::SemiExpendable, SemiExpendableValueClass::High],
    'capitalization threshold inclusive' => ['50000.00', AssetAccountingClassification::Ppe, null],
]);

test('batch receipt creates individually tracked low-valued items using unit cost rather than total purchase value', function () {
    $manager = User::factory()->inventoryManager()->create();
    $data = validSemiExpendableRegistration([
        'serial_number' => null,
        'property_number' => null,
        'acquisition_cost' => 3000,
        'registration_quantity' => 10,
        'property_number_prefix' => 'SEP-BATCH-2026',
    ]);

    $this->actingAs($manager)
        ->post(route('inventory.semi_expendable.store'), $data)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inventory.semi_expendable.index'));

    $assets = InventoryAsset::query()->orderBy('property_number')->get();

    expect($assets)->toHaveCount(10)
        ->and($assets->pluck('property_number')->unique())->toHaveCount(10)
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->accounting_classification === AssetAccountingClassification::SemiExpendable))->toBeTrue()
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->semi_expendable_value_class === SemiExpendableValueClass::Low))->toBeTrue()
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->accounting_status === AssetAccountingStatus::OnHand))->toBeTrue()
        ->and($assets->sum('acquisition_cost'))->toEqual(30000)
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->events()->where('event_type', 'receipt')->exists()))->toBeTrue();
});

test('semi-expendable registration rejects the PPE threshold', function () {
    $manager = User::factory()->inventoryManager()->create();

    $this->actingAs($manager)
        ->post(route('inventory.semi_expendable.store'), validSemiExpendableRegistration([
            'acquisition_cost' => 50000,
        ]))
        ->assertSessionHasErrors('acquisition_cost');

    expect(InventoryAsset::query()->count())->toBe(0);
});

test('low-valued issuance creates a segregated ICS and separates accounting inventory from physical accountability', function () {
    Carbon::setTestNow('2026-06-15 09:00:00');
    $manager = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_cost' => 5000,
        'depreciation_useful_life_months' => 12,
        'location' => 'Property Unit',
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $recipient->id,
    ])->assertSessionHasNoErrors();

    $asset->refresh();
    $ics = PropertyAccountabilityDocument::query()->sole();

    expect($asset->semi_expendable_value_class)->toBe(SemiExpendableValueClass::Low)
        ->and($asset->accounting_status)->toBe(AssetAccountingStatus::IssuedAndExpensed)
        ->and($asset->physical_status)->toBe(SemiExpendablePhysicalStatus::Issued)
        ->and($asset->accountability_status)->toBe(SemiExpendableAccountabilityStatus::PendingRecipient)
        ->and($asset->is_depreciable)->toBeFalse()
        ->and($ics->document_no)->toMatch('/^ICS-LV-2026-\d{6}$/')
        ->and($ics->renewal_due_at->toDateString())->toBe('2029-06-15')
        ->and($ics->accountability_expires_at->toDateString())->toBe('2027-06-15')
        ->and($asset->events()->where('event_type', 'issuance')->exists())->toBeTrue()
        ->and($asset->events()->where('event_type', 'ics_created')->exists())->toBeTrue();
});

test('low-valued accountability expires without deleting or disposing of the physical property', function () {
    Carbon::setTestNow('2026-01-01 08:00:00');
    $manager = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_cost' => 4000,
        'depreciation_useful_life_months' => 12,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $recipient->id]);
    $ics = PropertyAccountabilityDocument::query()->sole();

    $expired = app(SemiExpendablePropertyService::class)->expireDueLowValueAccountabilities('2027-01-01');

    expect($expired)->toBe(1)
        ->and($asset->fresh()->accountability_status)->toBe(SemiExpendableAccountabilityStatus::AccountabilityExtinguished)
        ->and($asset->fresh()->physical_status)->toBe(SemiExpendablePhysicalStatus::Issued)
        ->and($asset->fresh()->lifecycle_status->value)->not->toBe('disposed')
        ->and($asset->fresh()->current_custodian_reference_id)->toBe($recipient->id)
        ->and($ics->fresh()->status)->toBe('expired')
        ->and($asset->events()->where('event_type', 'accountability_expired')->exists())->toBeTrue();
});

test('high-valued accountability never auto expires from useful life alone', function () {
    Carbon::setTestNow('2020-01-01 08:00:00');
    $manager = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_cost' => 30000,
        'depreciation_useful_life_months' => 12,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $recipient->id]);
    $ics = PropertyAccountabilityDocument::query()->sole();

    expect(app(SemiExpendablePropertyService::class)->expireDueLowValueAccountabilities('2030-01-01'))->toBe(0)
        ->and($asset->fresh()->semi_expendable_value_class)->toBe(SemiExpendableValueClass::High)
        ->and($asset->fresh()->accountability_status)->toBe(SemiExpendableAccountabilityStatus::PendingRecipient)
        ->and($ics->fresh()->status)->toBe('pending_recipient')
        ->and($ics->accountability_expires_at)->toBeNull();
});

test('transfer return and reissue preserve every ICS and custody event', function () {
    $manager = User::factory()->inventoryManager()->create();
    $employeeA = HrisReference::factory()->create();
    $employeeB = HrisReference::factory()->create();
    $employeeC = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 30000]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $employeeA->id]);
    $icsA = PropertyAccountabilityDocument::query()->sole();
    $workflow = app(InventoryPropertyTransferService::class);
    $transfer = $workflow->create([
        'inventory_asset_id' => $asset->getKey(), 'recipient_reference_id' => $employeeB->id,
        'transfer_type' => 'reassignment', 'reason' => 'New officer appointed.',
        'destination_location' => 'New office', 'desired_date' => today()->toDateString(),
    ], $manager);
    $workflow->transition($transfer, ['action' => 'verify', 'attestation' => 'Property checked.', 'inspection_reference' => 'INSPECT'], User::factory()->inventoryManager()->create());
    $workflow->transition($transfer, ['action' => 'approve', 'attestation' => 'Transfer approved.'], User::factory()->inventoryManager()->create());
    $workflow->transition($transfer, [
        'action' => 'complete', 'attestation' => 'Joint handover witnessed.', 'inspection_reference' => 'JOINT', 'condition_status' => 'good',
        'handover_date' => today()->toDateString(), 'release_reference' => 'SIGNED-RELEASE', 'receipt_reference' => 'SIGNED-RECEIPT',
    ], $manager);
    $icsB = PropertyAccountabilityDocument::query()->whereKeyNot($icsA->getKey())->sole();

    expect($icsA->fresh()->status)->toBe('superseded')
        ->and($icsB->supersedes_document_id)->toBe($icsA->id)
        ->and($icsB->recipient_reference_id)->toBe($employeeB->id);

    $this->post(route('inventory.assets.unassign', $asset))->assertSessionHasNoErrors();

    expect($icsB->fresh()->status)->toBe('returned')
        ->and($asset->fresh()->accountability_status)->toBe(SemiExpendableAccountabilityStatus::Returned)
        ->and($asset->fresh()->available_for_reissue)->toBeTrue()
        ->and($asset->fresh()->accounting_status)->toBe(AssetAccountingStatus::IssuedAndExpensed);

    $this->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $employeeC->id])->assertSessionHasNoErrors();

    expect(PropertyAccountabilityDocument::query()->count())->toBe(3)
        ->and($asset->events()->where('event_type', 'return')->exists())->toBeTrue()
        ->and($asset->events()->where('event_type', 'reissue')->exists())->toBeTrue();
});

test('reported loss and pending relief retain high-valued accountability until approval', function () {
    $manager = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 25000]);
    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $recipient->id]);

    $this->post(route('inventory.semi_expendable.events.store', $asset), [
        'event_type' => 'loss', 'event_date' => today()->toDateString(), 'reference_no' => 'RLSDDSP-001',
        'loss_type' => 'lost', 'incident_date' => today()->subDay()->toDateString(),
        'date_discovered' => today()->toDateString(), 'circumstances' => 'Item was not found during the documented field inspection.',
    ])->assertSessionHasNoErrors();

    expect($asset->fresh()->physical_status)->toBe(SemiExpendablePhysicalStatus::Missing)
        ->and($asset->fresh()->current_custodian_reference_id)->toBe($recipient->id);

    $this->post(route('inventory.semi_expendable.events.store', $asset), [
        'event_type' => 'relief_requested', 'event_date' => today()->toDateString(), 'reference_no' => 'RELIEF-001',
        'notes' => 'Complete relief packet submitted for approval.',
    ])->assertSessionHasNoErrors();

    expect($asset->fresh()->relief_status)->toBe(SemiExpendableReliefStatus::Pending)
        ->and($asset->fresh()->accountability_status)->toBe(SemiExpendableAccountabilityStatus::PendingRelief)
        ->and($asset->fresh()->current_custodian_reference_id)->toBe($recipient->id);

    $this->post(route('inventory.semi_expendable.events.store', $asset), [
        'event_type' => 'relief_approved', 'event_date' => today()->toDateString(), 'reference_no' => 'RELIEF-001',
        'approval_no' => 'AUTH-RELIEF-001', 'approval_date' => today()->toDateString(),
        'notes' => 'Relief approved by the authorized official.',
    ])->assertSessionHasNoErrors();

    expect($asset->fresh()->relief_status)->toBe(SemiExpendableReliefStatus::Approved)
        ->and($asset->fresh()->accountability_status)->toBe(SemiExpendableAccountabilityStatus::Relieved)
        ->and($asset->fresh()->current_custodian_reference_id)->toBeNull();
});

test('unserviceable return and disposal remain distinct transactions', function () {
    $manager = User::factory()->inventoryManager()->create();
    $recipient = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 20000]);
    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $recipient->id]);
    $this->post(route('inventory.assets.unassign', $asset));

    $this->post(route('inventory.semi_expendable.events.store', $asset), [
        'event_type' => 'unserviceable', 'event_date' => today()->toDateString(), 'reference_no' => 'IIRUSP-001',
        'condition_status' => 'non_usable', 'appraised_value' => 500,
    ])->assertSessionHasNoErrors();

    expect($asset->fresh()->physical_status)->toBe(SemiExpendablePhysicalStatus::ForDisposal)
        ->and($asset->fresh()->lifecycle_status->value)->toBe('for_disposal');

    app(SemiExpendablePropertyService::class)->record($asset, [
        'event_type' => 'disposal', 'event_date' => today()->toDateString(), 'reference_no' => 'DISP-SEP-001',
        'disposal_method' => 'destruction', 'approval_no' => 'AUTH-001', 'approval_date' => today()->toDateString(),
        'proceeds' => 0, 'notes' => 'Destroyed in the presence of authorized witnesses.',
    ], $manager);

    expect($asset->fresh()->physical_status)->toBe(SemiExpendablePhysicalStatus::Disposed)
        ->and($asset->fresh()->accounting_status)->toBe(AssetAccountingStatus::Disposed)
        ->and($asset->fresh()->events()->where('event_type', 'unserviceable')->exists())->toBeTrue()
        ->and($asset->fresh()->events()->where('event_type', 'disposal')->exists())->toBeTrue();
});

test('semi-expendable statutory reports are generated from transaction records', function (string $document) {
    $employee = User::factory()->inventoryManager()->create();
    InventoryAsset::factory()->create(['acquisition_cost' => 12000]);

    $this->actingAs($employee)
        ->get(route('inventory.reports.print', ['document' => $document, 'period' => 'all']))
        ->assertOk();
})->with([
    'semi-master-register', 'spc', 'splc', 'regspi', 'rspi', 'itr', 'rrsp', 'rpcsp',
    'rlsddsp', 'iirusp', 'ics-renewal', 'low-value-expiration', 'high-value-beyond-life',
    'returned-for-reissue', 'semi-reconciliation', 'semi-audit-trail',
]);
