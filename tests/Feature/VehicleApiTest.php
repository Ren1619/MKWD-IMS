<?php

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\User;

test('it requires a bearer token', function () {
    $this->getJson(route('api.vehicles.index'))
        ->assertUnauthorized();
});

test('it does not accept a browser session instead of a bearer token', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('api.vehicles.index'))
        ->assertUnauthorized();
});

test('it requires the vehicles read ability', function () {
    $token = User::factory()->create()
        ->createToken('wrong-scope', ['profile:read'])
        ->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.vehicles.index'))
        ->assertForbidden();
});

test('it rejects tokens owned by inactive users', function () {
    $token = User::factory()->inactive()->create()
        ->createToken('inactive-owner', ['vehicles:read'])
        ->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.vehicles.index'))
        ->assertForbidden();
});

test('it returns every current and future vehicle without unrelated or archived assets', function () {
    $token = User::factory()->create()
        ->createToken('authorized-recipient', ['vehicles:read'])
        ->plainTextToken;

    $vehicleCategory = InventoryAssetCategory::factory()->create([
        'code' => 'DYNAMIC-FLEET',
        'name' => 'Managed Fleet',
        'is_vehicle' => true,
    ]);
    $vehicle = InventoryAsset::factory()->create([
        'category_id' => $vehicleCategory->getKey(),
        'subcategory_id' => null,
        'serial_number' => 'VEHICLE-001',
        'property_number' => 'PROP-VEHICLE-001',
        'name' => 'Service Vehicle',
        'brand' => 'Toyota',
        'model' => 'Hilux',
    ]);
    $futureVehicle = InventoryAsset::factory()->create([
        'category_id' => $vehicleCategory->getKey(),
        'subcategory_id' => null,
        'serial_number' => 'VEHICLE-002',
        'property_number' => 'PROP-VEHICLE-002',
        'name' => 'New Service Vehicle',
    ]);
    $archivedVehicle = InventoryAsset::factory()->create([
        'category_id' => $vehicleCategory->getKey(),
        'subcategory_id' => null,
    ]);
    $archivedVehicle->delete();
    $nonVehicleCategory = InventoryAssetCategory::factory()->create([
        'is_vehicle' => false,
    ]);
    InventoryAsset::factory()->create([
        'category_id' => $nonVehicleCategory->getKey(),
        'subcategory_id' => null,
    ]);

    $vehicleCategory->update([
        'code' => 'RENAMED-FLEET',
        'name' => 'Renamed Managed Fleet',
    ]);

    $this->withToken($token)
        ->getJson(route('api.vehicles.index'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $vehicle->getKey())
        ->assertJsonPath('data.0.serial_number', 'VEHICLE-001')
        ->assertJsonPath('data.0.brand', 'Toyota')
        ->assertJsonPath('data.0.model', 'Hilux')
        ->assertJsonPath('data.0.category.code', 'RENAMED-FLEET')
        ->assertJsonPath('data.0.lifecycle_status', 'active')
        ->assertJsonPath('data.0.condition_status', 'good')
        ->assertJsonPath('data.1.id', $futureVehicle->getKey())
        ->assertJsonMissing(['id' => $archivedVehicle->getKey()]);
});
