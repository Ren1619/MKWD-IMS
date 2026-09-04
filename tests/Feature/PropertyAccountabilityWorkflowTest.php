<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the accountability register filters by document type and current custody queue', function () {
    $manager = User::factory()->inventoryManager()->create();

    PropertyAccountabilityDocument::factory()->create([
        'document_no' => 'PAR-2026-SEARCH',
        'document_type' => 'PAR',
        'status' => 'pending_recipient',
        'asset_name' => 'Searchable pump controller',
    ]);
    PropertyAccountabilityDocument::factory()->create([
        'document_no' => 'ICS-2026-CLOSED',
        'document_type' => 'ICS',
        'status' => 'returned',
    ]);

    $this->actingAs($manager)
        ->get(route('inventory.accountability.index', [
            'search' => 'pump controller',
            'document_type' => 'PAR',
            'queue' => 'needs_action',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Accountability/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.document_no', 'PAR-2026-SEARCH')
            ->where('filters.document_type', 'PAR'));
});

test('assigning property issues the correct frozen PAR and the recipient can acknowledge it', function () {
    $manager = User::factory()->inventoryManager()->create();
    $recipientReference = HrisReference::factory()->create([
        'code' => 'EMP-1001',
        'name' => 'Juan Dela Cruz',
    ]);
    $recipient = User::factory()->employee()->create([
        'hris_reference_id' => $recipientReference->id,
    ]);
    $asset = InventoryAsset::factory()->create([
        'name' => 'Engineering Workstation',
        'acquisition_cost' => 50000,
        'property_number' => 'PPE-2026-001',
    ]);

    $this->actingAs($manager)
        ->post(route('inventory.assets.assign', $asset), [
            'hris_reference_id' => $recipientReference->id,
        ])
        ->assertRedirect(route('inventory.assets.index'));

    $document = PropertyAccountabilityDocument::query()->sole();

    expect($document->document_type)->toBe('PAR')
        ->and($document->document_no)->toMatch('/^PAR-\d{4}-\d{6}$/')
        ->and($document->status)->toBe('pending_recipient')
        ->and($document->asset_name)->toBe('Engineering Workstation')
        ->and($document->recipient_name)->toBe('Juan Dela Cruz')
        ->and($document->acquisition_cost)->toBe('50000.00')
        ->and($document->actions()->where('action', 'issue')->exists())->toBeTrue();

    $asset->update(['name' => 'Renamed Registry Record']);

    $this->actingAs($recipient)
        ->patch(route('inventory.accountability.transition', $document), [
            'action' => 'acknowledge',
            'attested' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe('active')
        ->and($document->fresh()->asset_name)->toBe('Engineering Workstation')
        ->and($document->fresh()->acknowledged_by_user_id)->toBe($recipient->id)
        ->and($document->actions()->where('action', 'acknowledge')->exists())->toBeTrue();

    $this->get(route('inventory.accountability.print', $document))
        ->assertOk()
        ->assertSee('PROPERTY ACKNOWLEDGMENT RECEIPT')
        ->assertSee('Engineering Workstation');
});

test('lower valued durable property uses an ICS and the employee sees only their documents', function () {
    $manager = User::factory()->inventoryManager()->create();
    $firstReference = HrisReference::factory()->create();
    $secondReference = HrisReference::factory()->create();
    $employee = User::factory()->employee()->create([
        'hris_reference_id' => $firstReference->id,
    ]);
    $firstAsset = InventoryAsset::factory()->create([
        'name' => 'Field Tablet',
        'acquisition_cost' => 49999.99,
    ]);
    $secondAsset = InventoryAsset::factory()->create([
        'name' => 'Other Employee Tablet',
        'acquisition_cost' => 30000,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $firstAsset), [
        'hris_reference_id' => $firstReference->id,
    ]);
    $this->post(route('inventory.assets.assign', $secondAsset), [
        'hris_reference_id' => $secondReference->id,
    ]);

    $firstDocument = PropertyAccountabilityDocument::query()
        ->where('inventory_asset_id', $firstAsset->getKey())
        ->sole();

    expect($firstDocument->document_type)->toBe('ICS');

    $this->actingAs($employee)
        ->get(route('inventory.accountability.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Accountability/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.document_no', $firstDocument->document_no)
            ->where('canManage', false));
});

test('custody transfer supersedes and links the prior document', function () {
    $manager = User::factory()->inventoryManager()->create();
    $firstReference = HrisReference::factory()->create();
    $secondReference = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 80000]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $firstReference->id,
    ]);
    $priorDocument = PropertyAccountabilityDocument::query()->sole();

    $this->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $secondReference->id,
    ])->assertSessionHasNoErrors();

    $replacement = PropertyAccountabilityDocument::query()
        ->whereKeyNot($priorDocument->getKey())
        ->sole();

    expect($priorDocument->fresh()->status)->toBe('superseded')
        ->and($replacement->status)->toBe('pending_recipient')
        ->and($replacement->supersedes_document_id)->toBe($priorDocument->id)
        ->and($replacement->recipient_reference_id)->toBe($secondReference->id)
        ->and($priorDocument->actions()->where('action', 'superseded')->exists())->toBeTrue();
});

