<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryAssetEvent;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

test('property pages include the full timeline and borrowing records for the correct property', function (string $registry, int $cost) {
    $manager = User::factory()->inventoryManager()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost]);
    $borrower = HrisReference::factory()->create(['name' => 'Historical Borrower']);
    InventoryAssetBorrowing::factory()->count(26)->create([
        'inventory_asset_id' => $asset->getKey(),
        'borrower_reference_id' => $borrower->getKey(),
        'status' => 'returned',
        'borrowed_at' => now()->subMonth(),
        'returned_at' => now()->subWeek(),
        'notes' => 'Released for field work.',
        'return_notes' => 'Returned in good condition.',
    ]);
    InventoryAssetBorrowing::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'borrower_reference_id' => null,
        'borrower_name' => 'Manual Borrower',
        'borrowed_at' => now(),
    ]);
    InventoryAssetBorrowing::factory()->create();
    InventoryAssetEvent::factory()->count(30)->create(['inventory_asset_id' => $asset->getKey()]);

    $this->actingAs($manager)->get(route("inventory.{$registry}.show", $asset))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Assets/Show')
            ->where('asset.inventory_asset_id', $asset->getKey())
            ->has('asset.events', 30)
            ->has('asset.borrowings', 27)
            ->where('asset.borrowings.0.borrower_name', 'Manual Borrower')
            ->where('asset.borrowings.1.borrower_reference.name', 'Historical Borrower')
            ->where('asset.borrowings.1.return_notes', 'Returned in good condition.')
            ->has('asset.accountability_documents', 0)
        );
})->with([['assets', 75000], ['semi_expendable', 7500]]);

