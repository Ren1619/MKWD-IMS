<?php

use App\Models\DepartmentLiquidation;
use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryReturn;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->employee()->create();
    $this->manager = User::factory()->inventoryManager()->create();
    $this->admin = User::factory()->superAdmin()->create();
    $this->record = SupplyRequest::factory()->create([
        'requester_user_id' => $this->owner->id, 'requester_name' => 'Requesting Employee',
        'office_name' => 'Operations', 'purpose' => 'Repair water lines', 'status' => 'ready_for_release',
        'irf_no' => '11-12-007', 'irf_date' => '2026-11-12', 'responsibility_center_code' => 'RC-1',
        'issuance_details' => ['requested_by' => 'Requesting Employee', 'checked_by' => 'Division Head', 'approved_by' => 'Department Head'],
    ]);
    $this->item = InventoryItem::factory()->create(['name' => 'Repair supplies', 'stock_number' => 'ITEM-123']);
    $stock = app(InventoryStockService::class);
    $stock->stockIn($this->item, ['quantity' => 2, 'unit_cost' => 10.25]);
    $stock->stockIn($this->item, ['quantity' => 8, 'unit_cost' => 20.75]);
    $this->line = $this->record->lines()->create([
        'inventory_item_id' => $this->item->getKey(), 'item_name' => $this->item->name,
        'unit_of_measure' => 'pc', 'quantity_requested' => 4, 'quantity_approved' => 4, 'quantity_reserved' => 4,
    ]);
    $this->actingAs($this->manager)->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertSessionHasNoErrors();
    $this->slip = IssuanceSlip::query()->sole();
});

test('request documents contain the requested fields and frozen heads', function () {
    $response = $this->actingAs($this->owner)->get(route('inventory.requests.print', $this->record))
        ->assertOk()->assertSeeInOrder(['Department:', 'Operations', 'Date:', 'November 12, 2026', 'IRF No.:', '11-12-007', 'Purpose:', 'Repair water lines', 'Repair supplies', 'CHARGE CODE:', 'RC-1', 'Requisitioner', 'Requesting Employee', 'Checked By', 'Division Head', 'Approved By', 'Department Head']);
    $html = new DOMDocument;
    @$html->loadHTML($response->getContent());
    $fields = (new DOMXPath($html))->query('//div[contains(@class,"issuance-request-fields")]/span');
    expect($fields->length)->toBe(3)
        ->and($fields->item(0)->textContent)->toContain('Department:')
        ->and($fields->item(1)->textContent)->toContain('Date:')
        ->and($fields->item(2)->textContent)->toContain('IRF No.:');
    $response->assertSee('.fields.issuance-request-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));', false);
});

test('slips use actual release costs and selected employee positions', function () {
    $signer = HrisReference::factory()->create(['name' => 'Selected Employee', 'metadata' => ['position' => ['name' => 'Supply Officer']]]);
    $this->actingAs($this->admin)->get(route('inventory.issuance_slips.print', ['slip' => $this->slip, 'prepared_by' => $signer->id, 'checked_by' => $signer->id, 'approved_by' => $signer->id]))
        ->assertOk()->assertSee('IS No.:')->assertSee('IS Date:')->assertSee('Requesting Department:')
        ->assertSee('Linked IRF No.:')->assertDontSee('Linked ISF No.:')
        ->assertSee('Issued to (Requisitioner):')->assertSee('Repair water lines')->assertSee('ITEM-123')
        ->assertSee('CHARGE ACCOUNT')->assertSee('15.50')->assertSee('62.00')
        ->assertSeeInOrder(['Prepared By', 'Selected Employee', 'Supply Officer', 'Checked By', 'Selected Employee', 'Supply Officer', 'Approved By', 'Selected Employee', 'Supply Officer']);
    expect($this->item->fresh()->quantity)->toBe(6)->and($this->slip->fresh()->status)->toBe('pending');
});

test('later release slips do not include earlier release quantities or costs', function () {
    $this->line->update(['quantity_requested' => 10, 'quantity_approved' => 10, 'quantity_reserved' => 10]);
    $this->record->update(['status' => 'partially_released']);
    $this->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertSessionHasNoErrors();
    $second = $this->record->issuanceSlips()->latest('id')->first();
    $this->actingAs($this->admin)->get(route('inventory.issuance_slips.print', $second))->assertOk()->assertSee('124.50')->assertDontSee('62.00');
    $this->get(route('inventory.issuance_slips.print', $this->slip))->assertOk()->assertSee('62.00')->assertDontSee('124.50');
});

