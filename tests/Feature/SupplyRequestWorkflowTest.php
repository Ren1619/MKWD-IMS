<?php

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryStockService;
use Inertia\Testing\AssertableInertia as Assert;

test('a request may contain multiple existing and new supply lines and returns visible feedback', function () {
    $employee = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($employee)
        ->post(route('inventory.requests.store'), [
            'office_name' => 'Engineering Division',
            'purpose' => 'Field operations',
            'lines' => [
                [
                    'inventory_item_id' => $item->getKey(),
                    'is_new_item' => false,
                    'quantity' => 2,
                ],
                [
                    'is_new_item' => true,
                    'item_name' => 'Specialized test strips',
                    'unit_of_measure' => 'box',
                    'quantity' => 3,
                    'estimated_unit_cost' => 125,
                    'justification' => 'No equivalent item exists in the current catalog.',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Supply request submitted.');

    $request = SupplyRequest::query()->with('lines')->sole();

    expect($request->lines)->toHaveCount(2)
        ->and($request->lines->pluck('item_name')->all())
        ->toContain($item->name, 'Specialized test strips');
});

test('inventory managers can submit a supply request for an active employee', function () {
    $manager = User::factory()->inventoryManager()->create();
    $employee = HrisReference::factory()->create([
        'name' => 'Employee Recipient',
        'metadata' => [
            'position' => [
                'section' => ['name' => 'Maintenance Section'],
                'division' => ['name' => 'Engineering Division'],
            ],
        ],
    ]);
    $item = InventoryItem::factory()->create();

    $this->actingAs($manager)
        ->get(route('inventory.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Requests/Index')
            ->has('employees', 1)
            ->where('employees.0.id', $employee->getKey())
            ->where('employees.0.office_name', 'Maintenance Section')
            ->where('responsibilityCenters', [])
            ->where('canManage', true));

    $this->post(route('inventory.requests.store'), [
        'requester_reference_id' => $employee->getKey(),
        'office_name' => 'Untrusted Office',
        'responsibility_center_code' => 'RC-100',
        'purpose' => 'Supplies requested on behalf of an employee.',
        'lines' => [[
            'inventory_item_id' => $item->getKey(),
            'is_new_item' => false,
            'quantity' => 1,
        ]],
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $request = SupplyRequest::query()->sole();

    expect($request->requester_user_id)->toBe($manager->getKey())
        ->and($request->requester_reference_id)->toBe($employee->getKey())
        ->and($request->requester_name)->toBe('Employee Recipient')
        ->and($request->office_name)->toBe('Maintenance Section')
        ->and($request->responsibility_center_code)->toBe('RC-100');
});

test('employees cannot submit a supply request for another employee', function () {
    $requester = User::factory()->employee()->create();
    $otherEmployee = HrisReference::factory()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($requester)
        ->post(route('inventory.requests.store'), [
            'requester_reference_id' => $otherEmployee->getKey(),
            'purpose' => 'Attempted request for another employee.',
            'lines' => [[
                'inventory_item_id' => $item->getKey(),
                'is_new_item' => false,
                'quantity' => 1,
            ]],
        ])
        ->assertSessionHasErrors('requester_reference_id');

    expect(SupplyRequest::query()->exists())->toBeFalse();
});

test('available items provide their weighted-average unit cost for supply requests', function () {
    $employee = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    $stockService = app(InventoryStockService::class);

    $stockService->stockIn($item, ['quantity' => 10, 'unit_cost' => 10, 'received_at' => '2026-08-20']);
    $stockService->stockIn($item, ['quantity' => 30, 'unit_cost' => 20, 'received_at' => '2026-08-21']);

    $this->actingAs($employee)
        ->get(route('inventory.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Requests/Index')
            ->has('items', 1)
            ->has('employees', 0)
            ->where('canManage', false)
            ->where('items.0.inventory_item_id', $item->getKey())
            ->where('items.0.weighted_average_unit_cost', '17.50'));
});

test('request and procurement queues can be searched and limited to records needing action', function () {
    $manager = User::factory()->inventoryManager()->create();

    SupplyRequest::factory()->create([
        'ris_no' => 'RIS-2026-NEEDLE',
        'requester_user_id' => $manager->id,
        'status' => 'submitted',
    ]);
    SupplyRequest::factory()->create([
        'ris_no' => 'RIS-2026-CLOSED',
        'requester_user_id' => $manager->id,
        'status' => 'released',
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.requests.index', [
            'search' => 'NEEDLE',
            'queue' => 'needs_action',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Requests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.ris_no', 'RIS-2026-NEEDLE')
            ->where('filters.queue', 'needs_action'));

    ProcurementRequest::factory()->create([
        'pr_no' => 'PR-2026-NEEDLE',
        'created_by_user_id' => $manager->id,
        'status' => 'for_approval',
    ]);
    ProcurementRequest::factory()->create([
        'pr_no' => 'PR-2026-CLOSED',
        'created_by_user_id' => $manager->id,
        'status' => 'accepted',
    ]);

    $this->get(route('inventory.procurement.index', [
        'search' => 'NEEDLE',
        'queue' => 'needs_action',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Procurement/Index')
            ->has('procurementRequests.data', 1)
            ->where('procurementRequests.data.0.pr_no', 'PR-2026-NEEDLE')
            ->where('filters.queue', 'needs_action'));
});

test('employees submit requests but only a different inventory manager can authorize and release stock', function () {
    $employee = User::factory()->employee()->create();
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    app(InventoryStockService::class)->stockIn($item, ['quantity' => 20, 'unit_cost' => 10, 'received_at' => '2026-08-20']);
    $this->actingAs($employee)->post(route('inventory.requests.store'), ['purpose' => 'Official office operations', 'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 6]]])->assertRedirect();
    $request = SupplyRequest::query()->with('lines')->sole();
    expect($request->ris_no)->toMatch('/^RIS-\d{4}-\d{6}$/')->and($request->status)->toBe('submitted');
    foreach (['approve', 'review', 'release'] as $action) {
        $this->actingAs($manager)->patch(route('inventory.requests.transition', $request), ['action' => $action, 'attested' => true, 'remarks' => 'Reviewed supporting records.'])->assertRedirect();
    }
    expect($request->fresh()->status)->toBe('released')->and($request->lines()->first()->quantity_released)->toBe(6)->and($item->fresh()->quantity)->toBe(14)->and($request->actions()->count())->toBe(4);
});
test('requesters cannot authorize their own supply request', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();
    $this->actingAs($manager)->post(route('inventory.requests.store'), ['purpose' => 'Manager request', 'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 1]]]);
    $this->patch(route('inventory.requests.transition', SupplyRequest::query()->sole()), ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
});

test('super admins can approve supply requests they submitted for another employee', function () {
    $adminEmployee = HrisReference::factory()->create();
    $requestedForEmployee = HrisReference::factory()->create();
    $admin = User::factory()->superAdmin()->create(['hris_reference_id' => $adminEmployee->getKey()]);
    $item = InventoryItem::factory()->create();

    $this->actingAs($admin)->post(route('inventory.requests.store'), [
        'requester_reference_id' => $requestedForEmployee->getKey(),
        'purpose' => 'Request submitted on behalf of another employee.',
        'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 1]],
    ])->assertRedirect();

    $request = SupplyRequest::query()->sole();

    $this->patch(route('inventory.requests.transition', $request), [
        'action' => 'approve',
        'attested' => true,
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe('approved');
});

test('inventory managers cannot authorize a supply request requested for themselves', function () {
    $managerEmployee = HrisReference::factory()->create();
    $manager = User::factory()->inventoryManager()->create(['hris_reference_id' => $managerEmployee->getKey()]);
    $item = InventoryItem::factory()->create();

    $this->actingAs($manager)->post(route('inventory.requests.store'), [
        'requester_reference_id' => $managerEmployee->getKey(),
        'purpose' => 'Manager request for personal office use.',
        'lines' => [['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'quantity' => 1]],
    ])->assertRedirect();

    $this->patch(route('inventory.requests.transition', SupplyRequest::query()->sole()), [
        'action' => 'approve',
        'attested' => true,
    ])->assertSessionHasErrors('action');
});

test('procurement stocks goods only after planning budget and inspection attestations', function () {
    $preparer = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    $this->actingAs($preparer)->post(route('inventory.procurement.store'), [...procurementIntakeData(), 'type' => 'replenishment', 'source' => 'low_stock', 'purpose' => 'Replenish operational stock', 'funding_source' => 'MOOE', 'ppmp_reference' => 'PPMP-2026-01', 'app_reference' => 'APP-2026-01', 'lines' => [['inventory_item_id' => $item->getKey(), 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure, 'quantity' => 10, 'estimated_unit_cost' => 25]]])->assertRedirect();
    $procurement = ProcurementRequest::query()->sole();
    prepareAndSignProcurement($procurement, $approver);
    $actions = [['action' => 'approve'], ['action' => 'forward', 'endorsement_recipient' => 'Procurement Unit'], ['action' => 'order', 'procurement_mode' => 'Applicable mode', 'purchase_order_no' => 'PO-100'], ['action' => 'record_delivery', 'delivery_reference' => 'DR-100', 'received_at' => '2026-08-27', 'actual_unit_cost' => 24]];
    foreach ($actions as $data) {
        $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), [...$data, 'attested' => true])->assertRedirect();
    }
    expect($item->fresh()->quantity)->toBe(0);
    $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), ['action' => 'accept', 'attested' => true, 'inspection_acceptance_no' => 'IAR-100'])->assertRedirect();
    $procurement->refresh();
    expect($procurement->status)->toBe('accepted')->and($procurement->inspection_acceptance_no)->toBe('IAR-100')->and($item->fresh()->quantity)->toBe(10);
});

test('reviewed supply shortages flow through procurement acceptance and become releasable stock', function () {
    $employee = User::factory()->employee()->create();
    $reviewer = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $item = InventoryItem::factory()->create(['quantity' => 0]);
    app(InventoryStockService::class)->stockIn($item, [
        'quantity' => 2,
        'unit_cost' => 20,
        'received_at' => today()->toDateString(),
    ]);

    $this->actingAs($employee)->post(route('inventory.requests.store'), [
        'responsibility_center_code' => 'RC-100',
        'purpose' => 'Supply a ten-unit field requirement.',
        'lines' => [[
            'inventory_item_id' => $item->getKey(),
            'is_new_item' => false,
            'quantity' => 10,
            'estimated_unit_cost' => 25,
        ]],
    ])->assertRedirect();
    $supplyRequest = SupplyRequest::query()->with('lines')->sole();

    foreach (['approve', 'review', 'review'] as $action) {
        $this->actingAs($reviewer)->patch(route('inventory.requests.transition', $supplyRequest), [
            'action' => $action,
            'attested' => true,
        ])->assertSessionHasNoErrors();
    }

    $procurement = ProcurementRequest::query()->with('lines')->sole();

    expect($supplyRequest->fresh()->status)->toBe('awaiting_replenishment')
        ->and($procurement->supply_request_id)->toBe($supplyRequest->getKey())
        ->and($procurement->source)->toBe('request_shortage')
        ->and($procurement->responsibility_center_code)->toBe('RC-100')
        ->and($procurement->lines)->toHaveCount(1)
        ->and($procurement->lines->sole()->quantity)->toBe(8)
        ->and($procurement->lines->sole()->supply_request_line_id)->toBe($supplyRequest->lines->sole()->getKey());

    prepareAndSignProcurement($procurement, $approver);

    $actions = [
        ['action' => 'approve'],
        ['action' => 'forward', 'endorsement_recipient' => 'Procurement Unit'],
        ['action' => 'order', 'procurement_mode' => 'Small value procurement', 'purchase_order_no' => 'PO-100'],
        ['action' => 'record_delivery', 'delivery_reference' => 'DR-100', 'received_at' => today()->toDateString(), 'actual_unit_cost' => 24],
        ['action' => 'accept', 'inspection_acceptance_no' => 'IAR-100'],
    ];

    foreach ($actions as $data) {
        $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), [
            ...$data,
            'attested' => true,
        ])->assertSessionHasNoErrors();
    }

    expect($procurement->fresh()->status)->toBe('accepted')
        ->and($supplyRequest->fresh()->status)->toBe('ready_for_release')
        ->and($supplyRequest->lines()->sole()->quantity_reserved)->toBe(10)
        ->and($item->fresh()->quantity)->toBe(10);

    $this->actingAs($approver)->patch(route('inventory.requests.transition', $supplyRequest), [
        'action' => 'release',
        'attested' => true,
    ])->assertSessionHasNoErrors();

    expect($supplyRequest->fresh()->status)->toBe('released')
        ->and($supplyRequest->lines()->sole()->quantity_released)->toBe(10)
        ->and($item->fresh()->quantity)->toBe(0);
});

test('accepted procurement creates requested new catalog items and links them back for release', function () {
    $employee = User::factory()->employee()->create();
    $reviewer = User::factory()->inventoryManager()->create();
    $approver = User::factory()->superAdmin()->create();
    $seriesCategory = InventorySeriesCategory::factory()->create();

    $this->actingAs($employee)->post(route('inventory.requests.store'), [
        'purpose' => 'Acquire a newly requested laboratory supply.',
        'lines' => [[
            'is_new_item' => true,
            'item_name' => 'Laboratory calibration solution',
            'specifications' => 'Certified reference solution',
            'unit_of_measure' => 'bottle',
            'quantity' => 3,
            'estimated_unit_cost' => 500,
            'justification' => 'Required for instrument calibration.',
        ]],
    ])->assertSessionHasNoErrors();
    $supplyRequest = SupplyRequest::query()->with('lines')->sole();

    foreach (['approve', 'review'] as $action) {
        $this->actingAs($reviewer)->patch(route('inventory.requests.transition', $supplyRequest), [
            'action' => $action,
            'attested' => true,
        ])->assertSessionHasNoErrors();
    }

    $procurement = ProcurementRequest::query()->with('lines')->sole();
    $procurementLine = $procurement->lines->sole();
    $procurementLine->update(['series_category_id' => $seriesCategory->getKey()]);

    prepareAndSignProcurement($procurement, $approver);

    $actions = [
        ['action' => 'approve'],
        ['action' => 'forward', 'endorsement_recipient' => 'Procurement Unit'],
        ['action' => 'order', 'procurement_mode' => 'Small value procurement', 'purchase_order_no' => 'PO-NEW-1'],
        ['action' => 'record_delivery', 'delivery_reference' => 'DR-NEW-1', 'received_at' => today()->toDateString(), 'actual_unit_cost' => 475],
        ['action' => 'accept', 'inspection_acceptance_no' => 'IAR-NEW-1'],
    ];

    foreach ($actions as $data) {
        $this->actingAs($approver)->patch(route('inventory.procurement.transition', $procurement), [
            ...$data,
            'attested' => true,
        ])->assertSessionHasNoErrors();
    }

    $catalogItem = InventoryItem::query()->where('name', 'Laboratory calibration solution')->sole();
    $supplyLine = $supplyRequest->lines()->sole();

    expect($catalogItem->series_category_id)->toBe($seriesCategory->getKey())
        ->and($catalogItem->quantity)->toBe(3)
        ->and($supplyLine->inventory_item_id)->toBe($catalogItem->getKey())
        ->and($supplyLine->is_new_item)->toBeFalse()
        ->and($supplyLine->quantity_reserved)->toBe(3)
        ->and($supplyRequest->fresh()->status)->toBe('ready_for_release');
});

test('a procurement request may contain existing and new catalog items together', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();

    $this->actingAs($manager)
        ->post(route('inventory.procurement.store'), [...procurementIntakeData(),
            'type' => 'mixed',
            'source' => 'manual',
            'purpose' => 'Replenish stock and acquire a new operational supply.',
            'lines' => [
                [
                    'inventory_item_id' => $item->getKey(),
                    'item_name' => $item->name,
                    'unit_of_measure' => $item->unit_of_measure,
                    'quantity' => 10,
                    'estimated_unit_cost' => 25,
                ],
                [
                    'item_name' => 'New water quality reagent',
                    'unit_of_measure' => 'bottle',
                    'quantity' => 5,
                    'estimated_unit_cost' => 150,
                ],
            ],
        ])
        ->assertRedirect();

    $procurement = ProcurementRequest::query()->with('lines')->sole();

    expect($procurement->type)->toBe('mixed')
        ->and($procurement->lines)->toHaveCount(2)
        ->and($procurement->lines->pluck('inventory_item_id')->all())
        ->toContain($item->getKey(), null);
});

test('the purchase request dialog derives item sections from the request type', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Procurement/Index.tsx'));

    expect($source)
        ->toContain("form.data.type !== 'new_item'")
        ->toContain("form.data.type !== 'replenishment'")
        ->toContain('changeRequestType')
        ->not->toContain('Add new catalog items');
});

test('procurement request types enforce their matching catalog line kinds', function (string $type, array $lines) {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();

    $resolvedLines = collect($lines)->map(function (array $line) use ($item): array {
        if (($line['inventory_item_id'] ?? null) === 'existing') {
            $line['inventory_item_id'] = $item->getKey();
            $line['item_name'] = $item->name;
            $line['unit_of_measure'] = $item->unit_of_measure;
        }

        return $line;
    })->all();

    $this->actingAs($manager)
        ->post(route('inventory.procurement.store'), [...procurementIntakeData(),
            'type' => $type,
            'source' => 'manual',
            'purpose' => 'Validate procurement line types.',
            'lines' => $resolvedLines,
        ])
        ->assertSessionHasErrors('lines');

    expect(ProcurementRequest::query()->count())->toBe(0);
})->with([
    'replenishment with a new catalog item' => [
        'replenishment',
        [[
            'item_name' => 'New safety supply',
            'unit_of_measure' => 'piece',
            'quantity' => 1,
            'estimated_unit_cost' => 100,
        ]],
    ],
    'new item with an existing catalog item' => [
        'new_item',
        [[
            'inventory_item_id' => 'existing',
            'quantity' => 1,
            'estimated_unit_cost' => 100,
        ]],
    ],
    'mixed without a new catalog item' => [
        'mixed',
        [[
            'inventory_item_id' => 'existing',
            'quantity' => 1,
            'estimated_unit_cost' => 100,
        ]],
    ],
    'mixed without an existing catalog item' => [
        'mixed',
        [[
            'item_name' => 'New safety supply',
            'unit_of_measure' => 'piece',
            'quantity' => 1,
            'estimated_unit_cost' => 100,
        ]],
    ],
]);
