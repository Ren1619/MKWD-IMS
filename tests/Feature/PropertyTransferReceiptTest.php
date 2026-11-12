<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryPropertyTransfer;
use App\Models\User;
use App\Services\InventoryAssetService;
use App\Services\InventoryPropertyTransferService;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->manager = User::factory()->inventoryManager()->create();
    $this->reference = HrisReference::factory()->create();
    $this->recipient = User::factory()->employee()->create(['hris_reference_id' => $this->reference->id]);
    $this->asset = InventoryAsset::factory()->create(['acquisition_cost' => 75000]);
    app(InventoryAssetService::class)->assign($this->asset, HrisReference::factory()->create(), $this->manager);
    $this->originalCustodian = $this->asset->fresh()->current_custodian_reference_id;
    $this->workflow = app(InventoryPropertyTransferService::class);
    $this->transfer = $this->workflow->create([
        'inventory_asset_id' => $this->asset->getKey(), 'recipient_reference_id' => $this->reference->id,
        'transfer_type' => 'reassignment', 'reason' => 'New accountable officer.',
        'destination_location' => 'New office', 'desired_date' => today()->toDateString(),
    ], $this->manager);
    $this->workflow->transition($this->transfer, ['action' => 'verify', 'attestation' => 'Verified.', 'inspection_reference' => 'INSPECT'], User::factory()->inventoryManager()->create());
    $this->workflow->transition($this->transfer, ['action' => 'approve', 'attestation' => 'Approved.'], User::factory()->inventoryManager()->create());
    $this->transfer->refresh();
    $this->endpoint = route('inventory.transfers.transition', $this->transfer);
    $this->receiptData = ['action' => 'acknowledge_receipt', 'attested' => true, 'attestation' => 'I received the equipment and accept accountability.', 'handover_date' => today()->toDateString()];
    $this->completionData = ['action' => 'complete', 'attestation' => 'Handover checked.', 'inspection_reference' => 'JOINT', 'condition_status' => 'good', 'handover_date' => today()->toDateString(), 'release_reference' => 'RELEASE'];
});

test('receiving employees see only their PTR records with receipt controls and no management catalogs', function () {
    $this->actingAs($this->recipient)->get(route('inventory.transfers.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1)->has('assets', 0)->has('employees', 0)->where('canManage', false));
    $this->get(route('inventory.transfers.show', $this->transfer))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('allowedActions', ['acknowledge_receipt']));
    $this->get(route('inventory.transfers.print', $this->transfer))->assertSuccessful();
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.transfers.index'))
        ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 0));
    $this->get(route('inventory.transfers.show', $this->transfer))->assertForbidden();
    $this->get(route('inventory.transfers.print', $this->transfer))->assertForbidden();
});

test('recipient screens render a confirmed receipt form and retain the signed receipt afterward', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/PropertyTransferForm.mjs'), 'Show']);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $pages = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    $document = new DOMDocument;
    @$document->loadHTML($pages['pending']);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form//input[@name="action" and @value="acknowledge_receipt"]')->length)->toBe(1)
        ->and($xpath->query('//form[ancestor::*[@data-slot="card-content"]]//input[@name="action" and @value="acknowledge_receipt"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-slot="card-title" and normalize-space(.)="Transfer details"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-slot="card-title" and normalize-space(.)="Decision history"]')->length)->toBe(1)
        ->and($xpath->query('//form//input[@name="attested" and @required]')->length)->toBe(1)
        ->and($xpath->query('//form//button[@type="submit" and normalize-space(.)="Acknowledge receipt"]')->length)->toBe(1)
        ->and($pages['pending'])->not->toContain('Record decision')
        ->and($pages['recorded'])->toContain('Receipt acknowledged by', 'Receiving employee', 'I received the property.')
        ->not->toContain('name="attested"');

    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/PropertyTransferForm.mjs'), 'employee']);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $pages = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    expect($pages['available'])->not->toContain('Submit PTR request', 'name="recipient_reference_id"');
});

