<?php

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('returned requisitions can be corrected before review while earlier versions remain in history', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();
    $record = ProcurementRequest::factory()->intake()->create(['status' => 'returned_for_compliance']);
    $record->lines()->create(['inventory_item_id' => $item->getKey(), 'item_kind' => 'consumable', 'item_name' => 'Earlier name', 'unit_of_measure' => 'unit', 'quantity' => 1, 'estimated_unit_cost' => 10]);
    $payload = [...procurementIntakeData(), 'type' => 'replenishment', 'source' => 'manual', 'purpose' => 'Corrected request', 'lines' => [['inventory_item_id' => $item->getKey(), 'item_kind' => 'consumable', 'item_name' => $item->name, 'unit_of_measure' => 'unit', 'quantity' => 2, 'estimated_unit_cost' => 20]]];
    $this->actingAs($manager)->patch(route('inventory.procurement.update', $record), $payload)->assertSessionHasNoErrors();
    expect($record->fresh()->purpose)->toBe('Corrected request')->and($record->lines()->sole()->quantity)->toBe(2)->and($record->actions()->where('action', 'amend_requisition')->sole()->metadata['previous']['lines'][0]['item_name'])->toBe('Earlier name');
    $record->update(['status' => 'ready_for_preparation']);
    $this->patch(route('inventory.procurement.update', $record), $payload)->assertSessionHasErrors('action');
    expect($record->lines()->sole()->quantity)->toBe(2);
});

test('intake requires an approved source and does not prepare a PR prematurely', function () {
    $manager = User::factory()->inventoryManager()->create();
    $item = InventoryItem::factory()->create();
    $payload = [...procurementIntakeData(), 'type' => 'replenishment', 'source' => 'manual', 'purpose' => 'Buy office supplies', 'lines' => [['inventory_item_id' => $item->getKey(), 'item_kind' => 'consumable', 'item_name' => $item->name, 'quantity' => 2, 'unit_of_measure' => 'unit', 'estimated_unit_cost' => 100]]];
    $this->actingAs($manager)->post(route('inventory.procurement.store'), [...$payload, 'preprocurement' => []])->assertSessionHasErrors('preprocurement.approved_request_reference');
    $this->post(route('inventory.procurement.store'), $payload)->assertSessionHasNoErrors();
    $record = ProcurementRequest::query()->sole();
    expect($record->status)->toBe('intake')->and($record->pr_no)->toStartWith('REQ-');
    $this->patch(route('inventory.procurement.transition', $record), ['action' => 'prepare_document', 'attested' => true, 'preparation' => ['prepared_by_division' => 'PMMD']])->assertSessionHasErrors('action');
    $this->get(route('inventory.procurement.print', $record))->assertUnprocessable();
});

test('the procurement register exposes document checklists and only eligible signatory accounts', function () {
    $manager = User::factory()->inventoryManager()->create();
    $employee = User::factory()->employee()->create();
    $inactive = User::factory()->inventoryManager()->create(['is_active' => false]);
    $record = ProcurementRequest::factory()->intake()->create(['created_by_user_id' => $manager->id]);
    $this->actingAs($manager)->get(route('inventory.procurement.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Inventory/Procurement/Index')
        ->where('procurementRequests.data.0.pr_no', $record->pr_no)
        ->has('procurementRequests.data.0.attachments', 0)
        ->where('procurementCategories.supplies.documents.market_scoping.required', true)
        ->where('procurementCategories.project.documents.program_of_works.required', true)
        ->has('signatories', 1)
        ->where('signatories.0.id', $manager->id));
});

