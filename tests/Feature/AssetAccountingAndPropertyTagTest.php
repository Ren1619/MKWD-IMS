<?php

use App\AssetAccountingClassification;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetCustodian;
use App\Models\InventoryAssetSubcategory;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('only PPE is depreciated and its configurable residual percentage is applied', function () {
    $this->travelTo(Carbon::parse('2026-08-14 09:00:00'));

    $ppe = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-08-14',
        'available_for_use_date' => '2025-08-14',
        'acquisition_cost' => 120000,
        'residual_value_percentage' => 10,
        'depreciation_useful_life_months' => 60,
    ]);
    $semiExpendable = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-08-14',
        'acquisition_cost' => 49999.99,
        'residual_value_percentage' => 25,
        'depreciation_useful_life_months' => 60,
    ]);
    $thresholdAsset = InventoryAsset::factory()->create([
        'acquisition_cost' => 50000,
    ]);

    expect($ppe->accounting_classification)->toBe(AssetAccountingClassification::Ppe)
        ->and($ppe->is_depreciable)->toBeTrue()
        ->and($ppe->residual_value_percentage)->toBe('10.00')
        ->and($ppe->residual_value)->toBe(12000.0)
        ->and($ppe->depreciation_amount)->toBe(21600.0)
        ->and($ppe->book_value)->toBe(98400.0)
        ->and($semiExpendable->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable)
        ->and($semiExpendable->is_depreciable)->toBeFalse()
        ->and($semiExpendable->residual_value_percentage)->toBeNull()
        ->and($semiExpendable->residual_value)->toBe(0.0)
        ->and($semiExpendable->depreciation_amount)->toBe(0.0)
        ->and($semiExpendable->book_value)->toBe(49999.99)
        ->and($thresholdAsset->accounting_classification)->toBe(AssetAccountingClassification::Ppe)
        ->and($thresholdAsset->property_tag_uuid)->toBeUuid();
});

test('PPE registration requires depreciation inputs while semi-expendable property does not', function () {
    $manager = User::factory()->inventoryManager()->create();
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $category->getKey(),
    ]);
    $baseData = [
        'category_id' => $category->getKey(),
        'subcategory_id' => $subcategory->getKey(),
        'serial_number' => 'ACCOUNTING-TEST-001',
        'name' => 'Accounting Test Asset',
        'unit_of_measure' => 'unit',
        'acquisition_date' => '2026-08-01',
        'depreciation_useful_life_months' => 60,
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
    ];

    $this->actingAs($manager)
        ->post(route('inventory.assets.store'), [
            ...$baseData,
            'acquisition_cost' => 50000,
        ])
        ->assertSessionHasErrors([
            'available_for_use_date',
            'residual_value_percentage',
        ]);

    $this->post(route('inventory.semi_expendable.store'), [
        ...$baseData,
        'serial_number' => 'ACCOUNTING-TEST-002',
        'acquisition_cost' => 49999,
    ])->assertSessionHasNoErrors();

    $asset = InventoryAsset::query()->where('serial_number', 'ACCOUNTING-TEST-002')->sole();

    expect($asset->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable)
        ->and($asset->residual_value_percentage)->toBeNull();
});

