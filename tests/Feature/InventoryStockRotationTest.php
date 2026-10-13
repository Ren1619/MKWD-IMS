<?php

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\InventoryStockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->manager = User::factory()->inventoryManager()->create();
    $this->item = InventoryItem::factory()->create(['quantity' => 0]);
    $this->stock = app(InventoryStockService::class);
});
function rotationReceipt(string $reference, string $received, ?string $expiry, int $quantity = 5, string $cost = '10.00'): InventoryItemBatch
{
    test()->stock->stockIn(test()->item, ['quantity' => $quantity, 'unit_cost' => $cost, 'received_at' => $received, 'expiration_date' => $expiry, 'reference_no' => $reference]);

    return test()->item->batches()->where('reference_no', $reference)->firstOrFail();
}
test('FEFO selects earliest dated expiry before older undated stock and preserves actual batch costs', function () {
    $undated = rotationReceipt('UNDATED', '2026-09-01', null, 5, '11.00');
    $later = rotationReceipt('LATER', '2026-09-02', '2026-12-01', 5, '12.00');
    $sooner = rotationReceipt('SOONER', '2026-10-01', '2026-10-20', 5, '13.00');
    $issue = $this->stock->stockOut($this->item, ['quantity' => 7]);
    expect($issue->allocations->sortBy('inventory_item_stock_out_allocation_id')->pluck('inventory_item_batch_id')->all())->toBe([$sooner->getKey(), $later->getKey()])
        ->and($issue->allocations->sortBy('inventory_item_stock_out_allocation_id')->pluck('unit_cost')->all())->toBe(['13.00', '12.00'])
        ->and($sooner->fresh()->quantity_remaining)->toBe(0)->and($later->fresh()->quantity_remaining)->toBe(3)->and($undated->fresh()->quantity_remaining)->toBe(5);
});
test('FIFO remains deterministic for undated batches and equal expiration dates', function (?string $expiry) {
    $first = rotationReceipt('FIRST', '2026-09-01', $expiry, 2);
    $second = rotationReceipt('SECOND', '2026-09-01', $expiry, 2);
    $last = rotationReceipt('LAST', '2026-09-02', $expiry, 2);
    $issue = $this->stock->stockOut($this->item, ['quantity' => 5]);
    expect($issue->allocations->pluck('inventory_item_batch_id')->all())->toBe([$first->getKey(), $second->getKey(), $last->getKey()]);
})->with([null, '2026-11-01']);
test('expired and future receipts stay in book inventory but are excluded from issuance and availability', function () {
    $expired = rotationReceipt('EXPIRED', '2026-10-01', '2026-10-11');
    $future = rotationReceipt('FUTURE', '2026-10-13', null);
    $today = rotationReceipt('TODAY', '2026-10-12', '2026-10-12');
    expect($this->item->fresh()->quantity)->toBe(15)->and($this->stock->issuableQuantity($this->item))->toBe(5);
    $loaded = $this->item->fresh()->load('batches');
    expect($loaded->issuable_quantity)->toBe(5)->and($this->item->fresh()->issuable_quantity)->toBe(5);
    $issue = $this->stock->stockOut($this->item, ['quantity' => 5]);
    expect($issue->allocations->sole()->inventory_item_batch_id)->toBe($today->getKey())
        ->and($expired->fresh()->quantity_remaining)->toBe(5)->and($future->fresh()->quantity_remaining)->toBe(5)->and($this->item->fresh()->quantity)->toBe(10);
});
test('ineligible quantities fail atomically and backdating cannot resurrect expired stock', function () {
    rotationReceipt('EXPIRED', '2026-10-01', '2026-10-11');
    rotationReceipt('FUTURE', '2026-10-13', null);
    expect(fn () => $this->stock->stockOut($this->item, ['quantity' => 1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->stock->stockOut($this->item, ['quantity' => 1, 'stocked_out_at' => '2026-10-10']))->toThrow(InvalidArgumentException::class);
    expect($this->item->stockOuts()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(10);
});
test('historical issuance cannot consume a batch received after its issue date and future issues are rejected', function () {
    rotationReceipt('RECENT', '2026-10-10', null);
    expect(fn () => $this->stock->stockOut($this->item, ['quantity' => 1, 'stocked_out_at' => '2026-10-09']))->toThrow(InvalidArgumentException::class);
    $this->actingAs($this->manager)->patch(route('inventory.items.stock_out', $this->item), ['quantity' => 1, 'recipient_name' => 'Recipient', 'stocked_out_at' => '2026-10-13'])->assertSessionHasErrors('stocked_out_at');
    expect($this->item->stockOuts()->count())->toBe(0);
});
test('supply review reserves only issuable batches and keeps unusable stock out of procurement availability', function () {
    rotationReceipt('EXPIRED', '2026-10-01', '2026-10-11');
    rotationReceipt('USABLE', '2026-10-01', null, 2);
    $request = SupplyRequest::factory()->create(['status' => 'approved']);
    $line = SupplyRequestLine::factory()->create(['supply_request_id' => $request->id, 'inventory_item_id' => $this->item->getKey(), 'is_new_item' => false, 'quantity_requested' => 4, 'estimated_unit_cost' => 10]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $request), ['action' => 'review', 'attested' => true])->assertSessionHasNoErrors();
    expect($line->fresh()->quantity_reserved)->toBe(2)->and($request->fresh()->status)->toBe('awaiting_replenishment')->and($this->item->fresh()->quantity)->toBe(7);
    $this->get(route('inventory.items.index'))->assertInertia(fn (Assert $page) => $page->where('items.data.0.issuable_quantity', 2)->where('items.data.0.quantity', 7));
    $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page->where('items.0.issuable_quantity', 2));
});
test('stock expiring after reservation blocks release and allows a fresh review', function () {
    rotationReceipt('EXPIRES-TODAY', '2026-10-01', '2026-10-12');
    $request = SupplyRequest::factory()->create(['status' => 'approved']);
    $line = SupplyRequestLine::factory()->create(['supply_request_id' => $request->id, 'inventory_item_id' => $this->item->getKey(), 'is_new_item' => false, 'quantity_requested' => 5, 'estimated_unit_cost' => 10]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $request), ['action' => 'review', 'attested' => true])->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe('ready_for_release');
    $this->travelTo(now()->addDay());
    $this->patch(route('inventory.requests.transition', $request), ['action' => 'release', 'attested' => true])->assertSessionHasErrors('action');
    expect($line->fresh()->quantity_released)->toBe(0)->and($this->item->stockOuts()->count())->toBe(0);
    $this->patch(route('inventory.requests.transition', $request), ['action' => 'review', 'attested' => true])->assertSessionHasNoErrors();
    expect($line->fresh()->quantity_reserved)->toBe(0)->and($request->fresh()->status)->toBe('awaiting_replenishment');
});
test('duplicate item lines are checked together before any release writes', function () {
    rotationReceipt('EXPIRED', '2026-10-01', '2026-10-11', 4);
    rotationReceipt('USABLE', '2026-10-01', null, 3);
    $request = SupplyRequest::factory()->create(['status' => 'ready_for_release']);
    SupplyRequestLine::factory()->count(2)->create(['supply_request_id' => $request->id, 'inventory_item_id' => $this->item->getKey(), 'is_new_item' => false, 'quantity_requested' => 3, 'quantity_approved' => 3, 'quantity_reserved' => 3]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $request), ['action' => 'release', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->item->stockOuts()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(7);
});