test('document review returns incomplete requests and records compliance resubmission', function () {
    $creator = User::factory()->inventoryManager()->create();
    $reviewer = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create(['created_by_user_id' => $creator->id]);
    $route = route('inventory.procurement.transition', $record);
    $this->actingAs($creator)->patch($route, ['action' => 'submit_intake', 'attested' => true])->assertSessionHasNoErrors();
    $this->actingAs($reviewer)->patch($route, ['action' => 'verify_documents', 'attested' => true])->assertSessionHasErrors('document_waivers');
    $this->patch($route, ['action' => 'return_for_compliance', 'attested' => true])->assertSessionHasErrors('remarks');
    $this->patch($route, ['action' => 'return_for_compliance', 'attested' => true, 'remarks' => 'Missing CAF and approved request'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('returned_for_compliance')->and($record->fresh()->completed_at)->toBeNull();
    $this->actingAs($creator)->patch($route, ['action' => 'submit_intake', 'attested' => true, 'intake_details' => ['division' => 'PMMD']])->assertSessionHasNoErrors();
    expect($record->fresh()->preprocurement['division'])->toBe('PMMD')->and($record->actions()->where('action', 'return_for_compliance')->sole()->remarks)->toBe('Missing CAF and approved request');
});

test('supporting documents are private validated retained and scoped to the correct record', function () {
    Storage::fake('local');
    $manager = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create();
    $route = route('inventory.procurement.attachments.store', $record);
    $this->actingAs($manager)->post($route, ['document_key' => 'caf', 'file' => UploadedFile::fake()->create('bad.exe', 10)])->assertSessionHasErrors('file');
    $this->post($route, ['document_key' => 'unknown', 'file' => UploadedFile::fake()->create('caf.pdf', 10, 'application/pdf')])->assertSessionHasErrors('document_key');
    foreach (['caf.pdf', 'corrected-caf.pdf'] as $name) {
        $this->post($route, ['document_key' => 'caf', 'file' => UploadedFile::fake()->create($name, 10, 'application/pdf')])->assertSessionHasNoErrors();
    }
    expect($record->attachments()->count())->toBe(2);
    $attachment = $record->attachments()->first();
    Storage::disk('local')->assertExists($attachment->path);
    $this->get(route('inventory.procurement.attachments.download', [$record, $attachment]))->assertDownload('caf.pdf');
    $other = ProcurementRequest::factory()->intake()->create();
    $this->get(route('inventory.procurement.attachments.download', [$other, $attachment]))->assertNotFound();
    $record->update(['status' => 'for_document_review']);
    $this->post($route, ['document_key' => 'caf', 'file' => UploadedFile::fake()->create('later.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.procurement.attachments.download', [$record, $attachment]))->assertForbidden();
    $this->post($route, ['document_key' => 'caf', 'file' => UploadedFile::fake()->create('later.pdf', 10, 'application/pdf')])->assertForbidden();
});

test('replacement reports cannot be waived and a preparer cannot verify their own documents', function () {
    Storage::fake('local');
    $creator = User::factory()->inventoryManager()->create();
    $reviewer = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create(['created_by_user_id' => $creator->id, 'procurement_category' => 'equipment', 'preprocurement' => [...procurementIntakeData()['preprocurement'], 'is_replacement' => true]]);
    foreach (['approved_request', 'caf', 'app', 'ppmp'] as $key) {
        $this->actingAs($creator)->post(route('inventory.procurement.attachments.store', $record), ['document_key' => $key, 'file' => UploadedFile::fake()->create($key.'.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    }
    $route = route('inventory.procurement.transition', $record);
    $this->patch($route, ['action' => 'submit_intake', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch($route, ['action' => 'verify_documents', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($reviewer)->patch($route, ['action' => 'verify_documents', 'attested' => true, 'document_waivers' => ['specifications' => 'Catalog specification sufficient', 'waste_report' => 'Not applicable', 'pre_repair' => 'Not applicable', 'spot_report' => 'Not applicable']])->assertSessionHasErrors('document_waivers');
});

test('job orders follow assigned signatures GM approval printing and procurement endorsement without stock posting', function () {
    $creator = User::factory()->inventoryManager()->create();
    $gm = User::factory()->superAdmin()->create();
    $this->actingAs($creator)->post(route('inventory.procurement.store'), [...procurementIntakeData(), 'document_kind' => 'JO', 'procurement_category' => 'other_services', 'type' => 'new_item', 'source' => 'manual', 'purpose' => 'Repair pump', 'lines' => []])->assertSessionHasNoErrors();
    $record = ProcurementRequest::query()->sole();
    prepareAndSignProcurement($record, $gm);
    expect($record->fresh()->status)->toBe('for_approval')->and($record->fresh()->pr_no)->toStartWith('JO-')->and($record->fresh()->preprocurement['prepared_by_division'])->toBe('PMMD')->and($record->fresh()->preprocurement['division'])->toBe('PWSD');
    $route = route('inventory.procurement.transition', $record);
    $this->actingAs($creator)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($gm)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    $this->get(route('inventory.procurement.print', $record))->assertSuccessful()->assertSee('JOB ORDER')->assertSee('Repair pump and test operation.')->assertSee($gm->name);
    $this->patch($route, ['action' => 'forward', 'attested' => true])->assertSessionHasErrors('endorsement_recipient');
    $this->patch($route, ['action' => 'forward', 'attested' => true, 'endorsement_recipient' => 'Procurement Unit'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('endorsed')->and($record->fresh()->completed_at)->not->toBeNull()->and(InventoryItem::query()->count())->toBe(0);
    $this->patch($route, ['action' => 'order', 'attested' => true])->assertSessionHasErrors('action');
    $this->get(route('inventory.job_orders.index', ['queue' => 'completed']))->assertInertia(fn (Assert $page) => $page->has('procurementRequests.data', 1));
});

test('GM disapproval cancels the document and preserves its file and audit history', function () {
    $creator = User::factory()->inventoryManager()->create();
    $gm = User::factory()->superAdmin()->create();
    $record = ProcurementRequest::factory()->intake()->create(['created_by_user_id' => $creator->id, 'document_kind' => 'JO', 'procurement_category' => 'other_services']);
    prepareAndSignProcurement($record, $gm);
    $route = route('inventory.procurement.transition', $record);
    $this->actingAs($gm)->patch($route, ['action' => 'reject', 'attested' => true, 'remarks' => 'Disapproved; return to the end user.'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('cancelled')->and($record->attachments()->count())->toBeGreaterThan(0)->and($record->actions()->where('action', 'reject')->sole()->remarks)->toContain('return to the end user');
    $this->patch($route, ['action' => 'submit_intake', 'attested' => true])->assertSessionHasErrors('action');
});

test('signature steps cannot be skipped by another inventory manager', function () {
    $manager = User::factory()->inventoryManager()->create();
    $other = User::factory()->inventoryManager()->create();
    $record = ProcurementRequest::factory()->intake()->create(['status' => 'for_signatures', 'preprocurement' => ['prepared_by_user_id' => $manager->id, 'recommending_user_id' => $other->id, 'signatures' => []]]);
    $route = route('inventory.procurement.transition', $record);
    $this->actingAs($other)->patch($route, ['action' => 'sign', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($manager)->patch($route, ['action' => 'sign', 'attested' => true])->assertSessionHasNoErrors();
    $this->patch($route, ['action' => 'sign', 'attested' => true])->assertSessionHasErrors('action');
    expect($record->fresh()->preprocurement['signatures'])->toHaveCount(1);
});
