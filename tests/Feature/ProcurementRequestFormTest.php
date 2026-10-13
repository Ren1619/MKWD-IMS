<?php

use App\Models\HrisReference;
use App\Models\ProcurementRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('request forms save goods and service lines with system supplied requisitioner information', function (string $kind) {
    $employee = HrisReference::factory()->create([
        'name' => 'Requisitioner',
        'metadata' => ['position' => ['department' => ['id' => 1, 'name' => 'Operations'], 'division' => ['id' => 2, 'name' => 'Maintenance']]],
    ]);
    $manager = User::factory()->inventoryManager()->create(['hris_reference_id' => $employee->id]);
    HrisReference::factory()->create([
        'name' => 'Division signatory',
        'metadata' => ['position' => ['name' => 'Division Manager', 'division' => ['id' => 2]]],
    ]);
    HrisReference::factory()->create([
        'name' => 'Department signatory',
        'metadata' => ['position' => ['name' => 'Department Manager', 'department' => ['id' => 1]]],
    ]);
    HrisReference::factory()->create([
        'name' => 'Other department signatory',
        'metadata' => ['position' => ['name' => 'Department Manager', 'department' => ['id' => 99]]],
    ]);

    $this->actingAs($manager)->post(route('inventory.procurement.store'), [
        'request_form' => true,
        'document_kind' => $kind,
        'required_at' => '2026-12-31',
        'purpose' => 'Keep the office operational',
        'abc' => '2500.50',
        'charge_account' => '',
        'requested_by' => 'Forged requisitioner',
        'department' => 'Forged department',
        'date' => '2000-01-01',
        'lines' => [
            ['quantity' => 2, 'unit_of_measure' => 'unit', 'item_name' => 'Requested item or service'],
            ['quantity' => 1, 'unit_of_measure' => 'lot', 'item_name' => 'Additional item or service'],
        ],
    ])->assertSessionHasNoErrors();

    $record = ProcurementRequest::query()->sole();
    expect($record->document_kind)->toBe($kind)
        ->and($record->created_by_user_id)->toBe($manager->id)
        ->and($record->required_at->toDateString())->toBe('2026-12-31')
        ->and($record->preprocurement['requested_by'])->toBe('Requisitioner')
        ->and($record->preprocurement['department'])->toBe('Operations')
        ->and($record->preprocurement['division'])->toBe('Maintenance')
        ->and($record->preprocurement['checked_by'])->toBe('Division signatory')
        ->and($record->preprocurement['approved_by'])->toBe('Department signatory')
        ->and($record->preprocurement['date'])->toBe(now()->toDateString())
        ->and($record->preprocurement['abc'])->toBe('2500.50')
        ->and($record->preprocurement['charge_account'])->toBeNull()
        ->and($record->lines()->count())->toBe(2);
})->with(['PR', 'JO']);

test('request forms validate the date budget purpose and item descriptions', function () {
    $manager = User::factory()->inventoryManager()->create();
    $this->actingAs($manager)->post(route('inventory.procurement.store'), [
        'request_form' => true,
        'document_kind' => 'invalid',
        'required_at' => 'invalid',
        'purpose' => '',
        'abc' => -1,
        'charge_account' => 'Unverified account',
        'lines' => [['quantity' => 0, 'unit_of_measure' => '', 'item_name' => '']],
    ])->assertSessionHasErrors(['document_kind', 'required_at', 'purpose', 'abc', 'charge_account', 'lines.0.quantity', 'lines.0.unit_of_measure', 'lines.0.item_name']);
    expect(ProcurementRequest::query()->count())->toBe(0);
});

test('request forms keep inventory permissions and report unavailable system information', function () {
    $employee = User::factory()->employee()->create();
    $this->actingAs($employee)->post(route('inventory.procurement.store'), ['request_form' => true])->assertForbidden();
    $manager = User::factory()->inventoryManager()->create();
    $this->actingAs($manager)->get(route('inventory.procurement.index'))->assertInertia(fn (Assert $page) => $page
        ->where('requestFormContext.requested_by', $manager->name)
        ->where('requestFormContext.department', null)
        ->where('requestFormContext.checked_by', null)
        ->where('requestFormContext.approved_by', null)
        ->has('chargeAccounts', 0));
});
