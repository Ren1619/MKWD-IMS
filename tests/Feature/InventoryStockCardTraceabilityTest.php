<?php

use App\Models\InventoryItem;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\InventoryMonthlyReportBuilder;
use App\Services\InventoryReportService;
use App\Services\InventoryStockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->manager = User::factory()->inventoryManager()->create();
    $this->item = InventoryItem::factory()->create(['quantity' => 0, 'name' => 'Stock-card material', 'unit_of_measure' => 'pc']);
    app(InventoryStockService::class)->stockIn($this->item, ['quantity' => 10, 'unit_cost' => '0.10', 'received_at' => '2026-10-01', 'reference_no' => 'RR-ORIGINAL', 'source' => 'Original Supplier', 'batch_notes' => 'Conforming delivery']);
});
function traceabilityCard(string $document = 'stock-card', ?string $from = null): array
{
    return app(InventoryReportService::class)->printable($document, ['as_of' => '2026-10-12', 'period' => 'custom', 'period_label' => 'October 2026', 'year' => 2026, 'from' => $from, 'to' => '2026-10-12', 'fund_cluster' => '01', 'custodian' => null]);
}
function stockCardRequest(int $reserved = 3): SupplyRequest
{
    $request = SupplyRequest::factory()->create(['status' => 'ready_for_release', 'isf_no' => '10-12-007', 'requester_name' => 'Frozen Recipient', 'purpose' => 'Maintain project pumps']);
    SupplyRequestLine::factory()->create(['supply_request_id' => $request->id, 'inventory_item_id' => test()->item->getKey(), 'is_new_item' => false, 'item_name' => test()->item->name, 'unit_of_measure' => 'pc', 'quantity_requested' => 10, 'quantity_approved' => 10, 'quantity_reserved' => $reserved]);

    return $request;
}
function stockCardRelease(SupplyRequest $request): void
{
    test()->actingAs(test()->manager)->patch(route('inventory.requests.transition', $request), ['action' => 'release', 'attested' => true])->assertRedirect()->assertSessionHasNoErrors();
}
test('stock cards include supplier recipient purpose and exact amounts with actual issuance-slip references', function (string $document) {
    $request = stockCardRequest();
    stockCardRelease($request);
    $slip = $request->issuanceSlips()->sole();
    $issue = $this->item->stockOuts()->sole();
    expect($issue->issuance_slip_id)->toBe($slip->id)->and($issue->issuanceSlip->slip_no)->toBe($slip->slip_no);
    $table = traceabilityCard($document);
    $rows = collect($table['rows']);
    $receipt = $rows->first(fn ($row) => $row[4] === 'Receipt');
    $issued = $rows->first(fn ($row) => $row[4] === 'Issue');
    expect($table['columns'])->toHaveCount(14)->and($receipt)->toHaveCount(14)
        ->and($receipt[8])->toBe('1.00')->and($receipt[10])->toBe('Original Supplier')->and($receipt[11])->toBe('Conforming delivery')
        ->and($issued[3])->toBe($slip->slip_no)->and($issued[8])->toBe('0.30')->and($issued[9])->toBe(7)
        ->and($issued[10])->toBe('Frozen Recipient')->and($issued[11])->toBe('Maintain project pumps')->and($issued[12])->toBe('pc')->and($issued[13])->toBe($request->ris_no);
    $this->get(route('inventory.reports.print', $document))->assertOk()->assertSee('Supplier / source / recipient')->assertSee('Frozen Recipient')->assertSee($slip->slip_no);
})->with(['stock-card', 'supplies-ledger-card']);
test('each partial release links only its own stock movements even on the same day', function () {
    $request = stockCardRequest(3);
    stockCardRelease($request);
    $firstSlip = $request->issuanceSlips()->sole();
    $request->lines()->sole()->update(['quantity_reserved' => 7]);
    stockCardRelease($request);
    $slips = $request->issuanceSlips()->orderBy('id')->get();
    $issues = $this->item->stockOuts()->orderBy('inventory_item_stock_out_id')->get();
    expect($slips)->toHaveCount(2)->and($issues)->toHaveCount(2)
        ->and($issues[0]->issuance_slip_id)->toBe($firstSlip->id)->and($issues[0]->quantity)->toBe(3)
        ->and($issues[1]->issuance_slip_id)->toBe($slips[1]->id)->and($issues[1]->quantity)->toBe(4);
    $rows = collect(traceabilityCard()['rows'])->where(4, 'Issue')->values();
    expect($rows->pluck(3)->all())->toBe($slips->pluck('slip_no')->all())->and($rows->pluck(9)->all())->toBe([7, 3]);
    $built = app(InventoryMonthlyReportBuilder::class)->build('2026-10', null, true, '01');
    expect(collect($built['payload']['reports']['issues']['rows'])->pluck(1)->all())->toBe($slips->pluck('slip_no')->all());
});
test('legacy issues retain their original references and archived stock remains traceable', function () {
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'ris_no' => 'LEGACY-RIS', 'recipient_name' => 'Legacy Recipient', 'notes' => 'Legacy purpose']);
    $this->item->delete();
    $rows = collect(traceabilityCard()['rows']);
    $issue = $rows->first(fn ($row) => $row[4] === 'Issue');
    expect($issue[3])->toBe('LEGACY-RIS')->and($issue[10])->toBe('Legacy Recipient')->and($issue[11])->toBe('Legacy purpose')->and($issue[9])->toBe(8)
        ->and(IssuanceSlip::query()->count())->toBe(0);
});
test('period-filtered cards retain opening movements in the running balance without printing out-of-period rows', function () {
    app(InventoryStockService::class)->stockOut($this->item, ['quantity' => 2, 'stocked_out_at' => '2026-10-12', 'ris_no' => 'IS-LEGACY']);
    $rows = collect(traceabilityCard(from: '2026-10-10')['rows']);
    expect($rows)->toHaveCount(1)->and($rows->sole()[9])->toBe(8)->and($rows->sole()[8])->toBe('0.20');
});
test('slip-number failure rolls back stock movements and provenance links together', function () {
    $request = stockCardRequest();
    DB::table('issuance_slip_sequences')->updateOrInsert(['date' => '2026-10-12'], ['last_number' => 999]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $request), ['action' => 'release', 'attested' => true])->assertSessionHasErrors('action');
    expect($this->item->stockOuts()->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(10)->and(IssuanceSlip::query()->count())->toBe(0)->and($request->lines()->sole()->quantity_released)->toBe(0);
});
