<?php

use App\Actions\Integrations\SyncHrisReferences;
use App\Contracts\HrisReferenceSource;
use App\Models\HrisReference;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('employee references are received through the configured API', function () {
    config()->set('services.hris.base_url', 'https://hris.example.test');
    config()->set('services.hris.token', 'test-token');

    Http::preventStrayRequests();
    Http::fake([
        'https://hris.example.test/api/v1/employees' => Http::response(['data' => [[
            'id' => 'emp-100',
            'employee_number' => '2026-0100',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.test',
            'active' => true,
            'updated_at' => '2026-08-10T08:00:00+08:00',
        ]]]),
    ]);

    $result = app(SyncHrisReferences::class)->handle();

    expect($result)->toMatchArray(['created' => 1, 'total' => 1])
        ->and(HrisReference::query()->where('external_id', 'emp-100')->value('name'))->toBe('Ana Reyes')
        ->and(HrisReference::query()->where('type', '!=', HrisReference::TYPE_EMPLOYEE)->exists())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
});

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
