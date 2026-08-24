<?php

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard reports daily-changing inventory statistics', function () {
    $user = User::factory()->create();
    $movementItem = InventoryItem::factory()->create([
        'quantity' => 25,
        'reorder_point' => 10,
    ]);
    InventoryItemBatch::factory()->for($movementItem, 'item')->create([
        'quantity_in' => 25,
        'quantity_remaining' => 25,
        'received_at' => today(),
    ]);
    InventoryItemStockOut::factory()->for($movementItem, 'item')->create([
        'quantity' => 7,
        'stocked_out_at' => today(),
    ]);
    $expiryTrackedItem = InventoryItem::factory()->create([
        'quantity' => 20,
        'reorder_point' => 5,
    ]);
    InventoryItem::factory()->create([
        'quantity' => 5,
        'reorder_point' => 5,
    ]);
    InventoryItemBatch::factory()->for($expiryTrackedItem, 'item')->create([
        'quantity_in' => 10,
        'quantity_remaining' => 10,
        'received_at' => today()->subMonths(2),
        'expiration_date' => today()->subDay(),
    ]);
    InventoryItemBatch::factory()->for($expiryTrackedItem, 'item')->create([
        'batch_number' => 2,
        'quantity_in' => 10,
        'quantity_remaining' => 10,
        'received_at' => today()->subMonth(),
        'expiration_date' => today()->addDays(15),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Dashboard')
            ->where('metrics.stock_in_today', 25)
            ->where('metrics.stock_out_today', 7)
            ->where('metrics.low_stock', 1)
            ->where('metrics.expired_batches', 1)
            ->where('metrics.expiring_batches', 1)
            ->has('dailyMovements', 7)
            ->has('recentStockOuts', 1)
        );
});

test('the HRIS references module is no longer exposed', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/inventory/hris-references')
        ->assertNotFound();
});
