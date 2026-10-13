<?php

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('purchase request and job order registers isolate their records including searches and archives', function (string $kind, string $module, string $component) {
    $manager = User::factory()->inventoryManager()->create();
    $own = ProcurementRequest::factory()->create(['document_kind' => $kind, 'purpose' => 'Shared search term']);
    $other = ProcurementRequest::factory()->create(['document_kind' => $kind === 'PR' ? 'JO' : 'PR', 'purpose' => 'Shared search term']);
    $this->actingAs($manager)->get(route("inventory.{$module}.index", ['search' => 'Shared search term']))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component($component)->where('moduleKind', $kind)->has('procurementRequests.data', 1)->where('procurementRequests.data.0.id', $own->id));
    $own->delete();
    $other->delete();
    $this->get(route("inventory.{$module}.index", ['queue' => 'archived']))->assertInertia(fn (Assert $page) => $page->has('procurementRequests.data', 1)->where('procurementRequests.data.0.id', $own->id)->where('procurementRequests.data.0.can_delete', false));
})->with([['PR', 'purchase_requests', 'Inventory/Procurement/PurchaseRequests'], ['JO', 'job_orders', 'Inventory/Procurement/JobOrders']]);

test('each module creates only its document kind and permits corrections only during intake', function (string $kind, string $module) {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();
    $payload = [...procurementIntakeData(), 'document_kind' => $kind, 'type' => $kind === 'JO' ? 'new_item' : 'replenishment', 'source' => 'manual', 'purpose' => 'Module intake', 'lines' => $kind === 'JO' ? [] : [['item_kind' => 'consumable', 'inventory_item_id' => $item->getKey(), 'item_name' => $item->name, 'unit_of_measure' => 'unit', 'quantity' => 2, 'estimated_unit_cost' => 50]]];
    $this->actingAs($manager)->post(route("inventory.{$module}.store"), [...$payload, 'document_kind' => $kind === 'PR' ? 'JO' : 'PR'])->assertSessionHasErrors('document_kind');
    $this->post(route("inventory.{$module}.store"), $payload)->assertSessionHasNoErrors();
    $record = ProcurementRequest::query()->sole();
    expect($record->document_kind)->toBe($kind)->and($record->status)->toBe('intake');
    $this->patch(route("inventory.{$module}.update", $record), [...$payload, 'purpose' => 'Corrected intake'])->assertSessionHasNoErrors();
    expect($record->fresh()->purpose)->toBe('Corrected intake');
    $record->update(['status' => 'for_document_review']);
    $this->patch(route("inventory.{$module}.update", $record), $payload)->assertSessionHasErrors('action');
})->with([['PR', 'purchase_requests'], ['JO', 'job_orders']]);

test('module endpoints reject records of the other kind', function () {
    $manager = User::factory()->inventoryManager()->create();
    $jo = ProcurementRequest::factory()->intake()->create(['document_kind' => 'JO']);
    $this->actingAs($manager)->patch(route('inventory.purchase_requests.transition', $jo), ['action' => 'cancel', 'attested' => true, 'remarks' => 'Cross module attempt'])->assertNotFound();
    $this->delete(route('inventory.purchase_requests.destroy', $jo), ['remarks' => 'Cross module attempt'])->assertNotFound();
    $this->get(route('inventory.purchase_requests.print', $jo))->assertNotFound();
    expect($jo->fresh()->status)->toBe('intake');
});

test('unused intake deletion is recoverable and preserves lines and decision history', function (string $kind, string $module) {
    $manager = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create(['document_kind' => $kind]);
    $record->lines()->create(['item_kind' => 'consumable', 'item_name' => 'Unused draft item', 'quantity' => 1, 'unit_of_measure' => 'unit', 'estimated_unit_cost' => 10]);
    $this->actingAs($manager)->delete(route("inventory.{$module}.destroy", $record), ['remarks' => 'Duplicate intake'])->assertSessionHasNoErrors();
    $this->assertSoftDeleted($record);
    expect($record->lines()->count())->toBe(1)->and($record->actions()->where('action', 'delete')->sole()->remarks)->toBe('Duplicate intake');
    $this->patch(route("inventory.{$module}.transition", $record), ['action' => 'submit_intake', 'attested' => true])->assertNotFound();
    $this->patch(route("inventory.{$module}.restore", $record), ['remarks' => 'Needed after all'])->assertSessionHasNoErrors();
    expect($record->fresh()->trashed())->toBeFalse()->and($record->fresh()->status)->toBe('intake');
})->with([['PR', 'purchase_requests'], ['JO', 'job_orders']]);

