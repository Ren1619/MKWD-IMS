<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use App\Services\InventoryAssetService;
use App\Services\InventoryPropertyTransferService;
use App\Services\PropertyAccountabilityService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->recipientReference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 10], 'division' => ['id' => 20, 'name' => 'Engineering']]]]);
    $this->recipient = User::factory()->employee()->create(['hris_reference_id' => $this->recipientReference->id]);
    $this->issuer = User::factory()->inventoryManager()->create(['name' => 'Senior Supply Officer']);
    $headReference = HrisReference::factory()->create(['metadata' => $this->recipientReference->metadata]);
    $this->head = User::factory()->employee()->create(['email' => 'eng.head@obx.gov.ph', 'hris_reference_id' => $headReference->id, 'name' => 'Division Manager']);
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 75000, 'inspection_acceptance_report_no' => null, 'acceptance_date' => null]);
    app(InventoryAssetService::class)->assign($this->asset, $this->recipientReference, $this->issuer);
    $this->document = PropertyAccountabilityDocument::query()->sole();
    $this->route = route('inventory.accountability.transition', $this->document);
});

test('PPE issues an ARE without requiring IAR and freezes the signatory jurisdiction', function () {
    expect($this->document->document_type)->toBe('ARE')->and($this->document->document_no)->toStartWith('ARE-')
        ->and($this->document->notation_required)->toBeTrue()->and($this->document->status)->toBe('pending_recipient')
        ->and($this->document->recipient_department_id)->toBe('10')->and($this->document->recipient_division_id)->toBe('20')
        ->and($this->document->issued_by_name)->toBe($this->issuer->name)->and($this->document->noted_by_user_id)->toBeNull();
    $this->actingAs($this->issuer)->get(route('inventory.accountability.print', $this->document))->assertSuccessful()
        ->assertSee('ACKNOWLEDGMENT RECEIPT FOR EQUIPMENT')->assertSee('Senior Supply Officer')->assertSee('Division Manager');
});

test('end-user acknowledgment precedes independent division notation', function () {
    $this->actingAs($this->head)->patch($this->route, ['action' => 'note', 'attested' => true])->assertForbidden();
    $this->actingAs($this->recipient)->patch($this->route, ['action' => 'acknowledge', 'attested' => true])->assertSessionHasNoErrors();
    expect($this->document->fresh()->status)->toBe('pending_note');
    $this->actingAs($this->issuer)->patch($this->route, ['action' => 'note', 'attested' => true])->assertForbidden();
    $this->actingAs($this->head)->patch($this->route, ['action' => 'note', 'attested' => false])->assertSessionHasErrors('attested');
    $this->patch($this->route, ['action' => 'note', 'attested' => true])->assertSessionHasNoErrors();
    expect($this->document->fresh()->status)->toBe('active')
        ->and($this->document->fresh()->noted_by_user_id)->toBe($this->head->id)
        ->and($this->document->fresh()->noted_by_name)->toBe($this->head->name)
        ->and($this->document->fresh()->noted_at)->not->toBeNull()
        ->and($this->document->actions()->where('action', 'note')->count())->toBe(1);
    $this->patch($this->route, ['action' => 'note', 'attested' => true])->assertForbidden();
});

test('noted AREs are personal and remain visible after head assignments change', function () {
    $url = route('inventory.assets.ares.index', ['view' => 'noted']);
    $this->actingAs($this->head)->get($url)->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('Inventory/Assets/Ares')->has('documents.data', 0));
    app(PropertyAccountabilityService::class)->acknowledge($this->document, $this->recipient);
    $this->get(route('inventory.assets.ares.index'))->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.can_note', true));
    app(PropertyAccountabilityService::class)->note($this->document, $this->head);
    $this->actingAs($this->issuer)->get($url)->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
    $this->head->update(['email' => 'former-manager@example.com']);
    $this->actingAs($this->head->fresh())->get($url)->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.id', $this->document->id));
    $this->get(route('inventory.accountability.print', $this->document))->assertSuccessful()->assertSee($this->head->name);
    $this->get(route('inventory.assets.ares.index', ['view' => 'noted', 'search' => 'unmatched']))->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
    $this->actingAs($this->recipient)->get(route('inventory.assets.ares.index'))->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
});

test('unrelated divisions and department heads cannot note or browse another ARE', function (array $position, string $email) {
    $reference = HrisReference::factory()->create(['metadata' => ['position' => $position]]);
    $user = User::factory()->employee()->create(['hris_reference_id' => $reference->id, 'email' => $email]);
    app(PropertyAccountabilityService::class)->acknowledge($this->document, $this->recipient);
    $this->actingAs($user)->patch($this->route, ['action' => 'note', 'attested' => true])->assertForbidden();
    $this->get(route('inventory.assets.ares.index'))->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
    $this->get(route('inventory.accountability.print', $this->document))->assertForbidden();
})->with([
    'other division' => [['department' => ['id' => 10], 'division' => ['id' => 30]], 'other.head@obx.gov.ph'],
    'other department' => [['department' => ['id' => 11], 'division' => ['id' => 20]], 'other.head@obx.gov.ph'],
    'department head' => [['department' => ['id' => 10]], 'dept.head@obx.gov.ph'],
    'unit employee' => [['department' => ['id' => 10], 'division' => ['id' => 20], 'unit' => ['id' => 2]], 'unit.head@obx.gov.ph'],
]);