test('property pages render history and breadcrumbs back to their own registry', function (string $registry, int $cost) {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost]);
    InventoryAssetEvent::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'details' => [
            'borrowing_id' => 987654,
            'hris_reference_id' => 876543,
            'source' => 'legacy_register',
            'property_snapshot' => ['name' => 'Historical vehicle name', 'acquisition_cost' => '1000000.00', 'acquisition_date' => '2010-01-01', 'condition_status' => 'good', 'accounting_classification' => 'ppe'],
        ],
    ]);
    InventoryAssetBorrowing::factory()->create([
        'inventory_asset_id' => $asset->getKey(),
        'borrower_reference_id' => null,
        'borrower_name' => 'Previous manual borrower',
        'status' => 'returned',
        'returned_at' => now(),
        'return_notes' => 'All accessories returned.',
    ]);
    $asset->load(['category', 'subcategory', 'currentCustodian', 'activeBorrowing', 'events', 'latestIcsDocument', 'borrowings.borrowerReference', 'accountabilityDocuments']);
    $result = Process::path(base_path())->input($asset->toJson())
        ->timeout(60)->run(['node', base_path('tests/PropertyDetailsPage.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $rendered = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    expect($rendered['html'])->toContain('Borrowing history', 'Previous manual borrower', 'All accessories returned.', 'Accountability history', $asset->name)
        ->toContain('Property details at the time of transaction', 'Historical vehicle name', '₱1,000,000.00', 'Jan 1, 2010', 'Existing property register')
        ->not->toContain('role="dialog"', 'property_snapshot', 'acquisition_cost', 'legacy_register', '987654', '876543', 'Borrowing Id', '{&quot;name&quot;')
        ->and($rendered['breadcrumbs'][0]['href']['url'])->toBe(route("inventory.{$registry}.index", absolute: false))
        ->and($rendered['breadcrumbs'][1]['title'])->toBe($asset->property_number);

    expect($rendered['pages']['overview'])->toContain('Identification', 'Current custody', 'data-priority="primary"', 'role="tablist"')
        ->toContain('Acquisition source documents', 'Physical count', 'Record information')
        ->not->toContain('Expand supplementary details')
        ->not->toContain('Lifecycle timeline', 'Previous manual borrower', 'Acquisition and valuation')
        ->and($rendered['pages']['accounting'])->toContain('Acquisition and valuation', 'data-priority="primary"')
        ->not->toContain('Identification', 'Lifecycle timeline')
        ->and($rendered['pages']['borrowing'])->toContain('Previous manual borrower', 'All accessories returned.')
        ->not->toContain('Lifecycle timeline', 'Identification')
        ->and($rendered['pages']['activity'])->toContain('Lifecycle timeline', 'View details', 'Search lifecycle timeline')
        ->not->toContain('Identification', 'Acquisition and valuation')
        ->and($rendered['pages']['invalid'])->toContain('Identification')
        ->and($rendered['filtered'])->toBe([['search' => 'Maria returned laptop', 'status' => 'returned']])
        ->and($rendered['noMatches'])->toBe([])
        ->and($rendered['historyPreview'])->toContain('Record 5', 'Page', 'of', 'View details')
        ->not->toContain('Record 6', 'Record 7', 'Record 8');

    $accountingHtml = new DOMDocument;
    $overviewHtml = new DOMDocument;
    @$overviewHtml->loadHTML($rendered['pages']['overview']);
    $overview = new DOMXPath($overviewHtml);
    expect($overview->query('//table[@data-overview-table]')->length)->toBe(5)
        ->and($overview->query('//table[@data-overview-table]/thead')->length)->toBe(0)
        ->and($overview->query('//div[@data-overview-column="identification"]//table[@aria-label="Identification"]')->length)->toBe(1)
        ->and($overview->query('//div[@data-overview-column="custody"]//table[@aria-label="Location and custody"]')->length)->toBe(1)
        ->and($overview->query('//table[@aria-label="Identification"]//th[@scope="row" and text()="Property number"]')->length)->toBe(1);
    @$accountingHtml->loadHTML($rendered['pages']['accounting']);
    $accounting = new DOMXPath($accountingHtml);
    expect($accounting->query('//div[@role="region" and .//table[@data-accounting-table] and contains(concat(" ", normalize-space(@class), " "), " w-full ")]')->length)->toBe($registry === 'assets' ? 4 : 3)
        ->and($accounting->query('//div[@data-accounting-column="acquisition"]/div[@role="region"]')->length)->toBe(2)
        ->and($accounting->query('//div[@data-accounting-column="acquisition"]//table[@aria-label="Acquisition details"]')->length)->toBe(1)
        ->and($accounting->query('//div[@data-accounting-column="acquisition"]//table[@aria-label="Current valuation"]')->length)->toBe(1)
        ->and($accounting->query('//div[@data-accounting-column="depreciation"]//table[@aria-label="Accounting policy and status"]')->length)->toBe(1)
        ->and($accounting->query('//div[@data-accounting-column="depreciation"]//table[@aria-label="Depreciation"]')->length)->toBe($registry === 'assets' ? 1 : 0)
        ->and($accounting->query('//div[@role="region" and .//table[@data-accounting-table]]')->length)->toBe($registry === 'assets' ? 4 : 3)
        ->and($accounting->query('//table[@data-accounting-table]/tbody/tr')->length)->toBeGreaterThan(8)
        ->and($accounting->query('//table[@data-accounting-table]/tbody')->length)->toBe($registry === 'assets' ? 4 : 3)
        ->and($accounting->query('//tbody[@aria-label="Acquisition details"]//th[@scope="row" and text()="Acquisition cost"]')->length)->toBe(1)
        ->and($accounting->query('//tbody[@aria-label="Accounting policy and status"]//th[@scope="row" and text()="Accounting status"]')->length)->toBe(1)
        ->and($accounting->query('//tbody[@aria-label="Current valuation"]//th[@scope="row" and text()="Appraised value"]')->length)->toBe(1)
        ->and($accounting->query('//tbody[@aria-label="Depreciation"]')->length)->toBe($registry === 'assets' ? 1 : 0)
        ->and($accounting->query('//table[@data-accounting-table]//th[@scope="row" and text()="Acquisition cost"]')->length)->toBe(1)
        ->and($accounting->query('//table[@data-accounting-table]//th[@scope="row" and text()="Depreciation to date"]')->length)->toBe($registry === 'assets' ? 1 : 0);
})->with([['assets', 75000], ['semi_expendable', 7500]]);

test('property detail access is restricted to governance users and the current custodian', function (string $registry, int $cost) {
    $employee = HrisReference::factory()->create();
    $user = User::factory()->create(['hris_reference_id' => $employee->getKey()]);
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost, 'current_custodian_reference_id' => $employee->getKey()]);
    $otherAsset = InventoryAsset::factory()->create(['acquisition_cost' => $cost]);

    $this->actingAs($user)->get(route("inventory.{$registry}.show", $asset))->assertSuccessful();
    $this->get(route("inventory.{$registry}.show", $otherAsset))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route("inventory.{$registry}.show", $asset))->assertForbidden();
})->with([['assets', 75000], ['semi_expendable', 7500]]);

test('property detail routes reject records from the other registry and missing records', function () {
    $this->actingAs(User::factory()->inventoryManager()->create());
    $ppe = InventoryAsset::factory()->create(['acquisition_cost' => 75000]);
    $semi = InventoryAsset::factory()->create(['acquisition_cost' => 7500]);
    $this->get(route('inventory.assets.show', $semi))->assertNotFound();
    $this->get(route('inventory.semi_expendable.show', $ppe))->assertNotFound();
    $this->get(route('inventory.assets.show', 999999))->assertNotFound();
});

test('archived property details remain available and guests must authenticate', function (string $registry, int $cost) {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => $cost]);
    $asset->delete();
    $this->get(route("inventory.{$registry}.show", $asset))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->inventoryManager()->create())
        ->get(route("inventory.{$registry}.show", $asset))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('asset.deleted_at', fn ($value) => $value !== null));
})->with([['assets', 75000], ['semi_expendable', 7500]]);
