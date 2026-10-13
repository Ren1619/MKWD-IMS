<?php

use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryStockControl;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\InventoryStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->planner = User::factory()->inventoryManager()->create();
    $this->verifier = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->item = InventoryItem::factory()->create(['quantity' => 0, 'name' => 'Counted consumable']);
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 10, 'unit_cost' => 20, 'received_at' => '2026-10-01', 'reference_no' => 'RR-1']);
    $this->session = app(InventoryCountService::class)->create([
        'title' => 'Semiannual physical inventory', 'scheduled_date' => '2026-10-12',
        'memorandum_reference' => 'MEMO-2026-1', 'team_details' => 'Counter, Accounting checker, Internal Control validator, COA witness.',
    ], $this->planner);
});

function startConsumableCount(): void
{
    test()->actingAs(test()->planner)->patch(route('inventory.counts.transition', test()->session), ['action' => 'start', 'attested' => true])->assertSessionHasNoErrors();
}

function saveConsumableObservation(int $quantity, array $overrides = []): void
{
    $line = test()->session->lines()->firstOrFail();
    test()->actingAs(test()->planner)->patch(route('inventory.counts.record', [test()->session, $line]), [
        'quantity' => $quantity, 'latest_entry_id' => $line->latestEntry?->id, 'condition' => 'good',
        'location' => 'Main stockroom', 'aisle' => 'A', 'bin' => '1',
        'counter_name' => 'Warehouse counter', 'checker_name' => 'Accounting checker', 'validator_name' => 'Internal Control validator',
        'remarks' => 'Joint count verified against signed tags.', 'attested' => true, ...$overrides,
    ])->assertSessionHasNoErrors();
}

function approveConsumableCount(): void
{
    foreach (['submit' => test()->planner, 'verify' => test()->verifier, 'approve' => test()->approver] as $action => $actor) {
        test()->actingAs($actor)->patch(route('inventory.counts.transition', test()->session), ['action' => $action, 'remarks' => 'Approved under IAM-2026-1', 'attested' => true])->assertSessionHasNoErrors();
    }
}

test('count routes authorize managers and expose a paginated usable workspace', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->get(route('inventory.counts.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('inventory.counts.store'), [])->assertForbidden();
    $this->actingAs($employee)->patch(route('inventory.counts.transition', $this->session), ['action' => 'start', 'attested' => true])->assertForbidden();
    $this->actingAs($employee)->get(route('inventory.counts.print', [$this->session, 'worksheet']))->assertForbidden();
    $this->actingAs($this->planner)->get(route('inventory.counts.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Counts/Index')->has('sessions.data', 1));
    startConsumableCount();
    $this->get(route('inventory.counts.show', $this->session))->assertInertia(fn (Assert $page) => $page->component('Inventory/Counts/Show')->has('lines.data', 1)->where('lines.data.0.book_quantity', 10)->where('lines.data.0.latest_entry', null));
});

test('count creation validates the memorandum and does not prematurely freeze stock', function () {
    $this->actingAs($this->planner)->post(route('inventory.counts.store'), ['title' => 'Incomplete', 'attested' => true])->assertSessionHasErrors(['scheduled_date', 'memorandum_reference', 'team_details']);
    $this->post(route('inventory.counts.store'), ['title' => 'New session', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'MEMO-2', 'team_details' => 'Inventory team', 'attested' => true])->assertSessionHasNoErrors();
    expect(InventoryCountSession::query()->count())->toBe(2)->and(InventoryStockControl::query()->find(1)->active_count_session_id)->toBeNull();
});

test('cutoff freezes normal receipts issues and catalog changes and permits only one active count', function () {
    startConsumableCount();
    $this->actingAs($this->planner)->patch(route('inventory.items.stock_in', $this->item), ['quantity' => 1, 'unit_cost' => 10])->assertSessionHasErrors('quantity');
    $this->patch(route('inventory.items.stock_out', $this->item), ['quantity' => 1, 'recipient_name' => 'Recipient'])->assertSessionHasErrors('quantity');
    $this->patch(route('inventory.items.update', $this->item), ['series_category_id' => $this->item->series_category_id, 'name' => 'Changed', 'stock_number' => $this->item->stock_number, 'unit_of_measure' => 'pc', 'reorder_point' => 1, 'status' => 'active'])->assertSessionHasErrors('quantity');
    $other = app(InventoryCountService::class)->create(['title' => 'Other', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'MEMO-OTHER', 'team_details' => 'Team'], $this->planner);
    $this->patch(route('inventory.counts.transition', $other), ['action' => 'start', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(10)->and($this->item->fresh()->name)->toBe('Counted consumable');
});

test('a zero count must be explicit and a variance requires an agreeing recount', function () {
    startConsumableCount();
    $this->actingAs($this->planner)->patch(route('inventory.counts.transition', $this->session), ['action' => 'submit', 'attested' => true])->assertSessionHasErrors('action');
    saveConsumableObservation(0);
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'submit', 'attested' => true])->assertSessionHasErrors('action');
    saveConsumableObservation(1);
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'submit', 'attested' => true])->assertSessionHasErrors('action');
    saveConsumableObservation(0);
    saveConsumableObservation(0);
    approveConsumableCount();
    expect($this->item->fresh()->quantity)->toBe(0)->and($this->session->fresh()->status)->toBe('approved')
        ->and($this->session->lines()->first()->entries()->count())->toBe(4);
});