test('manual renewal and return controls require remarks and preserve their history', function () {
    $manager = User::factory()->inventoryManager()->create();
    $reference = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 75000]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $reference->id,
    ]);
    $original = PropertyAccountabilityDocument::query()->sole();

    $this->patch(route('inventory.accountability.transition', $original), [
        'action' => 'renew',
        'attested' => true,
    ])->assertSessionHasErrors('remarks');

    $this->patch(route('inventory.accountability.transition', $original), [
        'action' => 'renew',
        'attested' => true,
        'remarks' => 'Annual custody verification completed in person.',
    ])->assertSessionHasNoErrors();

    $renewed = PropertyAccountabilityDocument::query()
        ->whereKeyNot($original->getKey())
        ->sole();

    expect($original->fresh()->status)->toBe('superseded')
        ->and($renewed->supersedes_document_id)->toBe($original->id)
        ->and($renewed->status)->toBe('pending_recipient');

    $this->patch(route('inventory.accountability.transition', $renewed), [
        'action' => 'return',
        'attested' => true,
        'remarks' => 'Returned and physically inspected in good condition.',
    ])->assertSessionHasNoErrors();

    expect($renewed->fresh()->status)->toBe('returned')
        ->and($renewed->fresh()->closure_reason)->toBe('Returned and physically inspected in good condition.')
        ->and($asset->fresh()->current_custodian_reference_id)->toBeNull()
        ->and($renewed->actions()->where('action', 'returned')->exists())->toBeTrue();
});

test('another employee cannot acknowledge and managers must document witnessed acknowledgment', function () {
    $manager = User::factory()->inventoryManager()->create();
    $reference = HrisReference::factory()->create();
    $otherEmployee = User::factory()->employee()->create([
        'hris_reference_id' => HrisReference::factory()->create()->id,
    ]);
    $asset = InventoryAsset::factory()->create();

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $reference->id,
    ]);
    $document = PropertyAccountabilityDocument::query()->sole();

    $this->actingAs($otherEmployee)
        ->patch(route('inventory.accountability.transition', $document), [
            'action' => 'acknowledge',
            'attested' => true,
        ])
        ->assertSessionHasErrors('action');

    $this->actingAs($manager)
        ->patch(route('inventory.accountability.transition', $document), [
            'action' => 'witnessed_acknowledge',
            'attested' => true,
        ])
        ->assertSessionHasErrors('remarks');

    $this->patch(route('inventory.accountability.transition', $document), [
        'action' => 'witnessed_acknowledge',
        'attested' => true,
        'remarks' => 'Recipient signed the printed form in the presence of the property officer.',
    ])->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe('active')
        ->and($document->actions()->where('action', 'record_witnessed_acknowledgment')->exists())->toBeTrue();
});

test('cancelling an unacknowledged document records the reason and reverses the custody assignment', function () {
    $manager = User::factory()->inventoryManager()->create();
    $reference = HrisReference::factory()->create();
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 20000]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $asset), [
        'hris_reference_id' => $reference->id,
    ]);
    $document = PropertyAccountabilityDocument::query()->sole();

    $this->patch(route('inventory.accountability.transition', $document), [
        'action' => 'cancel',
        'attested' => true,
        'remarks' => 'Custodian assignment was entered against the wrong employee record.',
    ])->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe('cancelled')
        ->and($document->fresh()->closure_reason)->toBe('Custodian assignment was entered against the wrong employee record.')
        ->and($document->custodianAssignment->fresh()->unassigned_at)->not->toBeNull()
        ->and($asset->fresh()->current_custodian_reference_id)->toBeNull()
        ->and($document->actions()->where('action', 'cancel')->exists())->toBeTrue();
});

test('PAR and ICS register reports apply the capitalization threshold', function () {
    $manager = User::factory()->inventoryManager()->create();
    $reference = HrisReference::factory()->create();
    $parAsset = InventoryAsset::factory()->create([
        'name' => 'PAR Threshold Laptop',
        'acquisition_cost' => 50000,
    ]);
    $icsAsset = InventoryAsset::factory()->create([
        'name' => 'ICS Durable Tablet',
        'acquisition_cost' => 49999,
    ]);

    $this->actingAs($manager)->post(route('inventory.assets.assign', $parAsset), [
        'hris_reference_id' => $reference->id,
    ]);
    $this->post(route('inventory.assets.assign', $icsAsset), [
        'hris_reference_id' => $reference->id,
    ]);

    $this->get(route('inventory.reports.print', 'par'))
        ->assertOk()
        ->assertSee('PAR Threshold Laptop')
        ->assertDontSee('ICS Durable Tablet');

    $this->get(route('inventory.reports.print', 'ics'))
        ->assertOk()
        ->assertSee('ICS Durable Tablet')
        ->assertDontSee('PAR Threshold Laptop');
});