test('ARE notation remains nullable when the recipient has no division', function () {
    $reference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 10]]]]);
    $recipient = User::factory()->employee()->create(['hris_reference_id' => $reference->id]);
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 75000]);
    app(InventoryAssetService::class)->assign($asset, $reference, $this->issuer);
    $document = $asset->accountabilityDocuments()->sole();
    app(PropertyAccountabilityService::class)->acknowledge($document, $recipient);
    expect($document->fresh()->status)->toBe('active')->and($document->fresh()->notation_required)->toBeFalse()
        ->and($document->fresh()->noted_by_user_id)->toBeNull()->and($document->fresh()->noted_at)->toBeNull();
});

test('witnessed acknowledgment cannot replace the ARE end-user signature', function () {
    $this->actingAs($this->issuer)->patch($this->route, ['action' => 'witnessed_acknowledge', 'attested' => true, 'remarks' => 'Witnessed signature.'])->assertSessionHasErrors('action');
    expect($this->document->fresh()->status)->toBe('pending_recipient');
});

test('pending notation keeps the existing ARE open and blocks duplicate issue', function () {
    app(PropertyAccountabilityService::class)->acknowledge($this->document, $this->recipient);
    expect(fn () => app(PropertyAccountabilityService::class)->issueForCurrentAssignment($this->asset->fresh(), $this->issuer))->toThrow(ValidationException::class);
    expect($this->asset->accountabilityDocuments()->count())->toBe(1);
});

test('the ARE issuer and end user cannot also provide division notation', function (string $actor) {
    app(PropertyAccountabilityService::class)->acknowledge($this->document, $this->recipient);
    $user = $this->{$actor};
    if ($actor === 'issuer') {
        $reference = HrisReference::factory()->create(['metadata' => $this->recipientReference->metadata]);
        $user->update(['hris_reference_id' => $reference->id]);
    }
    $user->update(['email' => $actor.'.head@obx.gov.ph']);

    $this->actingAs($user->fresh())->patch($this->route, ['action' => 'note', 'attested' => true])->assertForbidden();
    expect($this->document->fresh()->status)->toBe('pending_note')->and($this->document->fresh()->noted_at)->toBeNull();
})->with(['issuer', 'recipient']);

test('an incomplete required ARE cannot be transferred before its signatures', function (bool $acknowledged) {
    if ($acknowledged) {
        app(PropertyAccountabilityService::class)->acknowledge($this->document, $this->recipient);
    }

    expect(fn () => app(InventoryPropertyTransferService::class)->create([
        'inventory_asset_id' => $this->asset->getKey(),
        'recipient_reference_id' => $this->head->hris_reference_id,
        'transfer_type' => 'reassignment',
        'reason' => 'New accountable officer.',
        'destination_location' => 'Engineering office',
        'desired_date' => today()->toDateString(),
    ], $this->issuer))->toThrow(ValidationException::class, 'Complete the ARE acknowledgment and division-manager notation');
    expect($this->asset->fresh()->current_custodian_reference_id)->toBe($this->recipientReference->id);
})->with([false, true]);

test('PPE assignment requires the supply officer attestation', function () {
    $asset = InventoryAsset::factory()->create(['acquisition_cost' => 75000]);
    $this->actingAs($this->issuer)->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $this->recipientReference->id])->assertSessionHasErrors('attested');
    $this->post(route('inventory.assets.assign', $asset), ['hris_reference_id' => $this->recipientReference->id, 'attested' => true])->assertSessionHasNoErrors();
    expect($asset->accountabilityDocuments()->sole()->document_type)->toBe('ARE');
});

test('legacy PAR acknowledgment is preserved without inventing notation', function () {
    $legacy = PropertyAccountabilityDocument::factory()->create(['inventory_asset_id' => $this->asset->getKey(), 'recipient_reference_id' => $this->recipientReference->id]);
    app(PropertyAccountabilityService::class)->acknowledge($legacy, $this->recipient);
    expect($legacy->fresh()->status)->toBe('active')->and($legacy->fresh()->noted_at)->toBeNull();
});

test('PPE tabs include an explicit empty state for the personal noted ARE view', function () {
    expect(file_get_contents(resource_path('js/components/ppe-module-nav.tsx')))->toContain('Noted AREs', "view: 'noted'")
        ->and(file_get_contents(resource_path('js/pages/Inventory/Assets/Ares.tsx')))->toContain('No AREs noted by you yet', '<PpeModuleNav');
});