test('independent verification and approval are required and posting cannot repeat', function () {
    startConsumableCount();
    saveConsumableObservation(10);
    $this->actingAs($this->planner)->patch(route('inventory.counts.transition', $this->session), ['action' => 'approve', 'remarks' => 'Attempted skip', 'attested' => true])->assertSessionHasErrors('action');
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'submit', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'verify', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->verifier)->patch(route('inventory.counts.transition', $this->session), ['action' => 'verify', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'approve', 'remarks' => 'IAM-1', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($this->approver)->patch(route('inventory.counts.transition', $this->session), ['action' => 'approve', 'remarks' => 'IAM-1', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch(route('inventory.counts.transition', $this->session), ['action' => 'approve', 'remarks' => 'IAM-1', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->session->lines()->first()->adjustments()->count())->toBe(0);
});

test('shortages retain FIFO batch costs and do not become material issuances', function () {
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 5, 'unit_cost' => 30, 'received_at' => '2026-10-02']);
    startConsumableCount();
    saveConsumableObservation(12);
    saveConsumableObservation(12);
    approveConsumableCount();
    $line = $this->session->lines()->first();
    expect($this->item->fresh()->quantity)->toBe(12)->and($this->item->fresh()->inventory_value)->toBe('290.00')
        ->and($line->adjustments()->sole()->quantity)->toBe(-3)->and($line->adjustments()->sole()->unit_cost)->toBe('20.00')
        ->and($this->item->stockOuts()->count())->toBe(0);
    $this->actingAs($this->planner)->get(route('inventory.reports.print', ['stock-card', 'period' => 'all', 'as_of' => '2026-10-12']))->assertOk()->assertSee('Adjustment (shortage)')->assertSee('Running balance');
});

test('overages require a valuation basis and are not counted as supplier receipts', function () {
    startConsumableCount();
    saveConsumableObservation(12);
    saveConsumableObservation(12);
    $this->actingAs($this->planner)->patch(route('inventory.counts.transition', $this->session), ['action' => 'submit', 'attested' => true])->assertSessionHasErrors('action');
    saveConsumableObservation(12, ['adjustment_unit_cost' => 25, 'valuation_basis' => 'Accounting approval VAL-1']);
    approveConsumableCount();
    expect($this->item->fresh()->inventory_value)->toBe('250.00')->and($this->session->lines()->first()->adjustments()->sole()->quantity)->toBe(2);
    $this->get(route('inventory.reports.print', ['receipt-register', 'period' => 'all']))->assertOk()->assertDontSee('Physical count adjustment');
});

test('cutoff movements distinguish before and after issues and segregated receipts', function () {
    startConsumableCount();
    $line = $this->session->lines()->first();
    $url = route('inventory.counts.transition', $this->session);
    $base = ['line_id' => $line->id, 'reference_no' => 'RIS-EMERGENCY', 'approval_reference' => 'AUTH-1', 'remarks' => 'Authorized urgent repair', 'attested' => true];
    $this->actingAs($this->planner)->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'emergency_issue', 'quantity' => 2, 'recipient_name' => 'Emergency recipient', 'timing' => 'after_count'])->assertSessionHasErrors('timing');
    $this->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'emergency_issue', 'quantity' => 2, 'recipient_name' => 'Emergency recipient', 'timing' => 'before_count'])->assertSessionHasNoErrors();
    saveConsumableObservation(8);
    $this->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'emergency_issue', 'quantity' => 1, 'recipient_name' => 'Emergency recipient', 'timing' => 'after_count'])->assertSessionHasNoErrors();
    $this->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'segregated_receipt', 'quantity' => 3, 'unit_cost' => 40])->assertSessionHasNoErrors();
    approveConsumableCount();
    expect($this->item->fresh()->quantity)->toBe(10)->and($line->fresh()->before_count_issues)->toBe(2)
        ->and($line->fresh()->after_count_issues)->toBe(1)->and($line->fresh()->segregated_receipts)->toBe(3)
        ->and($line->adjustments()->count())->toBe(0);
});

