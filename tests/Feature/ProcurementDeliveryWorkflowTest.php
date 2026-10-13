<?php

use App\AssetAccountingClassification;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use App\Models\InventoryItem;
use App\Models\ProcurementDelivery;
use App\Models\ProcurementDeliveryAction;
use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\InventoryReportService;
use App\Services\ProcurementDeliveryService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
    $this->recorder = User::factory()->inventoryManager()->create();
    $this->inspector = User::factory()->inventoryManager()->create();
    $this->acceptor = User::factory()->inventoryManager()->create();
    $this->item = InventoryItem::factory()->create(['quantity' => 0]);
    $this->order = ProcurementRequest::factory()->create(['status' => 'ordered', 'purchase_order_no' => 'PO-DEL-1', 'created_by_user_id' => $this->recorder->id]);
    $this->line = $this->order->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => 'pc', 'quantity' => 10, 'estimated_unit_cost' => 20, 'specifications' => 'Must match PO and TOR.']);
});
function shipmentIntake(array $overrides = []): array
{
    return ['procurement_request_id' => test()->order->id, 'idempotency_key' => (string) Str::uuid(), 'supplier_name' => 'Supplier One', 'invoice_number' => 'INV-1', 'delivery_reference' => 'DR-'.Str::uuid(),
        'received_at' => '2026-10-10T09:30', 'receiving_department' => 'Operations', 'order_document_basis' => 'PO and TOR verified; NOA/NTP not applicable to this order.',
        'notification_reference' => 'SMS-LOG-1', 'packaging_findings' => 'Packaging intact; quantities counted.', 'attested' => true,
        'lines' => [['line_id' => test()->line->id, 'quantity_delivered' => 4, 'unit_cost' => 25, 'expiration_date' => '2027-10-01']], ...$overrides];
}
function receiveShipment(array $overrides = []): ProcurementDelivery
{
    test()->actingAs(test()->recorder)->post(route('inventory.deliveries.store'), shipmentIntake($overrides))->assertRedirect()->assertSessionHasNoErrors();

    return ProcurementDelivery::query()->latest('id')->firstOrFail();
}
function shipmentInspection(ProcurementDelivery $document, int $accepted = 3, array $overrides = []): array
{
    $people = HrisReference::factory()->count(4)->create();

    return ['action' => 'inspect', 'remarks' => 'Jointly counted and inspected against PO/TOR.', 'attested' => true, 'inspection_reference' => 'INSPECT-1', 'supplier_notification_reference' => 'NOTIFY-SUPPLIER-1',
        'evaluation_date' => '2026-10-12', 'order_reference_date' => '2026-10-01', 'invoice_date' => '2026-10-10', 'delivery_receipt_date' => '2026-10-10',
        'inspection_site' => 'Main warehouse', 'evaluation_findings' => 'Conforming quantities identified; damaged packaging on the remainder.', 'evaluation_comments' => 'Full quantity checked without sampling.',
        'evaluator_reference_id' => $people[0]->id, 'inspection_chair_reference_id' => $people[1]->id, 'inspection_vice_reference_id' => $people[2]->id, 'inspection_end_user_reference_id' => $people[3]->id,
        'evaluator_signature_reference' => 'EVALUATOR-SIGNED-001', 'evaluation_chair_signature_reference' => 'IER-CHAIR-SIGNED-001',
        'lines' => $document->lines()->get()->map(fn ($line) => ['line_id' => $line->id, 'quantity_accepted' => $accepted, 'condition' => 'conforming', 'disposition' => 'warehouse', 'storage_location' => 'Main stockroom A1', 'findings' => 'Accepted quantities meet specifications; rejected remainder segregated.', 'nonconformities' => $accepted < $line->quantity_delivered ? ['packaging'] : []])->all(), ...$overrides];
}
function shipmentReturnAuthorization(array $overrides = []): array
{
    $people = HrisReference::factory()->count(4)->create();

    return ['action' => 'authorize_supplier_return', 'remarks' => 'All four signed notice references verified.', 'attested' => true,
        'return_notice_date' => '2026-10-12', 'return_purpose' => 'Warehouse supplies', 'return_reason' => 'Damaged packaging on rejected quantities.', 'return_action_required' => 'Replace rejected quantities with conforming materials.',
        'return_preparer_reference_id' => $people[0]->id, 'return_end_user_reference_id' => $people[1]->id, 'return_procurement_head_reference_id' => $people[2]->id, 'return_chair_reference_id' => $people[3]->id,
        'return_preparer_signature_reference' => 'SUPPLY-SIGNED', 'return_end_user_signature_reference' => 'ENDUSER-SIGNED', 'return_procurement_head_signature_reference' => 'PROCUREMENT-SIGNED', 'return_chair_signature_reference' => 'RETURN-CHAIR-SIGNED', ...$overrides];
}
function inspectShipment(ProcurementDelivery $document, int $accepted = 3, array $overrides = []): void
{
    test()->actingAs(test()->inspector)->patch(route('inventory.deliveries.transition', $document), shipmentInspection($document, $accepted, $overrides))->assertRedirect()->assertSessionHasNoErrors();
}
function acceptShipment(ProcurementDelivery $document, ?User $actor = null): TestResponse
{
    return test()->actingAs($actor ?? test()->acceptor)->patch(route('inventory.deliveries.transition', $document), ['action' => 'accept', 'remarks' => 'Completed signatures and receiving approvals verified.', 'signed_acceptance_reference' => 'SIGNED-IAR-RR-1', 'attested' => true]);
}
test('delivery workspaces authorize managers and show ordered PO lines and immutable source snapshots', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->get(route('inventory.deliveries.index'))->assertForbidden();
    $this->post(route('inventory.deliveries.store'), shipmentIntake())->assertForbidden();
    $this->actingAs($this->recorder)->get(route('inventory.deliveries.create'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Deliveries/Create')->has('orders', 1)->where('orders.0.lines.0.available_quantity', 10));
    $document = receiveShipment();
    $this->get(route('inventory.deliveries.index'))->assertInertia(fn (Assert $page) => $page->component('Inventory/Deliveries/Index')->has('documents.data', 1));
    $this->get(route('inventory.deliveries.show', $document))->assertInertia(fn (Assert $page) => $page->component('Inventory/Deliveries/Show')->where('allowedActions', ['postpone', 'cancel'])->has('document.lines', 1));
    $this->actingAs($employee)->get(route('inventory.deliveries.print', [$document, 'iar']))->assertForbidden();
    $this->patch(route('inventory.deliveries.transition', $document), ['action' => 'cancel', 'remarks' => 'Unauthorized', 'attested' => true])->assertForbidden();
});
test('partial acceptance posts only inspected quantities at actual cost and preserves separate receipt and posting dates', function () {
    $document = receiveShipment();
    expect($this->item->fresh()->quantity)->toBe(0)->and($this->line->fresh()->quantity_received)->toBe(0);
    inspectShipment($document);
    expect($this->item->fresh()->quantity)->toBe(0)->and($document->fresh()->status)->toBe('inspected');
    acceptShipment($document)->assertSessionHasNoErrors();
    $batch = $document->lines()->first()->batch;
    expect($batch->quantity_in)->toBe(3)->and($batch->unit_cost)->toBe('25.00')->and($batch->expiration_date->toDateString())->toBe('2027-10-01')->and($batch->received_at->toDateString())->toBe('2026-10-12')
        ->and($document->fresh()->received_at->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-10-10 09:30')
        ->and($this->item->fresh()->quantity)->toBe(3)->and($this->line->fresh()->quantity_received)->toBe(3)->and($this->order->fresh()->status)->toBe('delivered')
        ->and($document->fresh()->supplier_return_no)->not->toBeNull();
    acceptShipment($document)->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(3)->and($this->item->batches()->count())->toBe(1);
});
test('replacement shipments have independent costs and complete the PO only after full cumulative acceptance', function () {
    $first = receiveShipment();
    inspectShipment($first);
    acceptShipment($first)->assertSessionHasNoErrors();
    $second = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 7, 'unit_cost' => 30]]]);
    inspectShipment($second, 7);
    acceptShipment($second)->assertSessionHasNoErrors();
    expect($this->item->fresh()->quantity)->toBe(10)->and($this->line->fresh()->quantity_received)->toBe(10)->and($this->order->fresh()->status)->toBe('accepted')
        ->and($this->item->batches()->orderBy('batch_number')->pluck('unit_cost')->all())->toBe(['25.00', '30.00']);
});
test('pending accepted and cancelled quantities enforce the PO commitment cap', function () {
    $document = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 10, 'unit_cost' => 25]]]);
    $this->post(route('inventory.deliveries.store'), shipmentIntake())->assertSessionHasErrors('lines');
    inspectShipment($document, 6);
    $other = receiveShipment();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $other), ['action' => 'cancel', 'remarks' => 'Recorded shipment correction', 'attested' => true])->assertSessionHasNoErrors();
    receiveShipment();
    $this->post(route('inventory.deliveries.store'), shipmentIntake())->assertSessionHasErrors('lines');
});
test('source documents and idempotency prevent duplicate shipments', function () {
    $data = shipmentIntake();
    $this->actingAs($this->recorder)->post(route('inventory.deliveries.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('inventory.deliveries.store'), $data)->assertSessionHasNoErrors();
    expect(ProcurementDelivery::query()->count())->toBe(1)->and(ProcurementDeliveryAction::query()->count())->toBe(1);
    $this->post(route('inventory.deliveries.store'), [...$data, 'supplier_name' => 'Changed supplier'])->assertSessionHasErrors('idempotency_key');
    $this->post(route('inventory.deliveries.store'), [...$data, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('delivery_reference');
});
test('independent inspection and acceptance cannot be skipped or performed by the recorder or inspector', function () {
    $document = receiveShipment();
    acceptShipment($document)->assertSessionHasErrors('action');
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), shipmentInspection($document))->assertSessionHasErrors('action');
    inspectShipment($document);
    acceptShipment($document, $this->inspector)->assertSessionHasErrors('action');
    acceptShipment($document, $this->recorder)->assertSessionHasErrors('action');
    acceptShipment($document)->assertSessionHasNoErrors();
});
test('inspection rejects damaged expired excess foreign lines and missing location or calibration evidence', function (string $failure) {
    $overrides = $failure === 'expired' ? ['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 4, 'unit_cost' => 25, 'expiration_date' => '2026-10-11']]] : [];
    $document = receiveShipment($overrides);
    $data = shipmentInspection($document);
    if ($failure === 'damaged') {
        $data['lines'][0]['condition'] = 'damaged';
    }
    if ($failure === 'excess') {
        $data['lines'][0]['quantity_accepted'] = 5;
    }
    if ($failure === 'foreign') {
        $data['lines'][0]['line_id'] += 999;
    }
    if ($failure === 'location') {
        $data['lines'][0]['storage_location'] = '';
    }
    if ($failure === 'calibration') {
        $data['lines'][0]['disposition'] = 'calibration';
    }
    $this->actingAs($this->inspector)->patch(route('inventory.deliveries.transition', $document), $data)->assertSessionHasErrors('lines');
    expect($document->fresh()->status)->toBe('received')->and($document->lines()->first()->quantity_accepted)->toBeNull()->and($this->item->fresh()->quantity)->toBe(0);
})->with(['damaged', 'expired', 'excess', 'foreign', 'location', 'calibration']);
test('inspection discrepancies require notification and wholly rejected deliveries create no stock or property', function () {
    $document = receiveShipment();
    $this->actingAs($this->inspector)->patch(route('inventory.deliveries.transition', $document), shipmentInspection($document, 0, ['supplier_notification_reference' => null]))->assertSessionHasErrors('supplier_notification_reference');
    inspectShipment($document, 0);
    expect($document->fresh()->status)->toBe('rejected')->and($document->fresh()->inspection_acceptance_no)->toBeNull()->and($this->item->fresh()->quantity)->toBe(0);
    acceptShipment($document)->assertSessionHasErrors('action');
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertOk()->assertSee('Return to Supplier Notice')->assertSee('Supplier One');
    receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 10, 'unit_cost' => 25]]]);
});
test('supplier handover records rejected quantities once without deducting warehouse stock', function () {
    $document = receiveShipment();
    inspectShipment($document);
    acceptShipment($document)->assertSessionHasNoErrors();
    $this->patch(route('inventory.deliveries.transition', $document), shipmentReturnAuthorization())->assertSessionHasNoErrors();
    $data = ['action' => 'record_supplier_return', 'remarks' => 'Rejected quantity handed to supplier for replacement.', 'return_reference' => 'SUPPLIER-ACK-1', 'returned_at' => '2026-10-12', 'attested' => true];
    $this->patch(route('inventory.deliveries.transition', $document), [...$data, 'returned_at' => '2026-10-09'])->assertSessionHasErrors('returned_at');
    $this->patch(route('inventory.deliveries.transition', $document), $data)->assertSessionHasNoErrors();
    $this->patch(route('inventory.deliveries.transition', $document), $data)->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(3)->and($document->fresh()->supplier_returned_at)->not->toBeNull();
});
test('stock count cutoff rolls back acceptance and posting until released', function () {
    $document = receiveShipment();
    inspectShipment($document);
    $count = app(InventoryCountService::class)->create(['title' => 'Count', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'MEMO-1', 'team_details' => 'Authorized team'], $this->recorder);
    $this->actingAs($this->recorder)->patch(route('inventory.counts.transition', $count), ['action' => 'start', 'attested' => true])->assertSessionHasNoErrors();
    acceptShipment($document)->assertSessionHasErrors('quantity');
    expect($document->fresh()->status)->toBe('inspected')->and($this->line->fresh()->quantity_received)->toBe(0)->and($this->item->fresh()->quantity)->toBe(0);
    $this->actingAs($this->recorder)->patch(route('inventory.counts.transition', $count), ['action' => 'cancel', 'remarks' => 'Rescheduled', 'attested' => true])->assertSessionHasNoErrors();
    acceptShipment($document)->assertSessionHasNoErrors();
});
test('new item acceptance creates the catalog entry while direct use stays outside warehouse stock', function (string $destination) {
    $this->line->update(['inventory_item_id' => null, 'series_category_id' => $this->item->series_category_id, 'item_name' => 'New delivered item']);
    $document = receiveShipment();
    $data = shipmentInspection($document, 4);
    $data['lines'][0]['disposition'] = $destination;
    $data['lines'][0]['disposition_reference'] = 'SIGNED-ENDUSER-OR-CALIBRATION-1';
    inspectShipment($document, 4, ['lines' => $data['lines']]);
    acceptShipment($document)->assertSessionHasNoErrors();
    expect($this->line->fresh()->quantity_received)->toBe(4);
    if ($destination === 'direct_use') {
        expect(InventoryItem::query()->where('name', 'New delivered item')->exists())->toBeFalse();
    } else {
        expect(InventoryItem::query()->where('name', 'New delivered item')->sole()->quantity)->toBe(4);
    }
})->with(['warehouse', 'direct_use', 'calibration']);
test('property acceptance supports partial shipments and classifies each accepted unit at its actual cost', function () {
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create(['inventory_asset_category_id' => $category->getKey()]);
    $this->line->update(['inventory_item_id' => null, 'item_kind' => 'property', 'asset_category_id' => $category->getKey(), 'asset_subcategory_id' => $subcategory->getKey(), 'quantity' => 2]);
    $first = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 1, 'unit_cost' => 49000]]]);
    inspectShipment($first, 1);
    acceptShipment($first)->assertSessionHasNoErrors();
    $second = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 1, 'unit_cost' => 50000]]]);
    inspectShipment($second, 1);
    acceptShipment($second)->assertSessionHasNoErrors();
    $assets = InventoryAsset::query()->orderBy('inventory_asset_id')->get();
    expect($assets)->toHaveCount(2)->and($assets[0]->accounting_classification)->toBe(AssetAccountingClassification::SemiExpendable)->and($assets[1]->accounting_classification)->toBe(AssetAccountingClassification::Ppe)
        ->and($assets->pluck('property_number')->unique())->toHaveCount(2)->and($first->lines()->first()->asset_ids)->toHaveCount(1)->and($this->order->fresh()->status)->toBe('accepted')->and($this->item->fresh()->quantity)->toBe(0);
});
test('invalid order kinds foreign lines missing receipt evidence and future receipt dates fail safely', function () {
    $this->actingAs($this->recorder)->post(route('inventory.deliveries.store'), [])->assertSessionHasErrors(['procurement_request_id', 'supplier_name', 'invoice_number', 'received_at', 'notification_reference', 'packaging_findings', 'order_document_basis', 'lines']);
    $this->post(route('inventory.deliveries.store'), shipmentIntake(['received_at' => '2099-01-01T10:00']))->assertSessionHasErrors('received_at');
    $this->post(route('inventory.deliveries.store'), shipmentIntake(['lines' => [['line_id' => 99999, 'quantity_delivered' => 1, 'unit_cost' => 20]]]))->assertSessionHasErrors('lines');
    $this->order->update(['document_kind' => 'JO']);
    $this->post(route('inventory.deliveries.store'), shipmentIntake())->assertSessionHasErrors('procurement_request_id');
    expect(ProcurementDelivery::query()->count())->toBe(0);
});
test('legacy acceptance endpoints cannot bypass modern shipment inspections', function () {
    $document = receiveShipment();
    $this->patch(route('inventory.procurement.transition', $this->order), ['action' => 'accept', 'inspection_acceptance_no' => 'FAKE-IAR', 'attested' => true])->assertSessionHasErrors('action');
    $this->patch(route('inventory.procurement.transition', $this->order), ['action' => 'record_delivery', 'delivery_reference' => 'FAKE-DR', 'received_at' => '2026-10-12', 'actual_unit_cost' => 20, 'attested' => true])->assertSessionHasErrors('action');
    expect($this->item->fresh()->quantity)->toBe(0)->and($document->fresh()->status)->toBe('received');
});
test('signed acceptance evidence and independent immutable decisions support print and endorsement records', function () {
    $document = receiveShipment();
    foreach (['iar', 'receiving-report'] as $type) {
        $this->get(route('inventory.deliveries.print', [$document, $type]))->assertOk()->assertSee('WORKING DOCUMENT');
    }
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertNotFound();
    inspectShipment($document);
    $this->actingAs($this->acceptor)->patch(route('inventory.deliveries.transition', $document), ['action' => 'accept', 'remarks' => 'Missing signature evidence', 'attested' => true])->assertSessionHasErrors('signed_acceptance_reference');
    acceptShipment($document)->assertSessionHasNoErrors();
    $data = ['action' => 'endorse', 'remarks' => 'Completed pre-audit and forwarded for payment processing.', 'endorsement_reference' => 'ACCOUNTING-1', 'endorsement_recipient' => 'Accounting Unit', 'attested' => true];
    $this->patch(route('inventory.deliveries.transition', $document), $data)->assertSessionHasNoErrors();
    $this->patch(route('inventory.deliveries.transition', $document), $data)->assertSessionHasErrors('action');
    $this->get(route('inventory.deliveries.print', [$document, 'iar']))->assertOk()->assertDontSee('WORKING DOCUMENT')->assertSee('ACCOUNTING-1')->assertSee('SIGNED-IAR-RR-1');
    $entry = $document->actions()->first();
    expect(fn () => $entry->update(['remarks' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('accepted deliveries reassess linked supply shortages after each partial shipment', function () {
    $supply = SupplyRequest::factory()->create(['status' => 'awaiting_replenishment']);
    $supplyLine = $supply->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name, 'unit_of_measure' => 'pc', 'quantity_requested' => 10, 'quantity_approved' => 10, 'quantity_reserved' => 0]);
    $this->order->update(['supply_request_id' => $supply->id]);
    $this->line->update(['supply_request_line_id' => $supplyLine->id]);
    $first = receiveShipment();
    inspectShipment($first);
    acceptShipment($first)->assertSessionHasNoErrors();
    expect($supply->fresh()->status)->toBe('awaiting_replenishment')->and($supplyLine->fresh()->quantity_reserved)->toBe(3);
    $second = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 7, 'unit_cost' => 30]]]);
    $inspection = shipmentInspection($second, 7);
    $inspection['lines'][0]['disposition'] = 'direct_use';
    $inspection['lines'][0]['disposition_reference'] = 'ENDUSER-1';
    $this->actingAs($this->inspector)->patch(route('inventory.deliveries.transition', $second), $inspection)->assertSessionHasErrors('lines');
    inspectShipment($second, 7);
    acceptShipment($second)->assertSessionHasNoErrors();
    expect($supply->fresh()->status)->toBe('ready_for_release')->and($supplyLine->fresh()->quantity_reserved)->toBe(10)->and($this->order->fresh()->status)->toBe('accepted');
});

