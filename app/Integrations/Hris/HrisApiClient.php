<?php

namespace App\Integrations\Hris;

use App\Contracts\HrisReferenceSource;
use App\Models\HrisIntegrationSetting;
use App\Models\HrisReference;
use App\Services\HrisEndpointGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use LogicException;

class HrisApiClient implements HrisReferenceSource
{
    public function __construct(private HrisEndpointGuard $endpointGuard) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function references(): array
    {
        return $this->fetch(config('services.hris.employees_path'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(?string $path): array
    {
        if (! is_string($path) || $path === '') {
            throw new LogicException('The HRIS API paths are not configured.');
        }

        $request = $this->request();
        $references = [];
        $cursor = null;
        $seenCursors = [];

        do {
            $payload = $request->get($path, [
                'per_page' => 250,
                'cursor' => $cursor,
            ])->throw()->json();

            if (! is_array($payload) || ! isset($payload['data'])
                || ! is_array($payload['data']) || ! array_is_list($payload['data'])) {
                throw new LogicException('The HRIS API response must contain a data array.');
            }

            if (! isset($payload['meta']) || ! is_array($payload['meta'])
                || ! array_key_exists('next_cursor', $payload['meta'])) {
                throw new LogicException('The HRIS API response must contain meta.next_cursor.');
            }

            foreach ($payload['data'] as $record) {
                if (! is_array($record)) {
                    throw new LogicException('Every HRIS employee must be an object.');
                }

                $references[] = $this->normalize($record);
            }

            $cursor = $payload['meta']['next_cursor'];

            if ($cursor !== null) {
                if (! is_string($cursor) || $cursor === '' || in_array($cursor, $seenCursors, true)) {
                    throw new LogicException('The HRIS API returned an invalid or repeated cursor.');
                }

                $seenCursors[] = $cursor;
            }
        } while ($cursor !== null);

        return $references;
    }

    private function request(): PendingRequest
    {
        $baseUrl = HrisIntegrationSetting::configuredBaseUrl();

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new LogicException('HRIS_API_URL is not configured.');
        }

        $validationError = $this->endpointGuard->validationError($baseUrl);

        if ($validationError !== null) {
            throw new LogicException("The configured HRIS API URL is unsafe: {$validationError}");
        }

        $request = Http::baseUrl($this->endpointGuard->normalize($baseUrl))
            ->acceptJson()
            ->connectTimeout((int) config('services.hris.connect_timeout', 3))
            ->timeout((int) config('services.hris.timeout', 15))
            ->retry(3, 250)
            ->withOptions(['allow_redirects' => false]);

        $token = config('services.hris.token');

        return is_string($token) && $token !== '' ? $request->withToken($token) : $request;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalize(array $record): array
    {
        $employeeId = $record['employee_id'] ?? null;

        if ((! is_string($employeeId) && ! is_int($employeeId)) || trim((string) $employeeId) === '') {
            throw new LogicException('Every HRIS employee requires an employee_id.');
        }

        $name = trim(implode(' ', array_filter([
            $record['first_name'] ?? null,
            $record['middle_name'] ?? null,
            $record['last_name'] ?? null,
            $record['name_extension'] ?? null,
        ])));

        if ($name === '') {
            throw new LogicException('Every HRIS employee requires a name.');
        }

        return [
            'external_id' => (string) $employeeId,
            'type' => HrisReference::TYPE_EMPLOYEE,
            'code' => (string) $employeeId,
            'name' => $name,
            'email' => $record['email'] ?? null,
            'parent_external_id' => null,
            'is_active' => true,
            'source_updated_at' => null,
            'metadata' => Arr::only($record, [
                'employee_id',
                'first_name',
                'middle_name',
                'last_name',
                'name_extension',
                'phone_number',
                'position',
                'id',
                'name',
                'classification',
                'department',
                'division',
                'section',
            ]),
        ];
    }
}
