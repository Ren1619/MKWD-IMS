<?php

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\InventoryItemStockOutAllocation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('receipts retain their own accounting details and releases use FIFO by receipt date', function () {
    $user = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 5,
        'unit_cost' => 30,
        'received_at' => '2026-08-10',
        'expiration_date' => '2028-08-10',
        'source' => 'New Supplier',
        'reference_no' => 'DR-NEW',
        'batch_notes' => 'Delivered first, but received later.',
    ])->assertRedirect(route('inventory.items.index'));

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 4,
        'unit_cost' => 20,
        'received_at' => '2026-08-01',
        'expiration_date' => '2027-08-01',
        'source' => 'Older Supplier',
        'reference_no' => 'DR-OLD',
    ])->assertRedirect(route('inventory.items.index'));

    $olderBatch = $item->batches()->where('reference_no', 'DR-OLD')->sole();

    $this->actingAs($user)->patch(route('inventory.items.stock_out', $item), [
        'quantity' => 4,
        'recipient_name' => 'Accounting Test Recipient',
        'stocked_out_at' => '2026-08-12',
    ])->assertRedirect(route('inventory.items.index'));

    $item->refresh()->load(['batches', 'stockOuts.allocations']);
    $stockOut = $item->stockOuts->sole();

    expect($olderBatch->fresh()->quantity_remaining)->toBe(0)
        ->and($stockOut->allocations)->toHaveCount(1)
        ->and($stockOut->allocations->sole()->inventory_item_batch_id)->toBe($olderBatch->getKey())
        ->and($stockOut->allocations->sole()->unit_cost)->toBe('20.00')
        ->and($stockOut->total_cost)->toBe('80.00')
        ->and($item->inventory_value)->toBe('150.00')
        ->and($item->next_expiration_date)->toBe('2028-08-10');
});

test('a receipt requires a valid unit cost and leaves stock unchanged when validation fails', function () {
    $user = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($user)->patch(route('inventory.items.stock_in', $item), [
        'quantity' => 5,
        'unit_cost' => -1,
        'received_at' => '2026-08-10',
    ])->assertSessionHasErrors('unit_cost');

    expect($item->fresh()->quantity)->toBe(0)
        ->and($item->batches()->count())->toBe(0);
});

test('employees can inspect receipt batches and FIFO release allocations for active or archived items', function () {
    $user = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create(['quantity' => 7]);
    $batch = InventoryItemBatch::factory()->for($item, 'item')->create([
        'batch_number' => 1,
        'quantity_in' => 10,
        'quantity_remaining' => 7,
        'unit_cost' => 125.50,
        'received_at' => '2026-07-15',
        'expiration_date' => '2028-07-15',
        'source' => 'Traceable Supplier',
        'reference_no' => 'DR-TRACE-001',
        'notes' => 'Receipt retained for inspection.',
    ]);
    $stockOut = InventoryItemStockOut::factory()->for($item, 'item')->create([
        'recipient_reference_id' => null,
        'recipient_name' => 'Juan Dela Cruz',
        'quantity' => 3,
        'stocked_out_at' => '2026-08-01',
    ]);

    InventoryItemStockOutAllocation::factory()->create([
        'inventory_item_stock_out_id' => $stockOut->getKey(),
        'inventory_item_batch_id' => $batch->getKey(),
        'quantity' => 3,
        'unit_cost' => 125.50,
    ]);

    $item->delete();

    $this->actingAs($user)
        ->getJson(route('inventory.items.show', $item))
        ->assertOk()
        ->assertJsonPath('item.inventory_item_id', $item->getKey())
        ->assertJsonPath('item.inventory_value', '878.50')
        ->assertJsonPath('item.batches.0.reference_no', 'DR-TRACE-001')
        ->assertJsonPath('item.batches.0.source', 'Traceable Supplier')
        ->assertJsonPath('releases.total', 1)
        ->assertJsonPath('releases.data.0.recipient_name', 'Juan Dela Cruz')
        ->assertJsonPath('releases.data.0.total_cost', '376.50')
        ->assertJsonPath('releases.data.0.allocations.0.inventory_item_batch_id', $batch->getKey())
        ->assertJsonPath('releases.data.0.allocations.0.batch.batch_number', 1);
});

test('inventory managers can configure item-specific replenishment controls', function () {
    $manager = User::factory()->inventoryManager()->create();
    $employee = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($manager)->patch(route('inventory.items.update_replenishment', $item), [
        'reorder_point' => 18,
        'reorder_quantity' => 60,
    ])->assertRedirect(route('inventory.items.index'));

    expect($item->fresh()->reorder_point)->toBe(18)
        ->and($item->fresh()->reorder_quantity)->toBe(60);

    $this->actingAs($employee)->patch(route('inventory.items.update_replenishment', $item), [
        'reorder_point' => 1,
        'reorder_quantity' => 2,
    ])->assertForbidden();

    expect($item->fresh()->reorder_point)->toBe(18);
});

test('inventory alerts use per-item reorder points and remaining batch expiration dates', function () {
    $user = User::factory()->employee()->create();
    $lowStockItem = InventoryItem::factory()->create([
        'quantity' => 5,
        'reorder_point' => 5,
    ]);
    $expiringItem = InventoryItem::factory()->create([
        'quantity' => 20,
        'reorder_point' => 5,
    ]);

    InventoryItemBatch::factory()->for($lowStockItem, 'item')->create([
        'quantity_in' => 5,
        'quantity_remaining' => 5,
        'expiration_date' => today()->subDay(),
    ]);
    InventoryItemBatch::factory()->for($expiringItem, 'item')->create([
        'quantity_in' => 20,
        'quantity_remaining' => 20,
        'expiration_date' => today()->addDays(15),
    ]);

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['alert' => 'low_stock']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $lowStockItem->getKey())
            ->where('items.data.0.is_low_stock', true));

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['alert' => 'expired']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $lowStockItem->getKey())
            ->where('items.data.0.expiration_status', 'expired'));

    $this->actingAs($user)
        ->get(route('inventory.items.index', ['alert' => 'expiring']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.inventory_item_id', $expiringItem->getKey())
            ->where('items.data.0.expiration_status', 'expiring'));
});