test('IAR register includes direct use and property without duplicating warehouse receipts', function () {
    $first = receiveShipment();
    inspectShipment($first);
    acceptShipment($first)->assertSessionHasNoErrors();
    $second = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 2, 'unit_cost' => 30]]]);
    $inspection = shipmentInspection($second, 2);
    $inspection['lines'][0]['disposition'] = 'direct_use';
    $inspection['lines'][0]['disposition_reference'] = 'ENDUSER-ACK-1';
    inspectShipment($second, 2, ['lines' => $inspection['lines']]);
    acceptShipment($second)->assertSessionHasNoErrors();
    $filters = ['as_of' => '2026-10-12', 'period' => 'monthly', 'period_label' => 'October', 'year' => 2026, 'from' => '2026-10-12', 'to' => '2026-10-12', 'fund_cluster' => '01', 'custodian' => null];
    $reports = app(InventoryReportService::class);
    expect($reports->printable('iar', $filters)['rows'])->toHaveCount(2)
        ->and($reports->printable('receipt-register', $filters)['rows'])->toHaveCount(1);
    $physicalPeriod = [...$filters, 'from' => '2026-10-10', 'to' => '2026-10-10'];
    expect($reports->printable('delivery-register', $physicalPeriod)['rows'])->toHaveCount(2)
        ->and($reports->printable('supplier-return-register', $physicalPeriod)['rows'])->toHaveCount(1)
        ->and($reports->printable('iar', $physicalPeriod)['rows'])->toHaveCount(0);
    expect($reports->printable('iar', [...$filters, 'as_of' => '2026-10-11'])['rows'])->toHaveCount(0);
});

