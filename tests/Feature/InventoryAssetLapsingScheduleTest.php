<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\PpeDepreciationCalculator;
use Inertia\Testing\AssertableInertia as Assert;

test('PPE accounting offers all employees including inactive signatories', function () {
    $employee = HrisReference::factory()->create(['is_active' => false]);
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 120000]);
    $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route('inventory.assets.show', $asset))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Inventory/Assets/Show')->has('employees', 1)
        ->where('employees.0.id', $employee->id));
});

test('lapsing schedule uses monthly historical valuations and both signatories', function () {
    $asset = InventoryAsset::factory()->create([
        'acquisition_cost' => 120000, 'acquisition_date' => '2025-01-01',
        'available_for_use_date' => '2025-01-01', 'depreciation_useful_life_months' => 60,
    ]);
    $employees = HrisReference::factory()->count(2)->create();
    $expected = app(PpeDepreciationCalculator::class)->calculate($asset, '2026-12-31');
    $response = $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route('inventory.assets.lapsing_schedule', [$asset, 'year' => 2026,
            'prepared_by' => $employees[0]->id, 'certified_by' => $employees[1]->id]));
    $response->assertOk()->assertSee('Lapsing Schedule')->assertSee($employees[0]->name)
        ->assertSee($employees[1]->name)->assertViewHas('rows', fn ($rows) => count($rows) === 12
            && $rows[11]['valuation']->carryingAmount === $expected->carryingAmount);
});

test('lapsing schedule validates year and employee signatories', function () {
    $asset = InventoryAsset::factory()->create();
    $employee = HrisReference::factory()->create();
    $this->actingAs(User::factory()->inventoryManager()->create())
        ->getJson(route('inventory.assets.lapsing_schedule', [$asset, 'year' => 'invalid',
            'prepared_by' => $employee->id, 'certified_by' => $employee->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['year', 'certified_by']);
});

test('lapsing schedule protects other custodians and rejects semi expendable assets', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 10000]);
    $employees = HrisReference::factory()->count(2)->create();
    $url = route('inventory.assets.lapsing_schedule', [$asset, 'year' => 2026,
        'prepared_by' => $employees[0]->id, 'certified_by' => $employees[1]->id]);
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->inventoryManager()->create())->get($url)->assertNotFound();
});

test('periods before acquisition have zero balances', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 120000,
        'acquisition_date' => '2026-06-01', 'available_for_use_date' => '2026-06-01']);
    $employees = HrisReference::factory()->count(2)->create();
    $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route('inventory.assets.lapsing_schedule', [$asset, 'year' => 2026,
            'prepared_by' => $employees[0]->id, 'certified_by' => $employees[1]->id]))
        ->assertOk()->assertViewHas('opening', null)
        ->assertViewHas('rows', fn ($rows) => $rows[0]['valuation'] === null && $rows[0]['expense'] === 0.0
            && $rows[5]['valuation'] !== null);
});
