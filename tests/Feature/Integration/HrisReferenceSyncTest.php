<?php

use App\Actions\Integrations\SyncHrisReferences;
use App\Contracts\HrisReferenceSource;
use App\Models\HrisReference;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.hris.url', 'https://hris.example.test');
    config()->set('services.hris.token', 'test-token');
    config()->set('services.hris.employees_path', '/api/v1/inventory/employees');
    config()->set('services.hris.reference_data_path', '/api/v1/inventory/reference-data');
    Http::preventStrayRequests();
});

test('employees and reference catalogs are received through the configured API', function () {
    config()->set('services.hris.url', 'https://hris.example.test');
    config()->set('services.hris.token', 'test-token');

    Http::preventStrayRequests();
    Http::fake([
        'https://hris.example.test/api/v1/inventory/employees*' => Http::response(['data' => [[
            'employee_id' => 'emp-100',
            'first_name' => 'Ana',
            'middle_name' => 'Cruz',
            'last_name' => 'Reyes',
            'name_extension' => 'Jr.',
            'email' => 'ana@example.test',
            'phone_number' => '09123456789',
            'position' => [
                'id' => 42,
                'name' => 'Engineer',
                'classification' => 'Permanent',
                'department' => ['id' => 1, 'name' => 'Operations'],
                'division' => ['id' => 2, 'name' => 'Engineering'],
                'section' => ['id' => 3, 'name' => 'Maintenance'],
            ],
        ]], 'meta' => ['next_cursor' => null]]),
        'https://hris.example.test/api/v1/inventory/reference-data' => Http::response(hrisReferenceDataPayload([
            'departments' => [['id' => 1, 'name' => 'Operations', 'acronym' => 'OPS']],
            'divisions' => [['id' => 2, 'department_id' => 1, 'name' => 'Engineering', 'acronym' => 'ENG']],
            'units' => [['id' => 3, 'division_id' => 2, 'name' => 'Maintenance', 'acronym' => 'MNT']],
            'roles' => [['id' => 4, 'name' => 'department_head']],
        ])),
    ]);

    $result = app(SyncHrisReferences::class)->handle();

    expect($result)->toMatchArray(['created' => 5, 'total' => 5])
        ->and(HrisReference::query()->where('external_id', 'emp-100')->value('name'))->toBe('Ana Cruz Reyes Jr.')
        ->and(HrisReference::query()->where('type', HrisReference::TYPE_DEPARTMENT)->value('code'))->toBe('OPS')
        ->and(HrisReference::query()->where('type', HrisReference::TYPE_DIVISION)->value('parent_external_id'))->toBe('1')
        ->and(HrisReference::query()->where('type', HrisReference::TYPE_UNIT)->value('parent_external_id'))->toBe('2')
        ->and(HrisReference::query()->where('type', HrisReference::TYPE_ROLE)->value('name'))->toBe('department_head');

    $reference = HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->sole();
    expect($reference->code)->toBe('emp-100')
        ->and($reference->email)->toBe('ana@example.test')
        ->and($reference->metadata['phone_number'])->toBe('09123456789')
        ->and($reference->metadata['position']['name'])->toBe('Engineer')
        ->and($reference->metadata['position']['section']['name'])->toBe('Maintenance');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Accept', 'application/json')
        && isset($request['per_page'])
        && $request['per_page'] === 250);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hris.example.test/api/v1/inventory/reference-data'
        && $request->hasHeader('Authorization', 'Bearer test-token'));
    Http::assertSentCount(2);
});

test('cursor pages are fully synchronized using employee_id even when other ids change', function () {
    $existing = HrisReference::factory()->create(['external_id' => '0001']);
    $missing = HrisReference::factory()->create();

    Http::fakeSequence()
        ->push(['data' => [hrisEmployeePayload('0001', ['id' => 10])], 'meta' => ['next_cursor' => 'next+/=']])
        ->push(['data' => [hrisEmployeePayload('0002')], 'meta' => ['next_cursor' => null]])
        ->push(hrisReferenceDataPayload())
        ->push(['data' => [hrisEmployeePayload('0001', ['id' => 99, 'first_name' => 'Updated'])], 'meta' => ['next_cursor' => 'next+/=']])
        ->push(['data' => [hrisEmployeePayload('0002')], 'meta' => ['next_cursor' => null]])
        ->push(hrisReferenceDataPayload());

    expect(app(SyncHrisReferences::class)->handle())->toMatchArray([
        'created' => 1, 'updated' => 1, 'deactivated' => 1, 'total' => 2,
    ]);

    $this->artisan('ims:sync-hris')->assertSuccessful();

    expect($existing->fresh()->name)->toBe('Updated Reyes')
        ->and($existing->fresh()->is_active)->toBeTrue()
        ->and($missing->fresh()->is_active)->toBeFalse()
        ->and(HrisReference::query()->count())->toBe(3);

    Http::assertSent(fn (Request $request): bool => isset($request['cursor'])
        && $request['cursor'] === 'next+/='
        && $request['per_page'] === 250);
    Http::assertSentCount(6);
});

test('a failure on a later page leaves all cached references unchanged', function () {
    $existing = HrisReference::factory()->create(['external_id' => '0001', 'name' => 'Original Name']);
    $missing = HrisReference::factory()->create();

    Http::fakeSequence()
        ->push(['data' => [hrisEmployeePayload('0001')], 'meta' => ['next_cursor' => 'page2']])
        ->pushStatus(503)->pushStatus(503)->pushStatus(503);

    expect(fn () => app(SyncHrisReferences::class)->handle())->toThrow(RequestException::class);
    expect($existing->fresh()->name)->toBe('Original Name')
        ->and($missing->fresh()->is_active)->toBeTrue();
    Http::assertSentCount(4);
});