test('records with evidence linked shortages or ongoing processing cannot be deleted', function () {
    $manager = User::factory()->inventoryManager()->create();
    $linked = ProcurementRequest::factory()->intake()->create(['supply_request_id' => SupplyRequest::factory()->create()->id]);
    $inProgress = ProcurementRequest::factory()->intake()->create(['status' => 'for_signatures']);
    $withEvidence = ProcurementRequest::factory()->intake()->create();
    $withEvidence->attachments()->create(['uploaded_by_user_id' => $manager->id, 'document_key' => 'caf', 'original_name' => 'caf.pdf', 'path' => 'retained/caf.pdf', 'size' => 10]);
    foreach ([$linked, $inProgress, $withEvidence] as $record) {
        $this->actingAs($manager)->delete(route('inventory.purchase_requests.destroy', $record), ['remarks' => 'Not allowed'])->assertSessionHasErrors('action');
        expect($record->fresh()->trashed())->toBeFalse();
    }
});

test('completed records can be archived and restored without changing workflow status', function (string $kind, string $module, string $status) {
    $manager = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->create(['document_kind' => $kind, 'status' => $status]);
    $this->actingAs($manager)->patch(route("inventory.{$module}.archive", $record), ['remarks' => 'Filed after completion'])->assertSessionHasNoErrors();
    $this->assertSoftDeleted($record);
    $this->get(route("inventory.{$module}.print", $record))->assertSuccessful();
    $this->get(route("inventory.{$module}.index"))->assertInertia(fn (Assert $page) => $page->has('procurementRequests.data', 0));
    $this->patch(route("inventory.{$module}.restore", $record), ['remarks' => 'Review retained record'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe($status)->and($record->fresh()->trashed())->toBeFalse();
})->with([['PR', 'purchase_requests', 'accepted'], ['JO', 'job_orders', 'endorsed'], ['PR', 'purchase_requests', 'cancelled']]);

test('active records cannot be archived and management decisions require reasons', function () {
    $manager = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create();
    $this->actingAs($manager)->patch(route('inventory.purchase_requests.archive', $record), ['remarks' => 'Still active'])->assertSessionHasErrors('action');
    $this->delete(route('inventory.purchase_requests.destroy', $record))->assertSessionHasErrors('remarks');
    expect($record->fresh()->trashed())->toBeFalse();
});

test('employees cannot access or manage either procurement module', function (string $module) {
    $employee = User::factory()->employee()->create();
    $record = ProcurementRequest::factory()->intake()->create();
    $this->actingAs($employee)->get(route("inventory.{$module}.index"))->assertForbidden();
    $this->delete(route("inventory.{$module}.destroy", $record), ['remarks' => 'Not authorized'])->assertForbidden();
    $this->patch(route("inventory.{$module}.archive", $record), ['remarks' => 'Not authorized'])->assertForbidden();
})->with(['purchase_requests', 'job_orders']);

test('the sidebar combines purchase requests and job orders as requests under procurement', function () {
    $source = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
    expect($source)->toContain("title: 'Requests'", 'label="Procurement"', 'procurementIndex()')
        ->not->toContain("title: 'Purchase Requests'", "title: 'Job Orders'");

    $manager = User::factory()->inventoryManager()->create();
    ProcurementRequest::factory()->create(['document_kind' => 'PR']);
    ProcurementRequest::factory()->create(['document_kind' => 'JO']);

    $this->actingAs($manager)->get(route('inventory.procurement.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Procurement/Index')
            ->where('moduleKind', null)
            ->has('procurementRequests.data', 2));
});
