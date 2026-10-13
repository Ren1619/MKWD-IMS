<?php

use App\Models\InventoryItem;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 9)->startOfDay());
    $this->manager = User::factory()->inventoryManager()->create();
    $this->record = SupplyRequest::factory()->create(['status' => 'ready_for_release', 'isf_no' => '10-09-007', 'office_name' => 'Operations', 'responsibility_center_code' => 'RC-1']);
    $this->item = InventoryItem::factory()->create();
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 10, 'unit_cost' => 25]);
    $this->line = $this->record->lines()->create(['inventory_item_id' => $this->item->id ?? $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => 'pc', 'quantity_requested' => 10, 'quantity_approved' => 10, 'quantity_reserved' => 4]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertSessionHasNoErrors();
    $this->slip = IssuanceSlip::query()->sole();
});

test('partial releases get independent slip numbers and immutable issued quantities', function () {
    expect($this->slip->slip_no)->toBe('10-09-001')->and($this->slip->snapshot['isf_no'])->toBe('10-09-007')->and($this->slip->lines[0]['quantity_issued'])->toBe(4);
    $this->line->update(['quantity_reserved' => 10]);
    $this->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertSessionHasNoErrors();
    $slips = $this->record->issuanceSlips()->orderBy('id')->get();
    expect($slips)->toHaveCount(2)->and($slips[1]->slip_no)->toBe('10-09-002')->and($slips[1]->lines[0]['quantity_issued'])->toBe(6)->and($slips[0]->lines[0]['quantity_issued'])->toBe(4)->and($this->item->fresh()->quantity)->toBe(0);
});

test('liquidation records utilization and approvals without affecting stock', function () {
    $route = route('inventory.liquidations.transition', $this->slip);
    $this->patch($route, ['action' => 'submit', 'attested' => true, 'liquidation_date' => '2026-10-09', 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 3]]])->assertSessionHasNoErrors();
    expect($this->slip->fresh()->lines[0]['balance'])->toBe(1)->and($this->item->fresh()->quantity)->toBe(6);
    $this->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasErrors('action');
    $checker = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $this->actingAs($checker)->patch($route, ['action' => 'check', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($approver)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    expect($this->slip->fresh()->status)->toBe('approved')->and($this->slip->fresh()->history)->toHaveCount(3)->and($this->item->fresh()->quantity)->toBe(6);
    $this->get(route('inventory.liquidations.print', $this->slip))->assertOk()->assertSee('10-09-001')->assertSee('QUANTITY UTILIZED')->assertSee($checker->name);
    $this->patch($route, ['action' => 'submit', 'attested' => true, 'liquidation_date' => '2026-10-09', 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 0]]])->assertSessionHasErrors('action');
});

test('invalid utilization or substituted lines cannot be submitted', function (int $quantity, bool $foreignLine) {
    $this->patch(route('inventory.liquidations.transition', $this->slip), ['action' => 'submit', 'attested' => true, 'liquidation_date' => '2026-10-09', 'lines' => [['supply_request_line_id' => $foreignLine ? 99999 : $this->line->id, 'quantity_utilized' => $quantity]]])->assertSessionHasErrors();
    expect($this->slip->fresh()->status)->toBe('pending')->and($this->slip->fresh()->history)->toBe([]);
})->with([[5, false], [-1, false], [2, true]]);

test('liquidation requires attestation and a date on or after issuance', function () {
    $this->patch(route('inventory.liquidations.transition', $this->slip), ['action' => 'submit', 'attested' => false, 'liquidation_date' => '2026-10-09', 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 2]]])->assertSessionHasErrors('attested');
    $this->patch(route('inventory.liquidations.transition', $this->slip), ['action' => 'submit', 'attested' => true, 'liquidation_date' => '2026-10-08', 'lines' => [['supply_request_line_id' => $this->line->id, 'quantity_utilized' => 2]]])->assertSessionHasErrors('liquidation_date');
});

test('employees cannot mutate liquidation or print another request', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->patch(route('inventory.liquidations.transition', $this->slip), ['action' => 'check', 'attested' => true])->assertForbidden();
    $this->get(route('inventory.liquidations.print', $this->slip))->assertForbidden();
    $this->actingAs($this->record->requester)->get(route('inventory.liquidations.print', $this->slip))->assertOk();
});

test('awaiting liquidation queue includes outstanding released slips and search finds slip numbers', function () {
    $this->get(route('inventory.requests.index', ['queue' => 'awaiting_liquidation', 'search' => '10-09-001']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.issuance_slips.0.slip_no', '10-09-001'));
    $this->slip->update(['status' => 'approved']);
    $this->get(route('inventory.requests.index', ['queue' => 'awaiting_liquidation']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
});

test('slip number exhaustion rolls back release stock and quantities', function () {
    DB::table('issuance_slip_sequences')->where('date', '2026-10-09')->update(['last_number' => 999]);
    $this->line->update(['quantity_reserved' => 10]);
    $this->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(6)->and($this->line->fresh()->quantity_released)->toBe(4)->and(IssuanceSlip::query()->count())->toBe(1);
});

test('the liquidation dialog receives ISF materials independently of request filters', function () {
    $this->get(route('inventory.requests.index', ['search' => 'no matching request']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests.data', 0)
            ->has('issuanceAllocations', 1)
            ->where('issuanceAllocations.0.isf_no', '10-09-007')
            ->where('issuanceAllocations.0.department', 'Operations')
            ->where('issuanceAllocations.0.lines.0.quantity_issued', 4));
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('issuanceAllocations', 0));
    $this->slip->update(['status' => 'approved']);
    $this->actingAs($this->manager)->get(route('inventory.requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('issuanceAllocations', 1));
});
