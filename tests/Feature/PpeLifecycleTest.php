<?php

use App\AssetAccountingStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetEvent;
use App\Models\InventoryAssetSubcategory;
use App\Models\User;
use App\Services\InventoryAssetLifecycleService;
use App\Services\PpeDepreciationCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function validPpeRegistration(array $overrides = []): array
{
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $category->getKey(),
    ]);

    return [
        'category_id' => $category->getKey(),
        'subcategory_id' => $subcategory->getKey(),
        'serial_number' => 'PPE-LIFECYCLE-001',
        'property_number' => 'PPE-2026-LIFECYCLE-001',
        'name' => 'Audit Lifecycle Server',
        'unit_of_measure' => 'unit',
        'fund_cluster' => '01',
        'manufacturer' => 'Example Manufacturer',
        'specifications' => 'Eight-core rack server with redundant power.',
        'location' => 'Data Center',
        'acquisition_date' => '2025-01-05',
        'acquisition_method' => 'purchase',
        'supplier_name' => 'Government Supplier Inc.',
        'purchase_order_no' => 'PO-2025-001',
        'inspection_acceptance_report_no' => 'IAR-2025-001',
        'invoice_no' => 'INV-2025-001',
        'disbursement_voucher_no' => 'DV-2025-001',
        'delivery_date' => '2025-01-07',
        'inspection_date' => '2025-01-08',
        'acceptance_date' => '2025-01-09',
        'available_for_use_date' => '2025-01-10',
        'acquisition_cost' => 110000,
        'directly_attributable_costs' => 10000,
        'residual_value_percentage' => 5,
        'residual_value_basis' => 'COA default residual value of 5%.',
        'depreciation_useful_life_months' => 60,
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
        ...$overrides,
    ];
}

test('PPE registration snapshots policy and creates immutable registration and acceptance events', function () {
    $manager = User::factory()->inventoryManager()->create();

    $this->actingAs($manager)
        ->post(route('inventory.assets.store'), validPpeRegistration())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inventory.assets.index'));

    $asset = InventoryAsset::query()->sole();

    expect($asset->accounting_policy_version)->toBe('2026-01')
        ->and($asset->capitalization_threshold_applied)->toBe('50000.00')
        ->and($asset->accounting_status)->toBe(AssetAccountingStatus::Recognized)
        ->and($asset->is_depreciable)->toBeTrue()
        ->and($asset->events()->get()->map(fn (InventoryAssetEvent $event): string => $event->event_type->value)->all())->toContain(
            InventoryAssetEventType::Registered->value,
            InventoryAssetEventType::Accepted->value,
        );

    $event = $asset->events()->where('event_type', InventoryAssetEventType::Registered)->firstOrFail();

    expect(fn () => $event->update(['notes' => 'Attempted rewrite']))
        ->toThrow(LogicException::class, 'immutable');
});

test('classification uses recognized cost including directly attributable costs', function () {
    $manager = User::factory()->inventoryManager()->create();
    $data = validPpeRegistration([
        'acquisition_cost' => 48000,
        'directly_attributable_costs' => 2000,
    ]);

    $this->actingAs($manager)
        ->post(route('inventory.assets.store'), $data)
        ->assertSessionHasNoErrors();

    expect(InventoryAsset::query()->sole()->accounting_classification->value)->toBe('ppe');
});

test('the authoritative calculator applies the fifteenth day rule and historical capital additions', function () {
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-01-05',
        'available_for_use_date' => '2025-01-10',
        'acquisition_cost' => 120000,
        'directly_attributable_costs' => 0,
        'residual_value_percentage' => 5,
        'depreciation_useful_life_months' => 60,
    ]);
    InventoryAssetEvent::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'event_type' => InventoryAssetEventType::CapitalAddition,
        'event_date' => '2025-03-10',
        'amount' => 60000,
        'details' => ['remaining_useful_life_months' => 58],
    ]);
    $calculator = app(PpeDepreciationCalculator::class);

    $beforeAddition = $calculator->calculate($asset->fresh(), '2025-02-28');
    $afterAddition = $calculator->calculate($asset->fresh(), '2025-03-31');

    expect($beforeAddition->grossCarryingAmount)->toBe('120000.00')
        ->and($beforeAddition->accumulatedDepreciation)->toBe('3800.00')
        ->and($afterAddition->grossCarryingAmount)->toBe('180000.00')
        ->and($afterAddition->residualValue)->toBe('9000.00')
        ->and($afterAddition->accumulatedDepreciation)->toBe('6682.76')
        ->and($afterAddition->carryingAmount)->toBe('173317.24');
});

test('lifecycle transactions are idempotent and update only the current snapshot they own', function () {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-01-01',
        'acquisition_cost' => 100000,
        'location' => 'Old Office',
    ]);
    $key = (string) Str::uuid();
    $payload = [
        'event_type' => 'transfer',
        'event_date' => today()->toDateString(),
        'reference_no' => 'PTR-2026-001',
        'to_location' => 'New Office',
        'notes' => 'Approved office transfer.',
        'idempotency_key' => $key,
    ];

    $this->actingAs($manager)->post(route('inventory.assets.events.store', $asset), $payload)->assertSessionHasNoErrors();
    $this->post(route('inventory.assets.events.store', $asset), $payload)->assertSessionHasNoErrors();

    expect($asset->fresh()->location)->toBe('New Office')
        ->and($asset->events()->where('event_type', 'transfer')->count())->toBe(1)
        ->and($asset->events()->where('idempotency_key', $key)->sole()->from_location)->toBe('Old Office');
});

