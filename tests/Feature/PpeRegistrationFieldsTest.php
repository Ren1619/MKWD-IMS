<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\User;
use Illuminate\Support\Facades\Process;

test('PPE registration removes unused WI extras while retaining fields used by other records', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/PpeRegistrationFields.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $fields = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    expect($fields['ppe'])->not->toContain('manufacturer', 'disbursement_voucher_no')
        ->toContain('item_description', 'serial_number', 'property_number', 'purchase_order_no', 'supplier_name', 'acquisition_cost', 'location')
        ->toContain('brand', 'model', 'old_property_number', 'fund_cluster', 'directly_attributable_costs', 'available_for_use_date', 'residual_value_percentage', 'specifications', 'acceptance_date')
        ->and($fields['semi_expendable'])->toContain('manufacturer', 'disbursement_voucher_no');
});

test('registration accepts the cleaned PPE form and preserves extra fields for semi-expendable records', function (string $registry, int $cost) {
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create(['inventory_asset_category_id' => $category->getKey()]);
    $data = [
        'category_id' => $category->getKey(), 'subcategory_id' => $subcategory->getKey(),
        'item_description' => 'WI-007 property', 'serial_number' => 'WI007-001', 'unit_of_measure' => 'unit',
        'acquisition_date' => today()->toDateString(), 'acquisition_cost' => $cost,
        'available_for_use_date' => today()->toDateString(), 'residual_value_percentage' => 5,
        'residual_value_basis' => 'Default policy.', 'depreciation_useful_life_months' => 60,
        'useful_life_override_reason' => 'Manufacturer rated life.', 'lifecycle_status' => 'active', 'condition_status' => 'good',
    ];
    if ($registry === 'semi_expendable') {
        $data += ['manufacturer' => 'Retained manufacturer', 'disbursement_voucher_no' => 'DV-KEPT-001'];
    }

    $this->actingAs(User::factory()->inventoryManager()->create())->post(route('inventory.'.$registry.'.store'), $data)
        ->assertRedirect()->assertSessionHasNoErrors();
    $asset = InventoryAsset::query()->sole();
    expect($asset->item_description)->toBe($data['item_description'])
        ->and($asset->manufacturer)->toBe($data['manufacturer'] ?? null)
        ->and($asset->disbursement_voucher_no)->toBe($data['disbursement_voucher_no'] ?? null);
})->with([['assets', 75000], ['semi_expendable', 7500]]);