test('only the named receiving employee acknowledges once without posting custody changes', function () {
    $this->actingAs($this->manager)->patch($this->endpoint, $this->receiptData)->assertForbidden();
    $this->actingAs(User::factory()->employee()->create())->patch($this->endpoint, $this->receiptData)->assertForbidden();
    $this->actingAs($this->recipient)->patch($this->endpoint, $this->receiptData)->assertSessionHasNoErrors();
    $receipt = $this->transfer->actions()->where('action', 'acknowledge_receipt')->sole();
    expect($receipt->actor_user_id)->toBe($this->recipient->id)->and($receipt->actor_name)->toBe($this->recipient->name)
        ->and($receipt->evidence['hris_reference_id'])->toBe($this->reference->id)
        ->and($this->transfer->fresh()->status)->toBe('approved')
        ->and($this->asset->fresh()->current_custodian_reference_id)->toBe($this->originalCustodian);
    $this->patch($this->endpoint, $this->receiptData)->assertForbidden();
    $this->patch($this->endpoint, $this->completionData)->assertForbidden();
    expect($this->transfer->actions()->where('action', 'acknowledge_receipt')->count())->toBe(1);
});

test('receipt acknowledgment requires confirmation and a valid receipt date', function (string $field, mixed $value) {
    $data = [...$this->receiptData, $field => $value];
    $this->actingAs($this->recipient)->patch($this->endpoint, $data)->assertSessionHasErrors($field);
    expect($this->transfer->actions()->where('action', 'acknowledge_receipt')->exists())->toBeFalse();
})->with([['attested', false], ['handover_date', null], ['handover_date', '2099-01-01'], ['attestation', '']]);

test('manager completion accepts the digital receipt instead of a paper receipt reference', function () {
    $this->actingAs($this->recipient)->patch($this->endpoint, $this->receiptData)->assertSessionHasNoErrors();
    $receipt = $this->transfer->actions()->where('action', 'acknowledge_receipt')->sole();
    $this->actingAs($this->manager)->patch($this->endpoint, $this->completionData)->assertSessionHasNoErrors();
    $completion = $this->transfer->actions()->where('action', 'complete')->sole();
    expect($this->transfer->fresh()->status)->toBe('completed')
        ->and($this->asset->fresh()->current_custodian_reference_id)->toBe($this->reference->id)
        ->and($completion->evidence['receipt_action_id'])->toBe($receipt->id)
        ->and($completion->evidence['receipt_reference'])->toBe($this->transfer->document_no.'-RECEIPT-'.$receipt->id);
    $this->recipient->update(['hris_reference_id' => null]);
    $this->actingAs($this->recipient->fresh())->get(route('inventory.transfers.index'))->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1));
    $this->get(route('inventory.transfers.print', $this->transfer))->assertSuccessful()->assertSee('Receipt digitally acknowledged by '.$receipt->actor_name);
});

test('receipt cannot predate approval and completion cannot contradict the receipt date', function () {
    $this->actingAs($this->recipient)->patch($this->endpoint, [...$this->receiptData, 'handover_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('transfer');
    $this->patch($this->endpoint, $this->receiptData)->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->patch($this->endpoint, [...$this->completionData, 'handover_date' => today()->subDay()->toDateString()])->assertSessionHasErrors('transfer');
    expect($this->asset->fresh()->current_custodian_reference_id)->toBe($this->originalCustodian);
});

test('unapproved cancelled stale and external transfers cannot receive an end user acknowledgment', function (string $case) {
    if ($case === 'stale') {
        $this->asset->update(['item_description' => 'Changed property']);
    } elseif ($case === 'unapproved') {
        $this->transfer->update(['status' => 'verified']);
    } elseif ($case === 'external') {
        $this->transfer = InventoryPropertyTransfer::factory()->create(['status' => 'approved', 'transfer_type' => 'agency_transfer', 'recipient_reference_id' => null]);
        $this->endpoint = route('inventory.transfers.transition', $this->transfer);
    } else {
        $this->workflow->transition($this->transfer, ['action' => 'cancel', 'attestation' => 'Cancelled.'], $this->manager);
    }
    $response = $this->actingAs($this->recipient)->patch($this->endpoint, $this->receiptData);
    if ($case === 'stale') {
        $response->assertSessionHasErrors('transfer');
    } else {
        $response->assertForbidden();
    }
    expect($this->transfer->actions()->where('action', 'acknowledge_receipt')->exists())->toBeFalse();
})->with(['unapproved', 'cancelled', 'stale', 'external']);