test('PPE and semi-expendable modules return separate lists', function () {
    $user = User::factory()->inventoryManager()->create();
    $ppe = InventoryAsset::factory()->create([
        'name' => 'Capitalized Network Server',
        'acquisition_cost' => 250000,
    ]);
    $semiExpendable = InventoryAsset::factory()->create([
        'name' => 'Durable Office Chair',
        'acquisition_cost' => 12000,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.assets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Assets/Index')
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $ppe->getKey())
            ->where('registryType', 'ppe')
            ->where('capitalizationThreshold', 50000));

    $this->get(route('inventory.semi_expendable.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $semiExpendable->getKey())
            ->where('registryType', 'semi_expendable'));
});

test('the semi-expendable module prioritizes cost availability accountability and exceptions', function () {
    $user = User::factory()->inventoryManager()->create();
    InventoryAsset::factory()->create([
        'acquisition_cost' => 10000,
    ]);
    InventoryAsset::factory()->create([
        'acquisition_cost' => 20000,
        'accounting_status' => 'issued_and_expensed',
        'accountability_status' => 'accountable',
    ]);
    InventoryAsset::factory()->create([
        'acquisition_cost' => 15000,
        'accounting_status' => 'issued_and_expensed',
        'available_for_reissue' => true,
    ]);
    InventoryAsset::factory()->create([
        'acquisition_cost' => 5000,
        'accounting_status' => 'issued_and_expensed',
        'physical_status' => 'missing',
        'relief_status' => 'pending',
        'accountability_status' => 'pending_relief',
    ]);
    $renewalOverdue = InventoryAsset::factory()->create([
        'acquisition_cost' => 6000,
        'accounting_status' => 'issued_and_expensed',
        'accountability_status' => 'accountable',
    ]);

    PropertyAccountabilityDocument::factory()->create([
        'inventory_asset_id' => $renewalOverdue->getKey(),
        'inventory_asset_custodian_id' => InventoryAssetCustodian::factory()->create([
            'inventory_asset_id' => $renewalOverdue->getKey(),
        ])->getKey(),
        'document_type' => 'ICS',
        'status' => 'active',
        'renewal_due_at' => today()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('inventory.semi_expendable.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrySummary.total', 5)
            ->where('registrySummary.acquisition_cost', 56000)
            ->where('registrySummary.on_hand', 1)
            ->where('registrySummary.issued_or_accountable', 3)
            ->where('registrySummary.available_for_reissue', 1)
            ->where('registrySummary.exceptions', 2));
});

test('PPE summary totals valuation availability and unique action items', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $reference = HrisReference::factory()->create();
    $assigned = InventoryAsset::factory()->create([
        'acquisition_cost' => 100000,
        'acquisition_date' => '2026-10-12',
        'available_for_use_date' => '2026-10-12',
        'current_custodian_reference_id' => $reference->id,
    ]);
    InventoryAsset::factory()->create([
        'acquisition_cost' => 100000,
        'acquisition_date' => '2026-10-12',
        'available_for_use_date' => '2026-10-12',
    ]);
    InventoryAsset::factory()->create([
        'acquisition_cost' => 100000,
        'acquisition_date' => '2026-10-12',
        'available_for_use_date' => '2026-10-12',
        'lifecycle_status' => 'under_maintenance',
        'condition_status' => 'needs_repair',
    ]);
    InventoryAsset::factory()->create(['acquisition_cost' => 1000]);

    $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route('inventory.assets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrySummary.total', 3)
            ->where('registrySummary.acquisition_cost', 300000)
            ->where('registrySummary.net_book_value', 300000)
            ->where('registrySummary.available_for_assignment', 1)
            ->where('registrySummary.requiring_action', 1));

    $this->actingAs(User::factory()->employee()->create(['hris_reference_id' => $reference->id]))
        ->get(route('inventory.assets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrySummary.total', 1)
            ->where('registrySummary.acquisition_cost', 100000)
            ->where('registrySummary.net_book_value', 100000)
            ->where('registrySummary.available_for_assignment', 0)
            ->where('assets.data.0.inventory_asset_id', $assigned->getKey()));
});

test('PPE can be explicitly sorted with newest records first', function () {
    $user = User::factory()->inventoryManager()->create();
    $olderAsset = InventoryAsset::factory()->create([
        'name' => 'Older PPE',
        'acquisition_cost' => 50000,
    ]);
    $newerAsset = InventoryAsset::factory()->create([
        'name' => 'Newer PPE',
        'acquisition_cost' => 50000,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.assets.index', [
            'sort' => 'inventory_asset_id',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assets.data.0.inventory_asset_id', $newerAsset->getKey())
            ->where('assets.data.1.inventory_asset_id', $olderAsset->getKey())
            ->where('filters.sort', 'inventory_asset_id'));
});

test('each property module enforces its capitalization range', function () {
    $manager = User::factory()->inventoryManager()->create();
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $category->getKey(),
    ]);
    $data = [
        'category_id' => $category->getKey(),
        'subcategory_id' => $subcategory->getKey(),
        'serial_number' => 'MODULE-RANGE-001',
        'name' => 'Module Range Test',
        'unit_of_measure' => 'unit',
        'acquisition_date' => '2026-08-01',
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
    ];

    $this->actingAs($manager)
        ->post(route('inventory.assets.store'), [
            ...$data,
            'acquisition_cost' => 49999.99,
        ])
        ->assertSessionHasErrors('acquisition_cost');

    $this->post(route('inventory.semi_expendable.store'), [
        ...$data,
        'acquisition_cost' => 50000,
    ])->assertSessionHasErrors('acquisition_cost');

    expect(InventoryAsset::query()->exists())->toBeFalse();
});

test('authorized managers can revise PPE residual settings with a documented basis', function () {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_date' => '2025-01-01',
        'available_for_use_date' => '2025-01-15',
        'acquisition_cost' => 100000,
        'residual_value_percentage' => 5,
    ]);

    $this->actingAs($manager)
        ->patch(route('inventory.assets.update_accounting', $asset), [
            'available_for_use_date' => '2025-02-01',
            'residual_value_percentage' => 7.5,
            'residual_value_basis' => 'Approved disposal-value study dated 2026-08-20.',
        ])
        ->assertSessionHasNoErrors();

    expect($asset->fresh()->residual_value_percentage)->toBe('7.50')
        ->and($asset->fresh()->residual_value)->toBe(7500.0)
        ->and($asset->fresh()->available_for_use_date->toDateString())->toBe('2025-02-01')
        ->and($asset->fresh()->residual_value_basis)->toBe('Approved disposal-value study dated 2026-08-20.');

    $this->actingAs(User::factory()->employee()->create())
        ->patch(route('inventory.assets.update_accounting', $asset), [
            'available_for_use_date' => '2025-02-01',
            'residual_value_percentage' => 10,
            'residual_value_basis' => 'Unauthorized change.',
        ])
        ->assertForbidden();
});