test('transient HRIS failures are retried', function () {
    Http::fakeSequence()->pushStatus(503)
        ->push(['data' => [hrisEmployeePayload('0001')], 'meta' => ['next_cursor' => null]])
        ->push(hrisReferenceDataPayload());

    expect(app(SyncHrisReferences::class)->handle()['created'])->toBe(1);
    Http::assertSentCount(3);
});

test('malformed responses do not modify cached employees', function (array $payload) {
    $existing = HrisReference::factory()->create();
    Http::fakeSequence()->push($payload);

    expect(fn () => app(SyncHrisReferences::class)->handle())->toThrow(LogicException::class);
    expect($existing->fresh()->is_active)->toBeTrue()
        ->and(HrisReference::query()->count())->toBe(1);
})->with([
    'missing data' => [['meta' => ['next_cursor' => null]]],
    'missing cursor metadata' => [['data' => []]],
    'invalid record' => [['data' => [null], 'meta' => ['next_cursor' => null]]],
    'id is not an employee_id' => [['data' => [['id' => 1, 'first_name' => 'Ana']], 'meta' => ['next_cursor' => null]]],
    'invalid cursor' => [['data' => [], 'meta' => ['next_cursor' => []]]],
]);

test('repeated cursors abort without modifying cached employees', function () {
    $existing = HrisReference::factory()->create();
    Http::fakeSequence()
        ->push(['data' => [hrisEmployeePayload('0001')], 'meta' => ['next_cursor' => 'same']])
        ->push(['data' => [], 'meta' => ['next_cursor' => 'same']]);

    expect(fn () => app(SyncHrisReferences::class)->handle())->toThrow(LogicException::class);
    expect($existing->fresh()->is_active)->toBeTrue()
        ->and(HrisReference::query()->count())->toBe(1);
    Http::assertSentCount(2);
});

test('valid empty snapshots deactivate all supported reference types', function () {
    $employee = HrisReference::factory()->create();
    $department = HrisReference::factory()->create(['type' => HrisReference::TYPE_DEPARTMENT]);
    Http::fakeSequence()
        ->push(['data' => [], 'meta' => ['next_cursor' => null]])
        ->push(hrisReferenceDataPayload());

    expect(app(SyncHrisReferences::class)->handle())->toMatchArray(['total' => 0, 'deactivated' => 2]);
    expect($employee->fresh()->is_active)->toBeFalse()
        ->and($department->fresh()->is_active)->toBeFalse();
});

test('a malformed reference data response leaves the entire cache unchanged', function () {
    $employee = HrisReference::factory()->create(['name' => 'Original Employee']);
    $department = HrisReference::factory()->department()->create(['name' => 'Original Department']);

    Http::fakeSequence()
        ->push(['data' => [hrisEmployeePayload($employee->external_id, ['first_name' => 'Updated'])], 'meta' => ['next_cursor' => null]])
        ->push(['data' => ['departments' => []]]);

    expect(fn () => app(SyncHrisReferences::class)->handle())->toThrow(LogicException::class);
    expect($employee->fresh()->name)->toBe('Original Employee')
        ->and($department->fresh()->name)->toBe('Original Department')
        ->and($department->fresh()->is_active)->toBeTrue();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hrisEmployeePayload(string $employeeId, array $overrides = []): array
{
    return array_replace([
        'employee_id' => $employeeId,
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'name_extension' => null,
        'email' => null,
        'phone_number' => null,
        'position' => null,
    ], $overrides);
}

/**
 * @param  array<string, list<array<string, mixed>>>  $overrides
 * @return array{data: array<string, list<array<string, mixed>>>}
 */
function hrisReferenceDataPayload(array $overrides = []): array
{
    return [
        'data' => array_replace([
            'departments' => [],
            'divisions' => [],
            'units' => [],
            'roles' => [],
        ], $overrides),
    ];
}

test('a complete sync updates present references and deactivates missing ones', function () {
    $source = new class implements HrisReferenceSource
    {
        public array $records = [];

        public function references(): array
        {
            return $this->records;
        }
    };

    $source->records = [referencePayload('emp-1', 'First Name'), referencePayload('emp-2', 'Second Name')];
    app()->instance(HrisReferenceSource::class, $source);
    app(SyncHrisReferences::class)->handle();

    $source->records = [referencePayload('emp-1', 'Updated Name')];
    $result = app(SyncHrisReferences::class)->handle();

    expect($result['deactivated'])->toBe(1)
        ->and(HrisReference::query()->where('external_id', 'emp-1')->value('name'))->toBe('Updated Name')
        ->and(HrisReference::query()->where('external_id', 'emp-2')->value('is_active'))->toBeFalse();
});

/** @return array<string, mixed> */
function referencePayload(string $externalId, string $name): array
{
    return [
        'external_id' => $externalId,
        'type' => HrisReference::TYPE_EMPLOYEE,
        'code' => $externalId,
        'name' => $name,
        'email' => null,
        'parent_external_id' => null,
        'metadata' => [],
        'is_active' => true,
        'source_updated_at' => now(),
    ];
}