test('impairment cannot exceed carrying amount', function () {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-01-01',
        'available_for_use_date' => '2025-01-01',
        'acquisition_cost' => 100000,
    ]);

    $this->actingAs($manager)
        ->post(route('inventory.assets.events.store', $asset), [
            'event_type' => 'impairment',
            'event_date' => today()->toDateString(),
            'reference_no' => 'JEV-IMP-001',
            'amount' => 200000,
            'impairment_trigger' => 'Significant physical damage.',
            'assessment_basis' => 'Independent engineering assessment.',
        ])
        ->assertSessionHasErrors('event_type');

    expect($asset->events()->where('event_type', 'impairment')->exists())->toBeFalse();
});

test('impairment reversals are dated, limited, and included in carrying amount', function () {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-01-01',
        'available_for_use_date' => '2025-01-01',
        'acquisition_cost' => 100000,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.events.store', $asset), [
        'event_type' => 'impairment',
        'event_date' => today()->subDay()->toDateString(),
        'reference_no' => 'JEV-IMP-002',
        'amount' => 10000,
        'impairment_trigger' => 'Evidence of physical damage.',
        'assessment_basis' => 'Recoverable service amount assessment.',
    ])->assertSessionHasNoErrors();

    $this->post(route('inventory.assets.events.store', $asset), [
        'event_type' => 'impairment_reversal',
        'event_date' => today()->toDateString(),
        'reference_no' => 'JEV-REV-001',
        'amount' => 4000,
        'impairment_trigger' => 'Restoration of service potential.',
        'assessment_basis' => 'Post-repair engineering assessment.',
    ])->assertSessionHasNoErrors();

    $asset->refresh();
    $valuation = app(PpeDepreciationCalculator::class)->calculate($asset, today());

    expect($asset->impairment_losses)->toBe('6000.00')
        ->and($valuation->impairmentLosses)->toBe('6000.00');

    $this->post(route('inventory.assets.events.store', $asset), [
        'event_type' => 'impairment_reversal',
        'event_date' => today()->toDateString(),
        'reference_no' => 'JEV-REV-OVER',
        'amount' => 7000,
        'impairment_trigger' => 'Unsupported excess reversal.',
        'assessment_basis' => 'Test assessment.',
    ])->assertSessionHasErrors('event_type');
});

test('disposal requires unserviceable available property and atomically derecognizes it', function () {
    Carbon::setTestNow('2026-09-17 10:00:00');
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2024-01-01',
        'available_for_use_date' => '2024-01-01',
        'acquisition_cost' => 100000,
        'location' => 'Property Unit',
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.events.store', $asset), [
        'event_type' => 'disposal',
        'event_date' => '2026-09-17',
        'reference_no' => 'DISP-001',
        'disposal_method' => 'Public auction',
        'approval_no' => 'AUTH-001',
        'approval_date' => '2026-09-16',
        'proceeds' => 5000,
    ])->assertSessionHasErrors('event_type');

    $this->post(route('inventory.assets.events.store', $asset), [
        'event_type' => 'unserviceable',
        'event_date' => '2026-09-15',
        'reference_no' => 'IIRUP-001',
        'condition_status' => 'non_usable',
        'appraised_value' => 6000,
    ])->assertSessionHasNoErrors();

    app(InventoryAssetLifecycleService::class)->record($asset, [
        'event_type' => 'disposal',
        'event_date' => '2026-09-17',
        'reference_no' => 'DISP-001',
        'disposal_method' => 'Public auction',
        'approval_no' => 'AUTH-001',
        'approval_date' => '2026-09-16',
        'proceeds' => 5000,
        'recipient' => 'Winning Bidder',
    ], $manager);

    $asset->refresh();
    $valuation = app(PpeDepreciationCalculator::class)->calculate($asset, '2026-09-17');

    expect($asset->lifecycle_status)->toBe(AssetLifecycleStatus::Disposed)
        ->and($asset->accounting_status)->toBe(AssetAccountingStatus::Derecognized)
        ->and($asset->derecognized_at->toDateString())->toBe('2026-09-17')
        ->and($asset->events()->where('event_type', 'disposal')->count())->toBe(1)
        ->and($asset->events()->where('event_type', 'derecognition')->count())->toBe(1)
        ->and($valuation->isRecognized)->toBeFalse()
        ->and($valuation->carryingAmount)->toBe('0.00');

    $this->delete(route('inventory.assets.destroy', $asset))
        ->assertRedirect(route('inventory.assets.index'));
    $this->assertNotSoftDeleted($asset);
});

test('employees cannot post PPE lifecycle transactions', function () {
    $employee = User::factory()->employee()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 100000]);

    $this->actingAs($employee)
        ->post(route('inventory.assets.events.store', $asset), [
            'event_type' => 'maintenance',
            'event_date' => today()->toDateString(),
            'reference_no' => 'WO-001',
            'amount' => 500,
        ])
        ->assertForbidden();
});

test('PPE reports use the requested historical as-of date', function () {
    $asset = InventoryAsset::factory()->create([
        'name' => 'Historical Report Asset',
        'property_number' => 'PPE-HIST-001',
        'acquisition_date' => '2025-01-01',
        'available_for_use_date' => '2025-01-01',
        'acquisition_cost' => 120000,
        'residual_value_percentage' => 5,
        'depreciation_useful_life_months' => 60,
    ]);
    InventoryAssetEvent::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'event_type' => InventoryAssetEventType::CapitalAddition,
        'event_date' => '2026-01-10',
        'amount' => 60000,
    ]);

    $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route('inventory.reports.print', [
            'document' => 'ppe-ledger-card',
            'as_of' => '2025-12-31',
            'period' => 'all',
        ]))
        ->assertOk()
        ->assertSee('Historical Report Asset')
        ->assertSee('120000.00')
        ->assertDontSee('180000.00');
});
