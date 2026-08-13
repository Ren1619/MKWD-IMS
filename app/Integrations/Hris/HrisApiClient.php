<?php

namespace App\Integrations\Hris;

use App\Contracts\HrisReferenceSource;
use App\Models\HrisReference;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;

class HrisApiClient implements HrisReferenceSource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function references(): array
    {
        return [
            ...$this->fetch(config('services.hris.employees_path'), HrisReference::TYPE_EMPLOYEE),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(?string $path, ?string $defaultType = null): array
    {
        if (! is_string($path) || $path === '') {
            throw new LogicException('The HRIS API paths are not configured.');
        }

        $payload = $this->request()->get($path)->throw()->json('data', []);

        if (! is_array($payload)) {
            throw new LogicException('The HRIS API response must contain a data array.');
        }

        return collect($payload)
            ->filter(fn (mixed $record): bool => is_array($record))
            ->map(fn (array $record): array => $this->normalize($record, $defaultType))
            ->values()
            ->all();
    }

    private function request(): PendingRequest
    {
        $baseUrl = config('services.hris.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new LogicException('HRIS_API_BASE_URL is not configured.');
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout((int) config('services.hris.connect_timeout', 3))
            ->timeout((int) config('services.hris.timeout', 10))
            ->retry([100, 500, 1000]);

        $token = config('services.hris.token');

        return is_string($token) && $token !== '' ? $request->withToken($token) : $request;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalize(array $record, ?string $defaultType): array
    {
        $type = Str::of((string) ($record['type'] ?? $defaultType))->lower()->singular()->toString();
        $externalId = (string) ($record['id'] ?? $record['external_id'] ?? '');
        $name = trim((string) ($record['name'] ?? $record['full_name'] ?? ''));

        if ($name === '' && $type === HrisReference::TYPE_EMPLOYEE) {
            $name = trim(implode(' ', array_filter([
                $record['first_name'] ?? null,
                $record['middle_name'] ?? null,
                $record['last_name'] ?? null,
                $record['name_suffix'] ?? null,
            ])));
        }

        if ($externalId === '' || $name === '') {
            throw new LogicException('Every HRIS reference requires an id and name.');
        }

        return [
            'external_id' => $externalId,
            'type' => $type,
            'code' => $record['code'] ?? $record['employee_number'] ?? null,
            'name' => $name,
            'email' => $record['email'] ?? null,
            'parent_external_id' => isset($record['parent_id']) ? (string) $record['parent_id'] : null,
            'is_active' => (bool) ($record['is_active'] ?? $record['active'] ?? true),
            'source_updated_at' => $record['updated_at'] ?? null,
            'metadata' => Arr::except($record, [
                'id',
                'external_id',
                'type',
                'code',
                'employee_number',
                'name',
                'full_name',
                'email',
                'parent_id',
                'is_active',
                'active',
                'updated_at',
            ]),
        ];
    }
}
