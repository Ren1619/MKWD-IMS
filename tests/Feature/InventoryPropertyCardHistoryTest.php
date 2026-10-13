<?php

use App\AssetAccountingClassification;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\InventoryAssetLifecycleService;
use App\Services\InventoryAssetService;
use App\Services\InventoryPropertyCardService;
use App\Services\InventoryReportService;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(10, 0));
    $this->manager = User::factory()->inventoryManager()->create();
    $this->lifecycle = app(InventoryAssetLifecycleService::class);
    $this->custody = app(InventoryAssetService::class);
});
function historicalProperty(float $cost): InventoryAsset
{
    $asset = InventoryAsset::factory()->create(['name' => 'Original property description', 'location' => 'Property Unit', 'acquisition_cost' => $cost, 'acquisition_date' => '2026-10-01', 'fund_cluster' => '01']);
    test()->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Registered, ['event_date' => '2026-10-01', 'reference_no' => 'IAR-ORIGINAL', 'amount' => $cost, 'details' => ['initial_location' => 'Property Unit']], test()->manager);
    if ($cost < 50000) {
        test()->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Receipt, ['event_date' => '2026-10-01', 'reference_no' => 'RR-ORIGINAL', 'amount' => $cost, 'quantity' => 1], test()->manager);
    }

    return $asset;
}
function propertyHistoryRows(InventoryAsset $asset, string $asOf = '2026-10-12', ?string $from = null, ?int $custodian = null): array
{
    $table = app(InventoryPropertyCardService::class)->build(['as_of' => $asOf, 'from' => $from, 'to' => $asOf, 'fund_cluster' => '01', 'custodian' => $custodian], $asset->accounting_classification);

    return array_map(fn (array $row): array => array_combine($table['columns'], $row), $table['rows']);
}
test('property cards preserve earlier description custodian and location after later transfers and catalog renaming', function (float $cost) {
    $asset = historicalProperty($cost);
    $a = HrisReference::factory()->create(['name' => 'Original Custodian', 'metadata' => ['position' => ['department' => ['name' => 'Operations']]]]);
    $b = HrisReference::factory()->create(['name' => 'Future Custodian']);
    $this->travelTo(now()->setDate(2026, 10, 3));
    $this->custody->assign($asset, $a, $this->manager);
    $first = $asset->accountabilityDocuments()->sole();
    $this->travelTo(now()->setDate(2026, 10, 8));
    $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Transfer, ['event_date' => '2026-10-08', 'reference_no' => 'PTR-LOCATION', 'from_location' => 'Property Unit', 'to_location' => 'Site A'], $this->manager);
    $asset->update(['location' => 'Site A']);
    $this->travelTo(now()->setDate(2026, 10, 14));
    $this->custody->assign($asset, $b, $this->manager);
    $asset->update(['name' => 'Future description', 'location' => 'Future location']);
    $a->update(['name' => 'Renamed HRIS label']);
    $rows = collect(propertyHistoryRows($asset, '2026-10-10'));
    expect($rows->pluck('Description')->unique()->all())->toBe(['Original property description'])
        ->and($rows->last()['Officer / office'])->toContain('Original Custodian')
        ->and($rows->last()['Location'])->toBe('Site A')
        ->and($rows->last()['PAR / ICS reference'])->toBe($first->document_no)
        ->and($rows->pluck('Officer / office')->filter()->implode(' '))->not->toContain('Future Custodian', 'Renamed HRIS label');
    $byCustodian = propertyHistoryRows($asset, '2026-10-10', custodian: $a->id);
    expect($byCustodian)->not->toBeEmpty();
})->with([60000.0, 8000.0]);
test('semi expendable returns keep accounting inventory expensed while recording the returning officer', function () {
    $asset = historicalProperty(8000);
    $a = HrisReference::factory()->create(['name' => 'Returning Custodian']);
    $this->travelTo(now()->setDate(2026, 10, 3));
    $this->custody->assign($asset, $a, $this->manager);
    $doc = $asset->accountabilityDocuments()->sole();
    $this->travelTo(now()->setDate(2026, 10, 5));
    $this->custody->unassign($asset, $this->manager, 'Returned in serviceable condition.');
    $rows = collect(propertyHistoryRows($asset));
    $receipt = $rows->firstWhere('Transaction', 'Received by Property Unit');
    $returned = $rows->firstWhere('Transaction', 'Returned to Property Unit');
    expect($receipt['Accounting inventory qty.'])->toBe(1)->and($returned['Accounting inventory qty.'])->toBe(0)
        ->and($returned['Recorded property qty.'])->toBe(1)->and($returned['Officer / office'])->toContain('Returning Custodian')
        ->and($returned['PAR / ICS reference'])->toBe($doc->document_no)
        ->and($rows->where('Transaction', 'Issued and expensed')->sum(fn (array $row): int => $row['Qty. moved (custody)']))->toBe(1)
        ->and($rows->where('Transaction', 'Custodian assignment')->sum(fn (array $row): int => $row['Qty. moved (custody)']))->toBe(0);
});
test('disposal and derecognition remain on archived property cards without counting the same disposal twice', function () {
    $asset = historicalProperty(60000);
    foreach ([InventoryAssetEventType::Disposal, InventoryAssetEventType::Derecognition] as $type) {
        $this->lifecycle->recordSystemEvent($asset, $type, ['event_date' => '2026-10-09', 'reference_no' => 'DISPOSAL-APPROVAL', 'notes' => 'Approved waste-material disposal.'], $this->manager);
    }
    $asset->update(['derecognized_at' => '2026-10-09']);
    $asset->delete();
    $rows = collect(propertyHistoryRows($asset));
    expect($rows->where('Transaction', 'Disposal')->sole()['Qty. disposed'])->toBe(1)
        ->and($rows->where('Transaction', 'Derecognition')->sole()['Qty. disposed'])->toBe(0)
        ->and($rows->last()['Recorded property qty.'])->toBe(0);
});
test('period filters preserve earlier custody and accounting state and include physical count evidence', function () {
    $asset = historicalProperty(60000);
    $a = HrisReference::factory()->create(['name' => 'Historical Officer']);
    $this->travelTo(now()->setDate(2026, 10, 3));
    $this->custody->assign($asset, $a, $this->manager);
    $this->travelTo(now()->setDate(2026, 10, 9));
    $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::PhysicalCount, ['event_date' => '2026-10-09', 'reference_no' => 'SIGNED-COUNT', 'quantity' => 1], $this->manager);
    $rows = propertyHistoryRows($asset, from: '2026-10-08');
    expect($rows)->toHaveCount(1)->and($rows[0]['Last recorded physical count'])->toBe(1)->and($rows[0]['Officer / office'])->toContain('Historical Officer');
    $quiet = propertyHistoryRows($asset, from: '2026-10-11');
    expect($quiet)->toHaveCount(1)->and($quiet[0]['Transaction'])->toBe('Closing balance (no period movement)')->and($quiet[0]['Last recorded physical count'])->toBe(1);
});
test('legacy property-card metadata is identified and no receipt or accountability evidence is invented', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_date' => '2026-10-01', 'acquisition_cost' => 8000, 'fund_cluster' => '01']);
    $rows = propertyHistoryRows($asset);
    expect($rows)->toHaveCount(1)->and($rows[0]['Transaction'])->toBe('Legacy opening record')
        ->and($rows[0]['Accounting inventory qty.'])->toBeNull()->and($rows[0]['Reference'])->toBeNull()
        ->and($rows[0]['Evidence basis'])->toBe('Legacy current descriptive metadata');
});

