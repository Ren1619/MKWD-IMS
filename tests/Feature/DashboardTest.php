<?php

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
    InventoryItemBatch::factory()->create([
        'quantity_in' => 25,
        'quantity_remaining' => 25,
        'received_at' => today(),
    ]);
    InventoryItemStockOut::factory()->create([
        'quantity' => 7,
        'stocked_out_at' => today(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Dashboard')
            ->where('metrics.stock_in_today', 25)
            ->where('metrics.stock_out_today', 7)
            ->has('dailyMovements', 7)
            ->has('recentStockOuts', 1)
        );
});

test('the HRIS references module is no longer exposed', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/inventory/hris-references')
        ->assertNotFound();
});