test('emergency issues cannot consume physically segregated receipts', function () {
    startConsumableCount();
    $line = $this->session->lines()->first();
    $url = route('inventory.counts.transition', $this->session);
    $base = ['line_id' => $line->id, 'reference_no' => 'RR-NEW', 'approval_reference' => 'AUTH-1', 'remarks' => 'Segregated delivery', 'attested' => true];
    $this->actingAs($this->planner)->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'segregated_receipt', 'quantity' => 3, 'unit_cost' => 40])->assertSessionHasNoErrors();
    $this->patch($url, [...$base, 'idempotency_key' => (string) Str::uuid(), 'action' => 'emergency_issue', 'quantity' => 11, 'recipient_name' => 'Emergency recipient', 'timing' => 'before_count'])->assertSessionHasErrors('quantity');
    expect($this->item->fresh()->quantity)->toBe(13);
});

test('a recount after an emergency issue preserves the actual observation and reconciles its timing', function () {
    startConsumableCount();
    saveConsumableObservation(8);
    $line = $this->session->lines()->first();
    $this->actingAs($this->planner)->patch(route('inventory.counts.transition', $this->session), [
        'action' => 'emergency_issue', 'line_id' => $line->id, 'quantity' => 1, 'recipient_name' => 'Urgent repair team',
        'timing' => 'after_count', 'reference_no' => 'RIS-URGENT', 'approval_reference' => 'AUTH-URGENT',
        'remarks' => 'Authorized urgent withdrawal', 'attested' => true, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    saveConsumableObservation(7);
    approveConsumableCount();
    $latest = $line->latestEntry()->first();
    expect($latest->observed_quantity)->toBe(7)->and($latest->quantity)->toBe(8)->and($latest->after_count_issues_at_observation)->toBe(1)
        ->and($this->item->fresh()->quantity)->toBe(7)->and($line->adjustments()->sole()->quantity)->toBe(-2);
});

test('replayed emergency submissions post once and a movement key cannot be reused with different quantities', function () {
    startConsumableCount();
    $line = $this->session->lines()->first();
    $payload = ['action' => 'emergency_issue', 'line_id' => $line->id, 'quantity' => 2, 'recipient_name' => 'Urgent repair team',
        'timing' => 'before_count', 'reference_no' => 'RIS-URGENT', 'approval_reference' => 'AUTH-URGENT',
        'remarks' => 'Urgent authorized withdrawal', 'attested' => true, 'idempotency_key' => (string) Str::uuid()];
    $url = route('inventory.counts.transition', $this->session);
    $this->actingAs($this->planner)->patch($url, $payload)->assertSessionHasNoErrors();
    $this->patch($url, $payload)->assertSessionHasNoErrors();
    $this->patch($url, [...$payload, 'quantity' => 3])->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(8)->and($line->fresh()->before_count_issues)->toBe(2)
        ->and($this->item->stockOuts()->count())->toBe(1);
});

test('recounts retain immutable history reject stale edits and scope lines to their session', function () {
    startConsumableCount();
    saveConsumableObservation(9);
    $line = $this->session->lines()->first();
    $this->actingAs($this->planner)->patch(route('inventory.counts.record', [$this->session, $line]), [
        'quantity' => 10, 'latest_entry_id' => null, 'condition' => 'good', 'location' => 'Main',
        'counter_name' => 'Counter', 'checker_name' => 'Checker', 'validator_name' => 'Validator', 'remarks' => 'Stale change', 'attested' => true,
    ])->assertSessionHasErrors('quantity');
    $other = app(InventoryCountService::class)->create(['title' => 'Other', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'M-2', 'team_details' => 'Team'], $this->planner);
    $this->patch(route('inventory.counts.record', [$other, $line]), [
        'quantity' => 10, 'condition' => 'good', 'location' => 'Main', 'counter_name' => 'Counter', 'checker_name' => 'Checker', 'validator_name' => 'Validator', 'attested' => true,
    ])->assertNotFound();
    expect(fn () => $line->entries()->sole()->update(['quantity' => 10]))->toThrow(LogicException::class);
    expect($line->entries()->count())->toBe(1);
});

test('cancellation retains evidence and authorized movements while reopening normal stock', function () {
    startConsumableCount();
    saveConsumableObservation(9);
    $this->actingAs($this->planner)->patch(route('inventory.counts.transition', $this->session), ['action' => 'cancel', 'remarks' => 'Rescheduled under memorandum M-2', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch(route('inventory.items.stock_in', $this->item), ['quantity' => 1, 'unit_cost' => 20])->assertSessionHasNoErrors();
    expect($this->item->fresh()->quantity)->toBe(11)->and($this->session->lines()->first()->entries()->count())->toBe(1)->and(InventoryStockControl::query()->find(1)->active_count_session_id)->toBeNull();
});

test('RPCI uses approved frozen observations and never invents physical quantities', function () {
    $url = route('inventory.reports.print', ['rpci', 'as_of' => '2026-10-12']);
    $this->actingAs($this->planner)->get($url)->assertOk()->assertSee('No approved physical count available')->assertDontSee('Counted consumable');
    startConsumableCount();
    saveConsumableObservation(8);
    saveConsumableObservation(8);
    approveConsumableCount();
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 100, 'unit_cost' => 50]);
    $this->get($url)->assertOk()->assertSee($this->session->document_no)->assertSee('Counted consumable')->assertDontSee('108');
    $this->get(route('inventory.reports.print', ['rpci', 'as_of' => '2026-10-11']))->assertSee('No approved physical count available');
    $this->get(route('inventory.reports.print', ['variance-reconciliation', 'as_of' => '2026-10-12']))->assertOk()->assertSee('Consumable')->assertSee('Counted consumable');
});

test('count calendar dates use Manila even before midnight UTC', function () {
    $this->travelTo(Carbon::parse('2026-10-12 00:30:00', 'Asia/Manila')->timezone(config('app.timezone')));
    startConsumableCount();
    saveConsumableObservation(10);
    approveConsumableCount();
    $this->actingAs($this->planner)->get(route('inventory.reports.print', ['rpci', 'as_of' => '2026-10-12']))->assertOk()->assertSee($this->session->document_no);
    $this->get(route('inventory.reports.print', ['rpci', 'as_of' => '2026-10-11']))->assertSee('No approved physical count available');
});

test('returning a session for recount retains observations and resets verification', function () {
    startConsumableCount();
    saveConsumableObservation(10);
    $url = route('inventory.counts.transition', $this->session);
    $this->actingAs($this->planner)->patch($url, ['action' => 'submit', 'attested' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->verifier)->patch($url, ['action' => 'return_for_recount', 'remarks' => 'Please confirm the stockroom quantity.', 'attested' => true])->assertSessionHasNoErrors();
    saveConsumableObservation(10);
    approveConsumableCount();
    expect($this->session->lines()->first()->entries()->count())->toBe(2);
});

test('approved signatures remain frozen when an approver is renamed', function () {
    startConsumableCount();
    saveConsumableObservation(10);
    approveConsumableCount();
    $nameAtApproval = $this->approver->name;
    $this->approver->update(['name' => 'Renamed account']);
    $this->actingAs($this->planner)->get(route('inventory.counts.print', [$this->session, 'reconciliation']))->assertOk()->assertSee($nameAtApproval)->assertDontSee('Renamed account');
});

test('approval detects unexpected live stock changes and rolls back without releasing cut-off', function () {
    startConsumableCount();
    saveConsumableObservation(8);
    saveConsumableObservation(8);
    $url = route('inventory.counts.transition', $this->session);
    $this->actingAs($this->planner)->patch($url, ['action' => 'submit', 'attested' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->verifier)->patch($url, ['action' => 'verify', 'attested' => true])->assertSessionHasNoErrors();
    $this->item->update(['quantity' => 9]);
    $this->actingAs($this->approver)->patch($url, ['action' => 'approve', 'remarks' => 'IAM-1', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->session->fresh()->status)->toBe('verified')->and($this->session->lines()->first()->adjustments()->count())->toBe(0)
        ->and(InventoryStockControl::query()->find(1)->active_count_session_id)->toBe($this->session->id);
});

test('count adjustments invalidate existing reservations for explicit review before release', function () {
    $request = SupplyRequest::factory()->create(['status' => 'ready_for_release']);
    $reserved = SupplyRequestLine::query()->create(['supply_request_id' => $request->id, 'inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => 'pc', 'quantity_requested' => 9, 'quantity_approved' => 9, 'quantity_reserved' => 9, 'quantity_released' => 0, 'is_new_item' => false]);
    startConsumableCount();
    saveConsumableObservation(8);
    saveConsumableObservation(8);
    approveConsumableCount();
    expect($reserved->fresh()->quantity_reserved)->toBe(0)->and($request->fresh()->status)->toBe('approved');
});

test('printable tags worksheets reconciliation and adjustments preserve count references', function (string $document) {
    startConsumableCount();
    $this->actingAs($this->planner)->get(route('inventory.counts.print', [$this->session, $document]))->assertOk()
        ->assertSee($this->session->document_no)->assertSee('WORKING DOCUMENT')->assertSee('Print / Save as PDF');
    if ($document === 'tags') {
        $this->get(route('inventory.counts.print', [$this->session, $document]))->assertSee('Account group')->assertSee('Taken by:')->assertSee('Checked by:');
    }
})->with(['tags', 'worksheet', 'reconciliation', 'adjustments']);
