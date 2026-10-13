<?php

use App\AssetConditionStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\InventoryAssetLifecycleService;
use App\Services\InventoryAssetService;
use App\Services\InventoryReportService;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(10, 0));
    $this->manager = User::factory()->inventoryManager()->create();
    $this->asset = InventoryAsset::factory()->create(['name' => 'Original transfer property', 'location' => 'Old warehouse', 'acquisition_date' => '2026-10-01', 'acquisition_cost' => 60000, 'fund_cluster' => '01']);
    $this->outgoing = HrisReference::factory()->create(['name' => 'Original outgoing officer']);
    $this->incoming = HrisReference::factory()->create(['name' => 'Original incoming officer']);
    $this->custody = app(InventoryAssetService::class);
    $this->custody->assign($this->asset, $this->outgoing, $this->manager);
    $this->previous = $this->asset->accountabilityDocuments()->sole();
});

function transferReportRows(array $overrides = [], string $key = 'property-transfer'): array
{
    $report = app(InventoryReportService::class)->printable($key, [...['as_of' => '2026-10-12', 'period' => 'custom', 'period_label' => 'October', 'year' => 2026, 'from' => null, 'to' => null, 'fund_cluster' => '01', 'custodian' => null], ...$overrides]);

    return array_map(fn (array $row): array => array_combine($report['columns'], $row), $report['rows']);
}

test('transfer registers exclude initial assignments and respect the historical cutoff', function () {
    expect(transferReportRows())->toBeEmpty();
    $this->travelTo(now()->setDate(2026, 10, 15));
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    expect(transferReportRows())->toBeEmpty()
        ->and(transferReportRows(['as_of' => '2026-10-15']))->toHaveCount(1);
});

test('custody transfer evidence survives renaming reassignment and archival', function () {
    $this->travelTo(now()->setDate(2026, 10, 5));
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    $replacement = $this->asset->accountabilityDocuments()->where('supersedes_document_id', $this->previous->id)->sole();
    $event = $this->asset->events()->where('event_type', InventoryAssetEventType::Transfer)->sole();
    $this->outgoing->update(['name' => 'Renamed outgoing officer']);
    $this->incoming->update(['name' => 'Renamed incoming officer']);
    $this->asset->update(['name' => 'Future property label', 'location' => 'Future location', 'condition_status' => AssetConditionStatus::NonUsable, 'fund_cluster' => '02']);
    $this->asset->delete();
    $row = transferReportRows()[0];
    expect($row['Transfer reference'])->toBe($event->reference_no)
        ->and($row['Transfer type'])->toBe('Reassignment')
        ->and($row['Description'])->toBe('Original transfer property')
        ->and($row['From accountable officer'])->toBe('Original outgoing officer')
        ->and($row['To accountable officer'])->toBe('Original incoming officer')
        ->and($row['Previous PAR / ICS'])->toBe($this->previous->document_no)
        ->and($row['Replacement PAR / ICS'])->toBe($replacement->document_no)
        ->and($row['From location'])->toBe('Old warehouse')
        ->and($row['Recorded condition'])->not->toBe(AssetConditionStatus::NonUsable->value)
        ->and($row['Transfer approval'])->toBe('Not recorded')
        ->and($row['Joint inspection / handover'])->toBe('Not recorded');
});

test('transfer filters use either recorded officer and independent date bounds', function () {
    $this->travelTo(now()->setDate(2026, 10, 5));
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    expect(transferReportRows(['custodian' => $this->outgoing->id]))->toHaveCount(1)
        ->and(transferReportRows(['custodian' => $this->incoming->id]))->toHaveCount(1)
        ->and(transferReportRows(['custodian' => HrisReference::factory()->create()->id]))->toBeEmpty()
        ->and(transferReportRows(['from' => '2026-10-06']))->toBeEmpty()
        ->and(transferReportRows(['to' => '2026-10-04']))->toBeEmpty()
        ->and(transferReportRows(['fund_cluster' => '02']))->toBeEmpty();
});