test('blocked stock acceptance rolls back property registration in the same mixed shipment', function () {
    $category = InventoryAssetCategory::factory()->create();
    $subcategory = InventoryAssetSubcategory::factory()->create(['inventory_asset_category_id' => $category->getKey()]);
    $property = $this->order->lines()->create(['item_kind' => 'property', 'asset_category_id' => $category->getKey(), 'asset_subcategory_id' => $subcategory->getKey(), 'item_name' => 'Testing equipment', 'unit_of_measure' => 'unit', 'quantity' => 1, 'estimated_unit_cost' => 50000]);
    $document = receiveShipment(['lines' => [['line_id' => $property->id, 'quantity_delivered' => 1, 'unit_cost' => 50000], ['line_id' => $this->line->id, 'quantity_delivered' => 1, 'unit_cost' => 20]]]);
    inspectShipment($document, 1);
    $count = app(InventoryCountService::class)->create(['title' => 'Mixed shipment count', 'scheduled_date' => '2026-10-12', 'memorandum_reference' => 'MEMO-2', 'team_details' => 'Authorized team'], $this->recorder);
    $this->actingAs($this->recorder)->patch(route('inventory.counts.transition', $count), ['action' => 'start', 'attested' => true])->assertSessionHasNoErrors();
    acceptShipment($document)->assertSessionHasErrors('quantity');
    expect(InventoryAsset::query()->count())->toBe(0)->and($document->lines()->whereNotNull('asset_ids')->count())->toBe(0)->and($property->fresh()->quantity_received)->toBe(0)->and($this->item->fresh()->quantity)->toBe(0);
});

