<?php

use App\Models\InventoryAsset;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\User;
use App\Services\InventoryReportService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('inventory report routes require authentication', function () {
    $this->get(route('inventory.reports.index'))->assertRedirect(route('login'));
    $this->get(route('inventory.reports.export'))->assertRedirect(route('login'));
    $this->get(route('inventory.reports.print', 'rpcppe'))->assertRedirect(route('login'));
});

test('reports page exposes the complete COA audit document catalog', function () {
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 24)
            ->where('documents.0.key', 'rpcppe')
            ->where('documents.0.code', 'RPCPPE'));
});

test('COA documents render printable audit tables', function (string $document, string $heading) {
    InventoryAsset::factory()->create([
        'property_number' => 'PPE-2026-001',
        'quantity_per_property_card' => 2,
        'quantity_per_physical_count' => 1,
    ]);

    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.print', [
            'document' => $document,
            'as_of' => '2026-12-31',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]))
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('Print / Save as PDF');
})->with([
    'physical PPE' => ['rpcppe', 'Report on the Physical Count'],
    'variance' => ['variance-reconciliation', 'Inventory Variance and Reconciliation Report'],
    'property card' => ['property-card', 'Property Card'],
    'audit exceptions' => ['audit-exceptions', 'Inventory Audit Exception Report'],
]);

test('print filters reject invalid reporting periods and unknown documents', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get(route('inventory.reports.print', [
            'document' => 'rpcppe',
            'from' => '2026-12-31',
            'to' => '2026-01-01',
        ]))
        ->assertSessionHasErrors('to');

    $this->actingAs($user)
        ->get('/inventory/reports/not-a-report/print')
        ->assertNotFound();
});

test('every registered COA document has a working printable response', function () {
    $this->actingAs(User::factory()->employee()->create());

    foreach (InventoryReportService::documentKeys() as $document) {
        $this->get(route('inventory.reports.print', $document))
            ->assertOk()
            ->assertSee('Print / Save as PDF');
    }
});

test('printable transaction reports support all-time and quarterly periods', function () {
    $item = InventoryItem::factory()->create(['name' => 'Quarterly Bond Paper']);
    InventoryItemStockOut::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'ris_no' => 'RIS-Q1',
        'stocked_out_at' => '2026-02-15',
    ]);
    InventoryItemStockOut::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'ris_no' => 'RIS-Q4',
        'stocked_out_at' => '2026-11-15',
    ]);
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get(route('inventory.reports.print', [
            'document' => 'rsmi',
            'period' => 'all',
        ]))
        ->assertOk()
        ->assertSee('All time')
        ->assertSee('RIS-Q1')
        ->assertSee('RIS-Q4');

    $this->actingAs($user)
        ->get(route('inventory.reports.print', [
            'document' => 'rsmi',
            'period' => 'q1',
            'year' => 2026,
        ]))
        ->assertOk()
        ->assertSee('Q1 2026')
        ->assertSee('RIS-Q1')
        ->assertDontSee('RIS-Q4');
});

test('custom report periods require both valid dates', function () {
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.print', [
            'document' => 'rsmi',
            'period' => 'custom',
            'from' => '2026-01-01',
        ]))
        ->assertSessionHasErrors('to');
});

test('employees can review live inventory valuation and depreciation summaries', function () {
    $this->travelTo(Carbon::parse('2026-08-14 09:00:00'));

    $item = InventoryItem::factory()->create([
        'name' => 'Bond Paper',
        'quantity' => 4,
        'reorder_point' => 5,
    ]);
    InventoryItemBatch::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'quantity_in' => 4,
        'quantity_remaining' => 4,
        'unit_cost' => 25,
        'expiration_date' => today()->addDays(5),
    ]);
    InventoryAsset::factory()->create([
        'acquisition_date' => '2025-08-14',
        'acquisition_cost' => 120000,
        'depreciation_useful_life_months' => 60,
    ]);

    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Reports/Index')
            ->where('filters.report', 'consumables')
            ->where('summary.consumable_types', 1)
            ->where('summary.stock_on_hand', 4)
            ->where('summary.low_stock', 1)
            ->where('summary.consumable_value', 100)
            ->where('summary.expiring_value', 100)
            ->where('summary.expired_value', 0)
            ->where('summary.asset_count', 1)
            ->where('summary.acquisition_cost', 120000)
            ->where('summary.depreciation', 22800)
            ->where('summary.book_value', 97200)
            ->has('records.data', 1)
            ->where('records.data.0.inventory_item_id', $item->inventory_item_id)
            ->has('options.lifecycles')
            ->has('options.conditions')
            ->has('options.custody'));
});

test('reports apply operational filters to consumables and assets', function () {
    $user = User::factory()->employee()->create();
    $lowStock = InventoryItem::factory()->create([
        'name' => 'Low Stock Toner',
        'quantity' => 2,
        'reorder_point' => 5,
    ]);
    InventoryItem::factory()->create([
        'name' => 'Healthy Stock Toner',
        'quantity' => 20,
        'reorder_point' => 5,
    ]);
    $disposedAsset = InventoryAsset::factory()->create([
        'name' => 'Disposed Printer',
        'lifecycle_status' => 'disposed',
        'condition_status' => 'non_usable',
    ]);
    InventoryAsset::factory()->create([
        'name' => 'Active Printer',
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.reports.index', [
            'report' => 'consumables',
            'attention' => 'low_stock',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.inventory_item_id', $lowStock->inventory_item_id)
            ->where('filters.attention', 'low_stock'));

    $this->actingAs($user)
        ->get(route('inventory.reports.index', [
            'report' => 'assets',
            'lifecycle_status' => 'disposed',
            'condition_status' => 'non_usable',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.report', 'assets')
            ->has('records.data', 1)
            ->where('records.data.0.inventory_asset_id', $disposedAsset->inventory_asset_id)
            ->where('records.data.0.lifecycle_status', 'disposed'));
});

test('filtered CSV exports neutralize spreadsheet formulas', function () {
    $this->travelTo(Carbon::parse('2026-08-14 09:00:00'));
    $item = InventoryItem::factory()->create([
        'name' => '=FormulaPayload',
        'stock_number' => '@RISK',
        'quantity' => 3,
    ]);
    InventoryItemBatch::factory()->create([
        'inventory_item_id' => $item->inventory_item_id,
        'quantity_in' => 3,
        'quantity_remaining' => 3,
        'unit_cost' => 10,
    ]);
    InventoryItem::factory()->create(['name' => 'Excluded Record']);

    $response = $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.export', [
            'report' => 'consumables',
            'search' => 'FormulaPayload',
        ]));

    $response
        ->assertOk()
        ->assertDownload('consumable-inventory-2026-08-14-090000.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $content = $response->streamedContent();

    expect($content)
        ->toContain("'@RISK")
        ->toContain("'=FormulaPayload")
        ->toContain('30.00')
        ->not->toContain('Excluded Record');
});

test('inventory report filters reject invalid input', function (array $query, string $field) {
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('inventory.reports.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    'report' => [['report' => 'ledger'], 'report'],
    'search length' => [['search' => str_repeat('a', 101)], 'search'],
    'records' => [['records' => 'deleted'], 'records'],
    'attention' => [['attention' => 'damaged'], 'attention'],
    'lifecycle' => [['lifecycle_status' => 'missing'], 'lifecycle_status'],
    'condition' => [['condition_status' => 'damaged'], 'condition_status'],
    'custody' => [['custody_status' => 'held'], 'custody_status'],
]);
