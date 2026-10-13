<?php

use App\AssetAccountingClassification;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Models\ProcurementRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('procurement records consumables semi expendable property and PPE in their proper ledgers', function () {
    $preparer = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $seriesCategory = InventorySeriesCategory::factory()->create();
    $assetCategory = InventoryAssetCategory::factory()->create([
        'name' => 'Office Equipment',
        'default_useful_life_months' => 60,
    ]);
    $assetSubcategory = InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $assetCategory->getKey(),
        'name' => 'Computing Equipment',
    ]);

    $this->actingAs($preparer)->post(route('inventory.procurement.store'), [...procurementIntakeData(),
        'type' => 'new_item',
        'source' => 'manual',
        'purpose' => 'Acquire operating supplies and accountable equipment.',
        'lines' => [
            [
                'item_kind' => 'consumable',
                'item_name' => 'Printer ink',
                'unit_of_measure' => 'cartridge',
                'quantity' => 4,
                'estimated_unit_cost' => 900,
            ],
            [
                'item_kind' => 'property',
                'asset_category_id' => $assetCategory->getKey(),
                'asset_subcategory_id' => $assetSubcategory->getKey(),
                'item_name' => 'Office monitor',
                'unit_of_measure' => 'unit',
                'quantity' => 2,
                'estimated_unit_cost' => 12000,
            ],
            [
                'item_kind' => 'property',
                'asset_category_id' => $assetCategory->getKey(),
                'asset_subcategory_id' => $assetSubcategory->getKey(),
                'item_name' => 'Engineering workstation',
                'unit_of_measure' => 'unit',
                'quantity' => 1,
                'estimated_unit_cost' => 49000,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $procurement = ProcurementRequest::query()->with('lines')->sole();
    $consumableLine = $procurement->lines->firstWhere('item_name', 'Printer ink');

    prepareAndSignProcurement($procurement, $approver);

    $actions = [
        ['action' => 'approve'],
        ['action' => 'forward', 'endorsement_recipient' => 'Procurement Unit'],
        ['action' => 'order', 'procurement_mode' => 'Competitive bidding', 'purchase_order_no' => 'PO-MIXED-1'],
        [
            'action' => 'record_delivery',
            'delivery_reference' => 'DR-MIXED-1',
            'received_at' => today()->toDateString(),
            'line_receipts' => $procurement->lines->map(fn ($line): array => [
                'line_id' => $line->getKey(),
                'quantity_received' => $line->quantity,
                'actual_unit_cost' => match ($line->item_name) {
                    'Printer ink' => 875,
                    'Office monitor' => 11500,
                    'Engineering workstation' => 50000,
                },
            ])->all(),
        ],
        ['action' => 'accept', 'inspection_acceptance_no' => 'IAR-MIXED-1'],
    ];

    foreach ($actions as $data) {
        $this->actingAs($approver)
            ->patch(route('inventory.procurement.transition', $procurement), [...$data, 'attested' => true])
            ->assertSessionHasNoErrors();
    }

    $consumable = InventoryItem::query()->where('name', 'Printer ink')->sole();
    $assets = InventoryAsset::query()->with('events')->orderBy('name')->get();

    expect($procurement->fresh()->status)->toBe('accepted')
        ->and($consumable->quantity)->toBe(4)
        ->and($assets)->toHaveCount(3)
        ->and($assets->where('name', 'Office monitor'))->toHaveCount(2)
        ->and($assets->where('name', 'Office monitor')->first()->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable)
        ->and($assets->where('name', 'Engineering workstation')->sole()->accounting_classification)->toBe(AssetAccountingClassification::Ppe)
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->procurement_request_line_id !== null))->toBeTrue()
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->inspection_acceptance_report_no === 'IAR-MIXED-1'))->toBeTrue()
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->events->contains(fn ($event): bool => $event->event_type->value === 'registered')))->toBeTrue()
        ->and($assets->every(fn (InventoryAsset $asset): bool => $asset->events->contains(fn ($event): bool => $event->event_type->value === 'accepted')))->toBeTrue();
});

test('property procurement requires a matching active category and subcategory', function () {
    $manager = User::factory()->inventoryManager()->create();
    $category = InventoryAssetCategory::factory()->create();
    $otherSubcategory = InventoryAssetSubcategory::factory()->create();

    $this->actingAs($manager)->post(route('inventory.procurement.store'), [...procurementIntakeData(),
        'type' => 'new_item',
        'source' => 'manual',
        'purpose' => 'Acquire accountable equipment.',
        'lines' => [[
            'item_kind' => 'property',
            'asset_category_id' => $category->getKey(),
            'asset_subcategory_id' => $otherSubcategory->getKey(),
            'item_name' => 'Portable tester',
            'unit_of_measure' => 'unit',
            'quantity' => 1,
            'estimated_unit_cost' => 25000,
        ]],
    ])->assertSessionHasErrors('lines.0.asset_subcategory_id');

    expect(ProcurementRequest::query()->exists())->toBeFalse();
});

test('procurement page exposes separate consumable and property classification controls', function () {
    $manager = User::factory()->inventoryManager()->create();
    $category = InventoryAssetCategory::factory()->create();
    InventoryAssetSubcategory::factory()->create([
        'inventory_asset_category_id' => $category->getKey(),
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.procurement.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Procurement/Index')
            ->has('assetCategories', 1)
            ->has('assetCategories.0.subcategories', 1));
});
