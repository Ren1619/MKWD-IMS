<?php

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function issuanceFormPayload(): array
{
    return [
        'issuance_form' => true,
        'purpose' => 'Official field operations',
        'responsibility_center_code' => '',
        'lines' => [['quantity' => 2, 'unit_of_measure' => 'box', 'item_name' => 'Requested supplies']],
    ];
}

test('issuance forms snapshot the requester department and correct managers from HRIS', function () {
    $reference = HrisReference::factory()->create([
        'name' => 'Requester Full Name',
        'metadata' => ['position' => ['department' => ['id' => 1, 'name' => 'Operations'], 'division' => ['id' => 2, 'name' => 'Field Services']]],
    ]);
    $user = User::factory()->employee()->create(['hris_reference_id' => $reference->id]);
    foreach (['Division' => [2, 'Division Manager Full Name'], 'Department' => [1, 'Department Manager Full Name']] as $organization => [$id, $name]) {
        HrisReference::factory()->create(['name' => $name, 'metadata' => ['position' => ['name' => $organization.' Manager', strtolower($organization) => ['id' => $id]]]]);
    }
    $other = HrisReference::factory()->create();
    $this->actingAs($user)->post(route('inventory.requests.store'), [
        ...issuanceFormPayload(),
        'requester_reference_id' => $other->id,
        'office_name' => 'Forged department',
        'requested_by' => 'Forged requester',
        'isf_no' => '01-01-999',
        'date' => '2000-01-01',
        'date_needed' => 'invalid',
    ])->assertSessionHasNoErrors();

    $record = SupplyRequest::query()->sole();
    expect($record->requester_reference_id)->toBe($reference->id)
        ->and($record->requester_name)->toBe('Requester Full Name')
        ->and($record->office_name)->toBe('Operations')
        ->and($record->issuance_details['checked_by'])->toBe('Division Manager Full Name')
        ->and($record->issuance_details['approved_by'])->toBe('Department Manager Full Name')
        ->and($record->issuance_details['date'])->toBe(now('Asia/Manila')->toDateString())
        ->and($record->isf_no)->toBe(now('Asia/Manila')->format('m-d').'-001')
        ->and($record->date_needed)->toBeNull()
        ->and($record->lines()->sole()->quantity_requested)->toBe(2);
});

test('ISF numbers increment across requesters and reset at Manila midnight and across years', function () {
    $user = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $this->travelTo(new DateTimeImmutable('2026-10-06 15:59:59 UTC'));
    $this->actingAs($user)->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasNoErrors();
    $this->actingAs($other)->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasNoErrors();
    $this->travelTo(new DateTimeImmutable('2026-10-06 16:00:00 UTC'));
    $this->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasNoErrors();
    $this->travelTo(new DateTimeImmutable('2027-10-06 10:00:00 UTC'));
    $this->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasNoErrors();

    expect(SupplyRequest::query()->orderBy('id')->pluck('isf_no')->all())
        ->toBe(['10-06-001', '10-06-002', '10-07-001', '10-06-001']);
});

test('issuance items link stock and retain catalog descriptions and units', function () {
    $user = User::factory()->employee()->create();
    $item = InventoryItem::factory()->create(['name' => 'Paper', 'unit_of_measure' => 'ream']);
    $this->actingAs($user)->post(route('inventory.requests.store'), [
        ...issuanceFormPayload(),
        'lines' => [['inventory_item_id' => $item->getKey(), 'quantity' => 3, 'unit_of_measure' => 'fake', 'item_name' => 'fake']],
    ])->assertSessionHasNoErrors();

    $line = SupplyRequest::query()->sole()->lines()->sole();
    expect($line->inventory_item_id)->toBe($item->getKey())
        ->and($line->item_name)->toBe('Paper')
        ->and($line->unit_of_measure)->toBe('ream')
        ->and($line->is_new_item)->toBeFalse()
        ->and($line->quantity_requested)->toBe(3);
});

test('invalid forms do not consume ISF numbers', function () {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->post(route('inventory.requests.store'), [
        ...issuanceFormPayload(),
        'purpose' => '',
        'responsibility_center_code' => 'Unverified account',
        'lines' => [['quantity' => 0, 'unit_of_measure' => '', 'item_name' => '']],
    ])->assertSessionHasErrors(['purpose', 'responsibility_center_code', 'lines.0.quantity', 'lines.0.unit_of_measure', 'lines.0.item_name']);
    expect(DB::table('issuance_request_sequences')->count())->toBe(0);
    $this->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasNoErrors();
    expect(SupplyRequest::query()->sole()->isf_sequence)->toBe(1);
});

test('the daily number limit rolls back creation and preserves the counter', function () {
    $user = User::factory()->employee()->create();
    DB::table('issuance_request_sequences')->insert(['date' => now('Asia/Manila')->toDateString(), 'last_number' => 999]);
    $this->actingAs($user)->post(route('inventory.requests.store'), issuanceFormPayload())->assertSessionHasErrors('issuance_form');
    expect(SupplyRequest::query()->count())->toBe(0)
        ->and(DB::table('issuance_request_sequences')->value('last_number'))->toBe(999);
});

test('the issuance form exposes automatic context and pending finance options', function () {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->where('issuanceContext.requested_by', $user->name)
        ->where('issuanceContext.department', null)
        ->where('issuanceContext.checked_by', null)
        ->where('issuanceContext.approved_by', null)
        ->has('responsibilityCenters', 0));
});

test('ISF previews show the next daily number without reserving it', function (?int $lastNumber, ?string $expected) {
    $this->travelTo(new DateTimeImmutable('2026-10-06 10:00:00 UTC'));
    $user = User::factory()->employee()->create();
    DB::table('issuance_request_sequences')->insert(['date' => '2026-10-05', 'last_number' => 42]);
    if ($lastNumber !== null) {
        DB::table('issuance_request_sequences')->insert(['date' => '2026-10-06', 'last_number' => $lastNumber]);
    }

    $this->actingAs($user)->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->where('issuanceContext.date', '2026-10-06')
        ->where('issuanceContext.isf_no', $expected));

    expect(DB::table('issuance_request_sequences')->where('date', '2026-10-06')->value('last_number'))->toBe($lastNumber)
        ->and(SupplyRequest::query()->count())->toBe(0);
})->with([
    'first request today' => [null, '10-06-001'],
    'next request today' => [17, '10-06-018'],
    'daily limit' => [999, null],
]);
