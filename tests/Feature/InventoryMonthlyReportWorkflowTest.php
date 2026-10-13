<?php

use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryMonthlyReport;
use App\Models\InventoryMonthlyReportSnapshot;
use App\Models\InventoryReturn;
use App\Models\ProcurementDelivery;
use App\Models\ProcurementRequest;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use App\Services\InventoryMonthlyReportBuilder;
use App\Services\InventoryStockService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->preparer = User::factory()->inventoryManager()->create();
    $this->checker = User::factory()->inventoryManager()->create();
    $this->noter = User::factory()->inventoryManager()->create();
    $this->approver = User::factory()->inventoryManager()->create();
    $this->item = InventoryItem::factory()->create(['quantity' => 0, 'name' => 'Monthly stock item', 'unit_of_measure' => 'pc', 'created_at' => '2026-08-01']);
    $stock = app(InventoryStockService::class);
    $stock->stockIn($this->item, ['quantity' => 10, 'unit_cost' => 20, 'received_at' => '2026-08-20', 'reference_no' => 'RR-AUG']);
    $stock->stockIn($this->item, ['quantity' => 5, 'unit_cost' => 30, 'received_at' => '2026-09-05', 'reference_no' => 'RR-SEP']);
    $this->issue = $stock->stockOut($this->item, ['quantity' => 12, 'ris_no' => 'RIS-SEP', 'stocked_out_at' => '2026-09-10', 'recipient_name' => 'Field employee', 'department_name' => 'Operations']);
    $stock->stockIn($this->item, ['quantity' => 7, 'unit_cost' => 40, 'received_at' => '2026-10-01', 'reference_no' => 'RR-OCT']);
    $stock->stockOut($this->item, ['quantity' => 1, 'ris_no' => 'RIS-OCT', 'stocked_out_at' => '2026-10-03', 'recipient_name' => 'Field employee']);
    $this->zero = InventoryItem::factory()->create(['quantity' => 0, 'created_at' => '2026-09-01', 'name' => 'Zero balance item']);
});
function monthlyIntake(array $overrides = []): array
{
    return ['period_month' => '2026-09', 'fund_cluster' => '01', 'include_zero' => true, 'checked_by_user_id' => test()->checker->id, 'noted_by_user_id' => test()->noter->id, 'approved_by_user_id' => test()->approver->id,
        'reconciliation_reference' => 'RECON-SEP: source approvals and stock movement balances checked.', 'attested' => true, 'idempotency_key' => (string) Str::uuid(), ...$overrides];
}
function prepareMonthlyPackage(array $overrides = []): InventoryMonthlyReport
{
    test()->actingAs(test()->preparer)->post(route('inventory.monthly_reports.store'), monthlyIntake($overrides))->assertRedirect()->assertSessionHasNoErrors();

    return InventoryMonthlyReport::query()->latest('id')->with('snapshot')->firstOrFail();
}
function monthlyDecision(InventoryMonthlyReport $packet, string $action, User $actor, array $overrides = []): TestResponse
{
    return test()->actingAs($actor)->patch(route('inventory.monthly_reports.transition', $packet), ['action' => $action, 'snapshot_id' => $packet->fresh()->active_snapshot_id, 'remarks' => 'Signed reports and supporting reconciliation checked.', 'signature_reference' => 'SIGNED-'.$action, 'attested' => true, ...$overrides]);
}
function approveMonthlyPackage(InventoryMonthlyReport $packet): void
{
    foreach (['check' => test()->checker, 'note' => test()->noter, 'approve' => test()->approver] as $action => $actor) {
        monthlyDecision($packet, $action, $actor)->assertRedirect()->assertSessionHasNoErrors();
    }
}
test('monthly report routes restrict preparation preview decisions and printing to inventory managers', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->get(route('inventory.monthly_reports.index'))->assertForbidden();
    $this->get(route('inventory.monthly_reports.create'))->assertForbidden();
    $this->post(route('inventory.monthly_reports.store'), monthlyIntake())->assertForbidden();
    $packet = prepareMonthlyPackage();
    $this->get(route('inventory.monthly_reports.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/MonthlyReports/Index')->has('documents.data', 1));
    $this->get(route('inventory.monthly_reports.show', $packet))->assertInertia(fn (Assert $page) => $page->component('Inventory/MonthlyReports/Show')->where('isStale', false)->where('allowedActions', ['refresh', 'cancel']));
    $this->actingAs($employee)->get(route('inventory.monthly_reports.print', [$packet, 'packet']))->assertForbidden();
    monthlyDecision($packet, 'check', $employee)->assertForbidden();
});
test('preparation snapshots every WI monthly register and reconstructs historical FIFO balances without changing stock', function () {
    $packet = prepareMonthlyPackage();
    $stockRows = $packet->snapshot->payload['reports']['stock-master']['rows'];
    $row = collect($stockRows)->first(fn ($row) => $row[0] === $this->item->stock_number);
    expect($packet->snapshot->payload['reports'])->toHaveCount(12)->and($row[4])->toBe(10)->and($row[5])->toBe(5)->and($row[7])->toBe(12)->and($row[9])->toBe(3)->and($row[10])->toBe('90.00')
        ->and($packet->snapshot->payload['reports']['issue-journal']['rows'])->toHaveCount(2)
        ->and($packet->snapshot->payload['reports']['issue-journal']['rows'][0][7])->toBe('200.00')
        ->and($this->item->fresh()->quantity)->toBe(9)->and($this->issue->fresh()->quantity)->toBe(12);
    expect(collect($packet->snapshot->payload['reconciliation'])->pluck('difference')->unique()->all())->toBe([0]);
});
test('stock master supports one classification and zero balance choices independently of monthly registers', function () {
    $all = app(InventoryMonthlyReportBuilder::class)->build('2026-09', null, true, '01');
    $nonzero = app(InventoryMonthlyReportBuilder::class)->build('2026-09', null, false, '01');
    $category = app(InventoryMonthlyReportBuilder::class)->build('2026-09', $this->item->series_category_id, true, '01');
    expect($all['payload']['reports']['stock-master']['rows'])->toHaveCount(2)->and($nonzero['payload']['reports']['stock-master']['rows'])->toHaveCount(1)->and($category['payload']['reports']['stock-master']['rows'])->toHaveCount(1)
        ->and($category['payload']['reports']['issue-journal']['rows'])->toHaveCount(2);
});
test('assigned signatories must be distinct active officers and cannot skip the review sequence', function () {
    $this->actingAs($this->preparer)->post(route('inventory.monthly_reports.store'), monthlyIntake(['checked_by_user_id' => $this->preparer->id]))->assertSessionHasErrors('checked_by_user_id');
    $packet = prepareMonthlyPackage();
    monthlyDecision($packet, 'approve', $this->approver)->assertSessionHasErrors('action');
    monthlyDecision($packet, 'check', $this->preparer)->assertSessionHasErrors('action');
    monthlyDecision($packet, 'check', $this->checker, ['signature_reference' => null])->assertSessionHasErrors('signature_reference');
    approveMonthlyPackage($packet);
    expect($packet->fresh()->status)->toBe('approved')->and($packet->fresh()->approved_at)->not->toBeNull()->and($packet->actions()->count())->toBe(4);
    monthlyDecision($packet, 'approve', $this->approver)->assertSessionHasErrors('action');
});
test('incomplete current or future months and malformed selections cannot be prepared', function (string $month) {
    $this->actingAs($this->preparer)->post(route('inventory.monthly_reports.store'), monthlyIntake(['period_month' => $month]))->assertSessionHasErrors('period_month');
    expect(InventoryMonthlyReport::query()->count())->toBe(0);
})->with(['2026-10', '2026-11', '2026-13']);
test('outstanding delivery return acknowledgement and stock reconciliation issues block monthly preparation', function (string $kind) {
    if ($kind === 'delivery') {
        ProcurementDelivery::factory()->create(['received_at' => '2026-09-20 08:00:00']);
    } elseif ($kind === 'return') {
        InventoryReturn::factory()->create(['return_date' => '2026-09-20']);
    } elseif ($kind === 'acknowledgement') {
        PropertyAccountabilityDocument::factory()->create(['issued_at' => '2026-09-20 08:00:00']);
    } else {
        $this->item->update(['quantity' => 99]);
    }
    $built = app(InventoryMonthlyReportBuilder::class)->build('2026-09', null, true, '01');
    expect($built['readiness']['blockers'])->not->toBeEmpty();
    $this->actingAs($this->preparer)->post(route('inventory.monthly_reports.store'), monthlyIntake())->assertSessionHasErrors('reconciliation');
    expect(InventoryMonthlyReport::query()->count())->toBe(0);
})->with(['delivery', 'return', 'acknowledgement', 'mismatch']);
test('source changes before approval require a new snapshot and repeat sign-off while preserving earlier evidence', function () {
    $packet = prepareMonthlyPackage();
    monthlyDecision($packet, 'check', $this->checker)->assertSessionHasNoErrors();
    $oldId = $packet->active_snapshot_id;
    $oldPayload = $packet->snapshot->payload;
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 2, 'unit_cost' => 35, 'received_at' => '2026-09-28', 'reference_no' => 'LATE-SEP']);
    monthlyDecision($packet, 'note', $this->noter)->assertSessionHasErrors('snapshot_id');
    monthlyDecision($packet, 'refresh', $this->preparer, ['remarks' => 'RECON-SEP-REVISED: late receipt reconciled.'])->assertSessionHasNoErrors();
    expect($packet->fresh()->status)->toBe('prepared')->and($packet->fresh()->active_snapshot_id)->not->toBe($oldId)->and($packet->snapshots()->count())->toBe(2)
        ->and(InventoryMonthlyReportSnapshot::query()->findOrFail($oldId)->payload)->toBe($oldPayload);
    monthlyDecision($packet, 'check', $this->checker, ['snapshot_id' => $oldId])->assertSessionHasErrors('snapshot_id');
    $packet = $packet->fresh()->load('snapshot');
    approveMonthlyPackage($packet);
    $this->get(route('inventory.monthly_reports.print', [$packet, 'packet']))->assertOk()->assertDontSee('WORKING DOCUMENT')->assertSee('RECON-SEP-REVISED')->assertDontSee('Pending');
});
test('later month receipts and issues do not invalidate historical reports awaiting approval', function () {
    $packet = prepareMonthlyPackage();
    $hash = $packet->snapshot->source_hash;
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 2, 'unit_cost' => 50, 'received_at' => '2026-10-08', 'reference_no' => 'LATER-OCT']);
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 1, 'ris_no' => 'LATER-OCT-IS', 'stocked_out_at' => '2026-10-09', 'recipient_name' => 'Field employee']);
    $fresh = app(InventoryMonthlyReportBuilder::class)->build('2026-09', null, true, '01');
    expect($fresh['hash'])->toBe($hash);
    approveMonthlyPackage($packet);
});
test('approved packages preserve report content and signatory names while explained correction revisions link to the original', function () {
    $packet = prepareMonthlyPackage();
    approveMonthlyPackage($packet);
    $payload = $packet->snapshot->payload;
    $name = $packet->signatories['prepared']['name'];
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 1, 'unit_cost' => 60, 'received_at' => '2026-09-29', 'reference_no' => 'CORRECTED-SEP']);
    $this->preparer->update(['name' => 'Changed preparer name']);
    monthlyDecision($packet, 'refresh', $this->preparer)->assertSessionHasErrors('action');
    $this->actingAs($this->preparer)->post(route('inventory.monthly_reports.store'), monthlyIntake())->assertSessionHasErrors('correction_reason');
    $revision = prepareMonthlyPackage(['correction_reason' => 'Late source receipt requires corrected monthly statements.']);
    expect($packet->fresh()->load('snapshot')->snapshot->payload)->toBe($payload)->and($packet->fresh()->signatories['prepared']['name'])->toBe($name)
        ->and($revision->revision)->toBe(2)->and($revision->supersedes_id)->toBe($packet->id)->and($revision->snapshot->payload)->not->toBe($payload);
});
test('duplicate submission retries and active monthly packages cannot create competing revisions', function () {
    $data = monthlyIntake();
    $this->actingAs($this->preparer)->post(route('inventory.monthly_reports.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('inventory.monthly_reports.store'), $data)->assertSessionHasNoErrors();
    expect(InventoryMonthlyReport::query()->count())->toBe(1);
    $this->post(route('inventory.monthly_reports.store'), [...$data, 'reconciliation_reference' => 'Changed'])->assertSessionHasErrors('idempotency_key');
    $this->post(route('inventory.monthly_reports.store'), monthlyIntake())->assertSessionHasErrors('period_month');
    $packet = InventoryMonthlyReport::query()->sole();
    monthlyDecision($packet, 'cancel', $this->preparer)->assertSessionHasNoErrors();
    $replacement = prepareMonthlyPackage();
    expect($replacement->revision)->toBe(2);
});
test('approved monthly packet transmittal is recorded once and never posts a journal entry or changes stock', function () {
    $packet = prepareMonthlyPackage();
    $this->get(route('inventory.monthly_reports.print', [$packet, 'packet']))->assertOk()->assertSee('WORKING DOCUMENT')->assertSee('Stock Master List');
    $this->get(route('inventory.monthly_reports.print', [$packet, 'transmittal']))->assertNotFound();
    approveMonthlyPackage($packet);
    $this->get(route('inventory.monthly_reports.print', [$packet, 'transmittal']))->assertOk()->assertSee('DRAFT TRANSMITTAL');
    monthlyDecision($packet, 'transmit', $this->preparer, ['transmittal_reference' => 'TRANSMITTAL-SEP-1', 'transmittal_recipient' => 'ABD Department Manager', 'jev_reference' => 'JEV-ACCT-1'])->assertSessionHasNoErrors();
    monthlyDecision($packet, 'transmit', $this->preparer, ['transmittal_reference' => 'TRANSMITTAL-SEP-1', 'transmittal_recipient' => 'ABD Department Manager'])->assertSessionHasErrors('action');
    $this->get(route('inventory.monthly_reports.print', [$packet, 'transmittal']))->assertOk()->assertDontSee('DRAFT TRANSMITTAL')->assertSee('TRANSMITTAL-SEP-1')->assertSee('JEV-ACCT-1');
    expect($packet->fresh()->status)->toBe('transmitted')->and($this->item->fresh()->quantity)->toBe(9);
});
test('monthly snapshots and sign-off evidence reject rewriting and deletion', function () {
    $packet = prepareMonthlyPackage();
    expect(fn () => $packet->snapshot->update(['source_hash' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $packet->snapshot->delete())->toThrow(LogicException::class);
    $entry = $packet->actions()->first();
    expect(fn () => $entry->update(['remarks' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});
test('PO JO PAR and ICS registers use their actual order endorsement and acknowledgement dates', function () {
    $this->travelTo(now()->setDate(2026, 9, 15));
    $po = ProcurementRequest::factory()->create(['purchase_order_no' => 'PO-SEP-1', 'status' => 'accepted']);
    $po->lines()->create(['item_name' => 'Ordered item', 'unit_of_measure' => 'pc', 'quantity' => 2, 'estimated_unit_cost' => 25]);
    $po->actions()->create(['actor_user_id' => $this->preparer->id, 'action' => 'order', 'to_status' => 'ordered', 'attestation' => true, 'remarks' => 'Order recorded', 'metadata' => ['purchase_order_no' => 'PO-SEP-1']]);
    $jo = ProcurementRequest::factory()->create(['document_kind' => 'JO', 'status' => 'endorsed']);
    $jo->lines()->create(['item_name' => 'Service', 'unit_of_measure' => 'job', 'quantity' => 1, 'estimated_unit_cost' => 100]);
    $jo->actions()->create(['actor_user_id' => $this->preparer->id, 'action' => 'forward', 'to_status' => 'endorsed', 'attestation' => true, 'remarks' => 'Job order endorsed', 'metadata' => ['endorsement_recipient' => 'Procurement Unit']]);
    PropertyAccountabilityDocument::factory()->create(['status' => 'active', 'document_type' => 'PAR', 'issued_at' => '2026-09-14', 'acknowledged_at' => '2026-09-15', 'acquisition_cost' => 50000]);
    PropertyAccountabilityDocument::factory()->create(['status' => 'active', 'document_type' => 'ICS', 'issued_at' => '2026-09-16', 'acknowledged_at' => '2026-09-17', 'acquisition_cost' => 12000]);
    $this->travelTo(now()->setDate(2026, 10, 12));
    $packet = prepareMonthlyPackage();
    foreach (['purchase-orders', 'job-orders', 'equipment', 'ics'] as $key) {
        expect($packet->snapshot->payload['reports'][$key]['rows'])->toHaveCount(1);
    }
    expect($packet->snapshot->payload['reports']['purchase-orders']['rows'][0][5])->toBe('50.00');
});
test('approved FIFO shortages appear as signed adjustments in both stock master reconciliation and IAM register', function () {
    $this->travelTo(now()->setDate(2026, 9, 20));
    $batch = $this->item->batches()->where('reference_no', 'RR-SEP')->firstOrFail();
    $session = InventoryCountSession::factory()->create(['status' => 'approved', 'completed_at' => '2026-09-20 00:00:00']);
    $line = InventoryCountLine::factory()->create(['inventory_count_session_id' => $session->id, 'inventory_item_id' => $this->item->getKey(), 'stock_number' => $this->item->stock_number, 'item_name' => $this->item->name]);
    $line->adjustments()->create(['inventory_item_batch_id' => $batch->getKey(), 'quantity' => -1, 'unit_cost' => 30, 'created_at' => '2026-09-20 00:00:00']);
    $batch->decrement('quantity_remaining', 1);
    $this->item->decrement('quantity', 1);
    $this->travelTo(now()->setDate(2026, 10, 12));
    $packet = prepareMonthlyPackage();
    $stock = collect($packet->snapshot->payload['reports']['stock-master']['rows'])->first(fn ($row) => $row[0] === $this->item->stock_number);
    expect($stock[8])->toBe(-1)->and($stock[9])->toBe(2)->and($stock[10])->toBe('60.00')->and($packet->snapshot->payload['reports']['adjustments']['rows'][0][7])->toBe('-30.00');
});
test('every monthly report has a valid individual print view and invalid document keys are rejected', function () {
    $packet = prepareMonthlyPackage();
    foreach (array_keys(InventoryMonthlyReportBuilder::catalog()) as $key) {
        $this->get(route('inventory.monthly_reports.print', [$packet, $key]))->assertOk()->assertSee($packet->document_no);
    }
    $this->get('/inventory/monthly-reports/'.$packet->id.'/print/not-a-report')->assertNotFound();
});

test('archived stock keeps its historical descriptions and exact receipt amounts in monthly registers', function () {
    $decimalItem = InventoryItem::factory()->create(['quantity' => 0, 'name' => 'Decimal cost item', 'created_at' => '2026-09-01']);
    app(InventoryStockService::class)->stockIn($decimalItem, ['quantity' => 3, 'unit_cost' => '0.10', 'received_at' => '2026-09-12', 'reference_no' => 'RR-DECIMAL']);
    $this->item->delete();
    $packet = prepareMonthlyPackage();
    foreach (['iar', 'receipts'] as $key) {
        $rows = collect($packet->snapshot->payload['reports'][$key]['rows']);
        expect($rows->first(fn ($row) => $row[1] === 'RR-SEP')[4])->toBe('Monthly stock item')
            ->and($rows->first(fn ($row) => $row[1] === 'RR-DECIMAL')[8])->toBe('0.30');
    }
});