test('managers can print permanent QR tags and public scans expose only safe property details', function () {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create([
        'name' => 'Tagged Distribution Meter',
        'property_number' => 'PPE-TAG-0001',
        'account_code' => '10605020',
        'serial_number' => 'SECRET-SERIAL-001',
        'location' => 'Restricted Engineering Vault',
        'acquisition_date' => '2025-06-15',
        'acquisition_cost' => 175000,
    ]);
    $custodian = HrisReference::factory()->create(['name' => 'Juan Dela Cruz']);
    $asset->update(['current_custodian_reference_id' => $custodian->id]);
    PropertyAccountabilityDocument::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'document_type' => 'PAR',
        'document_no' => 'PAR-2026-000123',
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.assets.property_tag', $asset))
        ->assertOk()
        ->assertSee('Permanent QR property tag')
        ->assertSee('data:image/svg+xml;base64,', escape: false)
        ->assertSee($asset->property_tag_uuid)
        ->assertSee('10605020')
        ->assertSee('PPE-TAG-0001')
        ->assertSee('Restricted Engineering Vault')
        ->assertSee('PAR-2026-000123')
        ->assertSee('Juan Dela Cruz')
        ->assertSee('175,000.00')
        ->assertSee('2025-06-15')
        ->assertSee('Account code')
        ->assertSee('PAR no.')
        ->assertSee('Accountable person');

    auth()->logout();

    $this->get(route('property-tags.show', ['asset' => $asset->property_tag_uuid]))
        ->assertOk()
        ->assertSee('Verified property tag')
        ->assertSee('Tagged Distribution Meter')
        ->assertSee('PPE-TAG-0001')
        ->assertDontSee('10605020')
        ->assertDontSee('Restricted Engineering Vault')
        ->assertDontSee('175000');

    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.assets.property_tag', $asset))
        ->assertForbidden();
});

test('an active accountability document prevents crossing the capitalization threshold', function () {
    $manager = User::factory()->inventoryManager()->create();
    $reference = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create([
        'acquisition_cost' => 49000,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $reference->id,
    ]);

    $this->patch(route('inventory.assets.update', $asset), [
        'category_id' => $asset->category_id,
        'subcategory_id' => $asset->subcategory_id,
        'serial_number' => $asset->serial_number,
        'property_number' => $asset->property_number,
        'name' => $asset->name,
        'unit_of_measure' => $asset->unit_of_measure,
        'fund_cluster' => $asset->fund_cluster,
        'brand' => $asset->brand,
        'model' => $asset->model,
        'description' => $asset->description,
        'location' => $asset->location,
        'acquisition_date' => $asset->acquisition_date->toDateString(),
        'available_for_use_date' => $asset->acquisition_date->toDateString(),
        'acquisition_cost' => 50000,
        'residual_value_percentage' => 5,
        'depreciation_useful_life_months' => 60,
    ])->assertSessionHasErrors('acquisition_cost');

    expect($asset->fresh()->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable);
});

test('PPE reports exclude semi-expendable property from depreciation', function () {
    $user = User::factory()->inventoryManager()->create();
    InventoryAsset::factory()->create([
        'name' => 'PPE Report Asset',
        'acquisition_cost' => 50000,
    ]);
    InventoryAsset::factory()->create([
        'name' => 'Semi-Expendable Report Asset',
        'acquisition_cost' => 49999,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.reports.print', 'depreciation-schedule'))
        ->assertOk()
        ->assertSee('PPE Report Asset')
        ->assertDontSee('Semi-Expendable Report Asset');
});

test('permanent tags use the correct accountability document and prescribed color without changing QR identity', function (float $cost, string $type, string $color) {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost]);
    $uuid = $asset->property_tag_uuid;
    PropertyAccountabilityDocument::factory()->create(['inventory_asset_id' => $asset->getKey(), 'document_type' => $type, 'document_no' => $type.'-TAG-CORRECT', 'status' => 'returned']);
    PropertyAccountabilityDocument::factory()->create(['inventory_asset_id' => $asset->getKey(), 'document_type' => $type === 'ICS' ? 'PAR' : 'ICS', 'document_no' => 'OTHER-TYPE-IGNORE']);
    $response = $this->actingAs($manager)->get(route('inventory.assets.property_tag', $asset));
    $response->assertSuccessful()->assertSee($type.' no.')->assertSee($type.'-TAG-CORRECT')->assertSee($color.' tag')
        ->assertSee('tag-'.strtolower($color), escape: false)->assertSee('(returned)')->assertDontSee('OTHER-TYPE-IGNORE')->assertSee($uuid);
    expect($asset->fresh()->property_tag_uuid)->toBe($uuid);
})->with([[49999.99, 'ICS', 'Yellow'], [50000.0, 'PAR', 'Green']]);

test('unissued semi-expendable property prints an ICS placeholder and keeps employee tag access restricted', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 8000]);
    $this->actingAs(User::factory()->inventoryManager()->create())->get(route('inventory.assets.property_tag', $asset))->assertSuccessful()
        ->assertSee('ICS no.')->assertSee('Not issued')->assertDontSee('PAR no.');
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.assets.property_tag', $asset))->assertForbidden();
});