test('archived items and expiry between inspection and acceptance prevent stock posting', function (string $failure) {
    $document = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 4, 'unit_cost' => 25, 'expiration_date' => '2026-10-13']]]);
    inspectShipment($document);
    if ($failure === 'archived') {
        $this->item->delete();
    } else {
        $this->travelTo(now()->addDays(3));
    }
    acceptShipment($document)->assertSessionHasErrors('action');
    expect($document->fresh()->status)->toBe('inspected')->and($this->line->fresh()->quantity_received)->toBe(0);
})->with(['archived', 'expired']);

function shipmentPostponement(array $overrides = []): array
{
    $people = HrisReference::factory()->count(2)->create();

    return ['action' => 'postpone', 'remarks' => 'Inspectorate Team has no quorum. Supplier informed.', 'attested' => true,
        'notice_submission_key' => (string) Str::uuid(), 'notice_date' => '2026-10-12', 'original_inspection_at' => '2026-10-12T13:00', 'proposed_inspection_at' => '2026-10-14T14:00',
        'original_site' => 'Main warehouse', 'proposed_site' => 'Inspection bay', 'postponement_reason' => 'no_quorum', 'supplier_contact_name' => 'Supplier representative',
        'supplier_address' => 'Supplier office address', 'lot_reference' => 'LOT-001', 'chair_reference_id' => $people[0]->id, 'authority_reference_id' => $people[1]->id,
        'chair_signature_reference' => 'SIGNED-CHAIR-NOTICE', 'authority_signature_reference' => 'SIGNED-GM-NOTICE', 'notice_sent_reference' => 'SUPPLIER-NOTIFIED-001', ...$overrides];
}
test('inspection postponement preserves signed notice without stock or commitment changes', function () {
    $document = receiveShipment();
    $input = shipmentPostponement();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasNoErrors();
    $notice = $document->actions()->where('action', 'postpone')->sole();
    expect($document->fresh()->status)->toBe('received')->and($this->item->fresh()->quantity)->toBe(0)->and($this->line->fresh()->quantity_received)->toBe(0)
        ->and($document->lines()->sole()->quantity_committed)->toBe(4)
        ->and(app(ProcurementDeliveryService::class)->availableQuantity($this->line))->toBe(6)
        ->and($notice->metadata['notice_no'])->toBe('NOP-2026-000001-001');
    $this->get(route('inventory.deliveries.postponements.print', [$document, $notice]))->assertSuccessful()->assertSee('NOTICE OF POSTPONEMENT OF DELIVERY INSPECTION')->assertSee('2026-10-14 14:00')->assertSee('SIGNED-CHAIR-NOTICE')->assertSee('SUPPLIER-NOTIFIED-001');
    $chair = HrisReference::findOrFail($input['chair_reference_id']);
    $originalName = $chair->name;
    $chair->update(['name' => 'Renamed employee']);
    $this->get(route('inventory.deliveries.postponements.print', [$document, $notice]))->assertSee($originalName)->assertDontSee('Renamed employee');
    $this->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasNoErrors();
    expect($document->actions()->where('action', 'postpone')->count())->toBe(1);
    $this->patch(route('inventory.deliveries.transition', $document), [...$input, 'remarks' => 'Changed notice using same key'])->assertSessionHasErrors('notice_submission_key');
    expect(fn () => $notice->update(['remarks' => 'Overwrite signed notice']))->toThrow(LogicException::class);
});
test('resumed inspection requires confirmation while normal inspections remain unchanged', function () {
    $document = receiveShipment();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), shipmentPostponement())->assertSessionHasNoErrors();
    $this->actingAs($this->inspector)->patch(route('inventory.deliveries.transition', $document), shipmentInspection($document))->assertSessionHasErrors('resumption_reference');
    expect($document->fresh()->status)->toBe('received')->and($document->lines()->sole()->quantity_accepted)->toBeNull();
    inspectShipment($document, 4, ['resumption_reference' => 'CONFIRMED-SUPPLIER-AND-QUORUM']);
    expect($document->fresh()->inspection['resumption_reference'])->toBe('CONFIRMED-SUPPLIER-AND-QUORUM')->and($this->item->fresh()->quantity)->toBe(0);
    acceptShipment($document)->assertSessionHasNoErrors();
    expect($this->item->fresh()->quantity)->toBe(4);
});
test('postponement validation requires signatures and a valid later schedule', function (string $field, mixed $value) {
    $document = receiveShipment();
    $input = shipmentPostponement([$field => $value]);
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasErrors();
    expect($document->actions()->where('action', 'postpone')->count())->toBe(0)->and($document->fresh()->status)->toBe('received')->and($this->item->fresh()->quantity)->toBe(0);
})->with([['chair_signature_reference', null], ['authority_signature_reference', null], ['notice_sent_reference', null], ['chair_reference_id', 99999], ['notice_date', '2026-10-13'], ['proposed_inspection_at', '2026-10-12T12:00'], ['original_inspection_at', '2026-10-09T10:00'], ['postponement_reason', 'arbitrary']]);
test('repeated postponements retain every letter and require the latest proposed schedule', function () {
    $document = receiveShipment();
    $first = shipmentPostponement();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $first)->assertSessionHasNoErrors();
    $second = shipmentPostponement(['original_inspection_at' => '2026-10-14T14:00', 'original_site' => 'Inspection bay', 'proposed_inspection_at' => '2026-10-16T10:00', 'proposed_site' => 'Technical office']);
    $this->patch(route('inventory.deliveries.transition', $document), $second)->assertSessionHasNoErrors();
    $notices = $document->actions()->where('action', 'postpone')->orderBy('id')->get();
    expect($notices)->toHaveCount(2)->and($notices[1]->metadata['notice_no'])->toBe('NOP-2026-000001-002');
    $this->get(route('inventory.deliveries.postponements.print', [$document, $notices[0]]))->assertSee('2026-10-14 14:00')->assertDontSee('2026-10-16 10:00');
    $this->patch(route('inventory.deliveries.transition', $document), shipmentPostponement())->assertSessionHasErrors('original_inspection_at');
    expect($document->actions()->where('action', 'postpone')->count())->toBe(2);
});
test('postponement printing denies another shipment non-notice decisions and employees', function () {
    $first = receiveShipment();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $first), shipmentPostponement())->assertSessionHasNoErrors();
    $notice = $first->actions()->where('action', 'postpone')->sole();
    $second = receiveShipment();
    $this->get(route('inventory.deliveries.postponements.print', [$second, $notice]))->assertNotFound();
    $this->get(route('inventory.deliveries.postponements.print', [$first, $first->actions()->where('action', 'receive')->sole()]))->assertNotFound();
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.deliveries.postponements.print', [$first, $notice]))->assertForbidden();
    $this->patch(route('inventory.deliveries.transition', $first), shipmentPostponement())->assertForbidden();
});
test('inspected cancelled and accepted shipments cannot gain a new postponement', function (string $state) {
    $document = receiveShipment();
    if ($state === 'cancelled') {
        $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), ['action' => 'cancel', 'remarks' => 'Cancelled before inspection.', 'attested' => true])->assertSessionHasNoErrors();
    } else {
        inspectShipment($document, 4);
        if ($state === 'accepted') {
            acceptShipment($document)->assertSessionHasNoErrors();
        }
    }
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), shipmentPostponement())->assertSessionHasErrors('action');
    expect($document->actions()->where('action', 'postpone')->count())->toBe(0);
})->with(['inspected', 'cancelled', 'accepted']);

