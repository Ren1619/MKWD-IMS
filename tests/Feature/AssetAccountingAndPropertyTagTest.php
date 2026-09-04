<?php

use App\AssetAccountingClassification;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
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

    $this->post(route('inventory.assets.store'), [
        ...$baseData,
        'serial_number' => 'ACCOUNTING-TEST-002',
        'acquisition_cost' => 49999,
    ])->assertSessionHasNoErrors();

    $asset = InventoryAsset::query()->where('serial_number', 'ACCOUNTING-TEST-002')->sole();

    expect($asset->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable)
        ->and($asset->residual_value_percentage)->toBeNull();
});

test('PPE and semi-expendable registries return separate lists', function () {
    $user = User::factory()->employee()->create();
    $ppe = InventoryAsset::factory()->create([
        'name' => 'Capitalized Network Server',
        'acquisition_cost' => 250000,
    ]);
    $semiExpendable = InventoryAsset::factory()->create([
        'name' => 'Durable Office Chair',
        'acquisition_cost' => 12000,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.assets.index', [
            'accounting_classification' => 'ppe',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Assets/Index')
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $ppe->getKey())
            ->where('filters.accounting_classification', 'ppe')
            ->where('capitalizationThreshold', 50000));

    $this->get(route('inventory.assets.index', [
        'accounting_classification' => 'semi_expendable',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.inventory_asset_id', $semiExpendable->getKey())
            ->where('filters.accounting_classification', 'semi_expendable'));
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
        'serial_number' => 'SECRET-SERIAL-001',
        'location' => 'Restricted Engineering Vault',
        'acquisition_cost' => 175000,
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.assets.property_tag', $asset))
        ->assertOk()
        ->assertSee('Permanent QR property tag')
        ->assertSee('data:image/svg+xml;base64,', escape: false)
        ->assertSee($asset->property_tag_uuid);

    auth()->logout();

    $this->get(route('property-tags.show', ['asset' => $asset->property_tag_uuid]))
        ->assertOk()
        ->assertSee('Verified property tag')
        ->assertSee('Tagged Distribution Meter')
        ->assertSee('PPE-TAG-0001')
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
    $user = User::factory()->employee()->create();
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