test('location transfers use the officer document existing at the event rather than later custody', function () {
    $this->travelTo(now()->setDate(2026, 10, 3));
    app(InventoryAssetLifecycleService::class)->recordSystemEvent($this->asset, InventoryAssetEventType::Transfer, ['event_date' => '2026-10-03', 'reference_no' => 'PTR-LOCATION', 'from_location' => 'Old warehouse', 'to_location' => 'Pump station'], $this->manager);
    $this->travelTo(now()->setDate(2026, 10, 8));
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    $row = transferReportRows(['to' => '2026-10-03'])[0];
    expect($row['Transfer type'])->toBe('Location transfer')
        ->and($row['From accountable officer'])->toBe('Original outgoing officer')
        ->and($row['To accountable officer'])->toBe('Original outgoing officer')
        ->and($row['To location'])->toBe('Pump station');
});

test('legacy transfer events do not invent custody condition or approval from current records', function () {
    $legacy = InventoryAsset::factory()->create(['name' => 'Legacy metadata', 'acquisition_cost' => 60000, 'fund_cluster' => '01']);
    $legacy->events()->create(['event_type' => InventoryAssetEventType::Transfer, 'event_date' => '2026-10-02', 'reference_no' => 'LEGACY-TRANSFER', 'from_location' => 'A', 'to_location' => 'B']);
    $row = transferReportRows()[0];
    expect($row['Description'])->toBe('Legacy metadata')
        ->and($row['Evidence basis'])->toBe('Legacy current metadata; custody not inferred')
        ->and($row['From accountable officer'])->toBe('Not recorded')
        ->and($row['Recorded condition'])->toBe('Not recorded')
        ->and($row['Unit acquisition cost'])->toBeNull();
});

test('semi expendable transfer reports exclude PPE and initial ICS issuance without changing accounting', function () {
    $semi = InventoryAsset::factory()->create(['acquisition_cost' => 8000, 'fund_cluster' => '01']);
    $this->custody->assign($semi, $this->outgoing, $this->manager);
    expect(transferReportRows(key: 'itr'))->toBeEmpty();
    $this->travelTo(now()->setDate(2026, 10, 5));
    $this->custody->assign($semi, $this->incoming, $this->manager);
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    $before = $semi->fresh()->accounting_status;
    $rows = transferReportRows(key: 'itr');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Previous PAR / ICS'])->toStartWith('ICS-')
        ->and($rows[0]['Replacement PAR / ICS'])->toStartWith('ICS-')
        ->and($semi->fresh()->accounting_status)->toBe($before);
});

test('printed transfer reports expose movement evidence and its missing workflow controls', function () {
    $this->travelTo(now()->setDate(2026, 10, 5));
    $this->custody->assign($this->asset, $this->incoming, $this->manager);
    $this->actingAs($this->manager)->get(route('inventory.reports.print', ['document' => 'property-transfer', 'as_of' => '2026-10-12', 'fund_cluster' => '01']))
        ->assertSuccessful()->assertSee('Original outgoing officer')->assertSee('Original incoming officer')
        ->assertSee('Joint inspection / handover')->assertSee('Not recorded');
});

test('backdated location events cannot adopt a later issued officer document', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 60000, 'fund_cluster' => '01']);
    $this->travelTo(now()->setDate(2026, 10, 8));
    $this->custody->assign($asset, $this->incoming, $this->manager);
    app(InventoryAssetLifecycleService::class)->recordSystemEvent($asset, InventoryAssetEventType::Transfer, ['event_date' => '2026-10-02', 'reference_no' => 'BACKDATED-LOCATION', 'from_location' => 'A', 'to_location' => 'B'], $this->manager);
    $row = transferReportRows()[0];
    expect($row['From accountable officer'])->toBe('Not recorded')
        ->and($row['To accountable officer'])->toBe('Not recorded')
        ->and($row['Previous PAR / ICS'])->toBeNull();
});

test('location movements after a return do not reinstate the former accountable officer', function () {
    $this->travelTo(now()->setDate(2026, 10, 3));
    $this->custody->unassign($this->asset, $this->manager);
    $this->travelTo(now()->setDate(2026, 10, 4));
    app(InventoryAssetLifecycleService::class)->recordSystemEvent($this->asset, InventoryAssetEventType::Transfer, ['event_date' => '2026-10-04', 'reference_no' => 'POST-RETURN-LOCATION', 'from_location' => 'A', 'to_location' => 'B'], $this->manager);
    expect(transferReportRows()[0]['From accountable officer'])->toBe('Not recorded');
});