test('evaluation report preserves rejected shipment dates checklist and named signatories without acceptance', function () {
    $document = receiveShipment();
    $this->actingAs($this->inspector)->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertNotFound();
    $input = shipmentInspection($document, 0);
    $this->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasNoErrors();
    $this->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertSuccessful()->assertSee('INSPECTION & EVALUATION REPORT')->assertSee('IER-2026-000001')->assertSee('EVALUATOR-SIGNED-001')->assertSee('IER-CHAIR-SIGNED-001')->assertSee('[X] Packing / packaging')->assertSee('2026-10-01');
    expect($document->fresh()->status)->toBe('rejected')->and($this->item->fresh()->quantity)->toBe(0)->and($document->fresh()->inspection_acceptance_no)->toBeNull();
    $name = HrisReference::findOrFail($input['evaluator_reference_id'])->name;
    HrisReference::findOrFail($input['evaluator_reference_id'])->update(['name' => 'Updated evaluator name']);
    $document->update(['supplier_name' => 'Changed supplier', 'inspection' => ['evaluation_no' => 'TAMPERED-HEADER']]);
    $document->lines()->sole()->update(['item_name' => 'Changed item details', 'findings' => 'Changed findings']);
    $this->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertSee('Supplier One')->assertSee($this->item->name)->assertSee($name)->assertDontSee('Changed supplier')->assertDontSee('Updated evaluator name')->assertDontSee('TAMPERED-HEADER')->assertDontSee('Changed findings');
});
test('evaluation rejects missing signatures unrecorded rejection reasons and invalid dates atomically', function (string $problem) {
    $document = receiveShipment();
    $input = shipmentInspection($document);
    if ($problem === 'signature') {
        unset($input['evaluator_signature_reference']);
    }
    if ($problem === 'chair') {
        unset($input['evaluation_chair_signature_reference']);
    }
    if ($problem === 'date') {
        $input['evaluation_date'] = '2026-10-09';
    }
    if ($problem === 'future_invoice') {
        $input['invoice_date'] = '2026-10-13';
    }
    if ($problem === 'checklist') {
        $input['lines'][0]['nonconformities'] = [];
    }
    if ($problem === 'category') {
        $input['lines'][0]['nonconformities'] = ['unknown_category'];
    }
    $this->actingAs($this->inspector)->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasErrors();
    expect($document->fresh()->status)->toBe('received')->and($document->lines()->sole()->quantity_accepted)->toBeNull()->and($document->actions()->where('action', 'inspect')->count())->toBe(0)->and($this->item->fresh()->quantity)->toBe(0);
})->with(['signature', 'chair', 'date', 'future_invoice', 'checklist', 'category']);
test('supplier handover requires four-signatory authorization and retains conforming stock', function () {
    $document = receiveShipment();
    inspectShipment($document);
    $handover = ['action' => 'record_supplier_return', 'remarks' => 'Supplier handover checked.', 'returned_at' => '2026-10-12', 'return_reference' => 'SUPPLIER-ACK', 'attested' => true];
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $handover)->assertSessionHasErrors('action');
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertSee('Four-signatory authorization evidence has not been recorded');
    $input = shipmentReturnAuthorization();
    $this->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasNoErrors();
    expect($document->fresh()->status)->toBe('inspected')->and($this->item->fresh()->quantity)->toBe(0)->and($document->fresh()->supplier_returned_at)->toBeNull();
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertSee('PROCUREMENT-SIGNED')->assertSee('SUPPLY-SIGNED')->assertSee('ENDUSER-SIGNED')->assertSee('RETURN-CHAIR-SIGNED')->assertSee('Replace rejected quantities');
    acceptShipment($document)->assertSessionHasNoErrors();
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $handover)->assertSessionHasNoErrors();
    expect($this->item->fresh()->quantity)->toBe(3)->and($this->line->fresh()->quantity_received)->toBe(3);
    $this->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasErrors('action');
    $sourceName = $this->item->name;
    $document->update(['supplier_name' => 'Different supplier']);
    $document->lines()->sole()->update(['item_name' => 'Different product', 'quantity_delivered' => 100]);
    HrisReference::findOrFail($input['return_procurement_head_reference_id'])->update(['name' => 'Different procurement head']);
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertSee('Supplier One')->assertSee($sourceName)->assertSee('SUPPLIER-ACK')->assertDontSee('Different supplier')->assertDontSee('Different product')->assertDontSee('Different procurement head');
});
test('supplier return authorization requires every signed role and cannot precede evaluation', function (string $field) {
    $document = receiveShipment();
    inspectShipment($document);
    $input = shipmentReturnAuthorization();
    if ($field === 'return_notice_date') {
        $input[$field] = '2026-10-11';
    } else {
        unset($input[$field]);
    }
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), $input)->assertSessionHasErrors($field);
    expect($document->actions()->where('action', 'authorize_supplier_return')->count())->toBe(0)->and($document->fresh()->supplier_returned_at)->toBeNull()->and($this->item->fresh()->quantity)->toBe(0);
})->with(['return_preparer_signature_reference', 'return_end_user_signature_reference', 'return_procurement_head_signature_reference', 'return_chair_signature_reference', 'return_notice_date']);
test('legacy inspection prints explicit evaluation gaps without invented signed evidence', function () {
    $document = receiveShipment();
    $document->update(['status' => 'inspected', 'inspection' => ['inspection_reference' => 'LEGACY-SIGNED-INSPECTION', 'chairperson_name' => 'Legacy Chair']]);
    $document->actions()->create(['actor_user_id' => $this->inspector->id, 'actor_name' => $this->inspector->name, 'action' => 'inspect', 'from_status' => 'received', 'to_status' => 'inspected', 'remarks' => 'Legacy recorded findings.', 'metadata' => ['inspection_reference' => 'LEGACY-SIGNED-INSPECTION', 'chairperson_name' => 'Legacy Chair']]);
    $this->actingAs($this->recorder)->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertSuccessful()->assertSee('LEGACY INSPECTION')->assertSee('Not recorded (legacy)')->assertSee('Legacy Chair')->assertDontSee('EVALUATOR-SIGNED-001');
});
test('employees cannot authorize supplier return or print evaluation and fully conforming deliveries have no return notice', function () {
    $document = receiveShipment();
    inspectShipment($document, 4);
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), shipmentReturnAuthorization())->assertSessionHasErrors('action');
    $this->get(route('inventory.deliveries.print', [$document, 'supplier-return']))->assertNotFound();
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertForbidden();
    $this->patch(route('inventory.deliveries.transition', $document), shipmentReturnAuthorization())->assertForbidden();
});