test('unrelated employees cannot print request documents or slips', function () {
    $this->actingAs(User::factory()->employee()->create());
    $this->get(route('inventory.requests.print', $this->record))->assertForbidden();
    $this->get(route('inventory.issuance_slips.print', $this->slip))->assertForbidden();
});

test('historical actors and requested for employees retain document access', function () {
    $actor = User::factory()->employee()->create();
    $this->record->actions()->create(['actor_user_id' => $actor->id, 'action' => 'approve', 'to_status' => 'approved', 'attestation' => 'Approved for official use.']);
    $reference = HrisReference::factory()->create();
    $this->record->update(['requester_reference_id' => $reference->id]);
    $recipient = User::factory()->employee()->create(['hris_reference_id' => $reference->id]);
    foreach ([$actor, $recipient] as $user) {
        $this->actingAs($user)->get(route('inventory.requests.print', $this->record))->assertOk();
        $this->get(route('inventory.issuance_slips.print', $this->slip))->assertForbidden();
    }
});

test('signatories must be active employee references and can be left blank', function () {
    $inactive = HrisReference::factory()->create(['is_active' => false]);
    $department = HrisReference::factory()->create(['type' => HrisReference::TYPE_DEPARTMENT]);
    $this->actingAs($this->admin);
    $this->getJson(route('inventory.issuance_slips.print', ['slip' => $this->slip, 'prepared_by' => $inactive->id, 'checked_by' => $department->id, 'approved_by' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['prepared_by', 'checked_by', 'approved_by']);
    $this->get(route('inventory.issuance_slips.print', $this->slip))->assertOk();
});

test('request page supplies selectable employees with positions and release slips', function () {
    $employee = HrisReference::factory()->create(['metadata' => ['position' => ['name' => 'Engineer']]]);
    $this->actingAs($this->admin)->get(route('inventory.requests.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('employees', 1)->where('employees.0.id', $employee->id)
            ->where('isSuperAdmin', true)->where('employees.0.position_name', 'Engineer')->has('requests.data.0.issuance_slips', 1));
});

test('only super admins can preview slips for fully released requests', function () {
    foreach ([$this->owner, $this->manager] as $user) {
        $this->actingAs($user)->get(route('inventory.issuance_slips.print', $this->slip))->assertForbidden();
        $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page->where('isSuperAdmin', false)->has('employees', 0));
    }
    foreach (['submitted', 'division_approved', 'approved', 'ready_for_release', 'partially_released'] as $status) {
        $this->record->update(['status' => $status]);
        $this->actingAs($this->admin)->get(route('inventory.issuance_slips.print', $this->slip))->assertForbidden();
    }
    $this->record->update(['status' => 'released']);
    $this->get(route('inventory.issuance_slips.print', $this->slip))->assertOk();
});

test('document actions follow role and status and defer signatories until preview opens', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/RequestDocuments.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('employee document dialogs receive only linked liquidation and return records', function () {
    $other = SupplyRequest::factory()->create();
    foreach ([$this->record, $other] as $index => $record) {
        DepartmentLiquidation::query()->create([
            'supply_request_id' => $record->id, 'document_no' => 'LIQ-'.$index,
            'liquidation_date' => now()->toDateString(), 'department' => 'Operations',
            'requested_by_user_id' => $this->manager->id, 'requested_by_name' => $this->manager->name,
            'lines' => [], 'history' => [],
        ]);
    }
    $return = InventoryReturn::factory()->create();
    foreach ([$this->record, $other] as $index => $record) {
        $return->lines()->create([
            'source_supply_request_id' => $record->id, 'inventory_item_id' => $this->item->getKey(),
            'stock_number' => $this->item->stock_number, 'item_name' => 'Returned item '.$index,
            'unit_of_measure' => 'pc', 'quantity_declared' => 1, 'quantity_committed' => 1, 'unit_cost' => 10.25,
        ]);
    }
    $this->actingAs($this->owner)->get(route('inventory.requests.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)
            ->has('liquidationDocuments', 1)->where('liquidationDocuments.0.document_no', 'LIQ-0')
            ->where('liquidationDocuments.0.available_action', null)
            ->has('requests.data.0.return_documents', 1)->has('requests.data.0.return_documents.0.lines', 1)
            ->where('requests.data.0.return_documents.0.lines.0.item_description', 'Returned item 0')
            ->where('requests.data.0.return_documents.0.lines.0.quantity', 1));
    $this->patch(route('inventory.requests.transition', $this->record), ['action' => 'release', 'attested' => true])->assertForbidden();
});