test('legacy receipts with unknown acquisition costs retain unknown valuation instead of zero or an invalid decimal', function () {
    $asset = InventoryAsset::factory()->createQuietly(['acquisition_date' => '2026-10-01', 'acquisition_cost' => null, 'fund_cluster' => '01', 'accounting_classification' => AssetAccountingClassification::SemiExpendable]);
    $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Receipt, ['event_date' => '2026-10-01', 'reference_no' => 'LEGACY-UNVALUED', 'quantity' => 1], $this->manager);
    $row = collect(propertyHistoryRows($asset))->firstWhere('Transaction', 'Received by Property Unit');
    expect($row['Unit acquisition cost'])->toBeNull()->and($row['Recorded amount'])->toBeNull()->and($row['Qty. received'])->toBe(1);
});
test('all property card routes retain classification boundaries and render chronological tables', function (string $key, float $cost) {
    $asset = historicalProperty($cost);
    $other = historicalProperty($cost === 60000.0 ? 8000.0 : 60000.0);
    $filters = ['as_of' => '2026-10-12', 'from' => null, 'to' => '2026-10-12', 'fund_cluster' => '01', 'custodian' => null, 'period' => 'custom', 'period_label' => 'October', 'year' => 2026];
    $table = app(InventoryReportService::class)->printable($key, $filters);
    expect(collect($table['rows'])->pluck(0)->unique()->all())->toBe([$asset->property_number]);
    $this->actingAs($this->manager)->get(route('inventory.reports.print', [$key, 'as_of' => '2026-10-12']))->assertOk()->assertSee('Qty. disposed')->assertSee('Evidence basis');
})->with([['property-card', 60000.0], ['spc', 8000.0], ['splc', 8000.0]]);