test('signed return notice blocks handover after delivery facts change and before its authorization date', function () {
    $document = receiveShipment();
    inspectShipment($document);
    $this->actingAs($this->recorder)->patch(route('inventory.deliveries.transition', $document), shipmentReturnAuthorization())->assertSessionHasNoErrors();
    $handover = ['action' => 'record_supplier_return', 'remarks' => 'Physical handover checked.', 'return_reference' => 'SUPPLIER-ACK', 'returned_at' => '2026-10-11', 'attested' => true];
    $this->patch(route('inventory.deliveries.transition', $document), $handover)->assertSessionHasErrors('returned_at');
    $document->lines()->sole()->update(['findings' => 'Changed after authorization.']);
    $this->patch(route('inventory.deliveries.transition', $document), [...$handover, 'returned_at' => '2026-10-12'])->assertSessionHasErrors('return_reference');
    expect($document->fresh()->supplier_returned_at)->toBeNull()->and($this->item->fresh()->quantity)->toBe(0);
});
test('multiple inspected lines may record the same non-conformance category without losing line identity', function () {
    $second = $this->order->lines()->create(['inventory_item_id' => $this->item->getKey(), 'item_name' => 'Second delivery material', 'unit_of_measure' => 'pc', 'quantity' => 4, 'estimated_unit_cost' => 25, 'specifications' => 'Second line specifications.']);
    $document = receiveShipment(['lines' => [['line_id' => $this->line->id, 'quantity_delivered' => 4, 'unit_cost' => 25], ['line_id' => $second->id, 'quantity_delivered' => 4, 'unit_cost' => 25]]]);
    inspectShipment($document, 3);
    $this->actingAs($this->recorder)->get(route('inventory.deliveries.print', [$document, 'evaluation']))->assertSuccessful()->assertSee('Second delivery material')->assertSee('[X] Packing / packaging');
    $observations = $document->actions()->where('action', 'inspect')->sole()->metadata['lines'];
    expect($observations)->toHaveCount(2)->and($observations[0]['line_id'])->not->toBe($observations[1]['line_id']);
});
