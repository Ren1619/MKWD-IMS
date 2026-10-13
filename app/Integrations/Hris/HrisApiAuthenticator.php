<?php

namespace App\Integrations\Hris;

use App\Contracts\HrisAuthenticator;
use App\Models\HrisIntegrationSetting;
use App\Services\HrisEndpointGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HrisApiAuthenticator implements HrisAuthenticator
{
    public function __construct(private HrisEndpointGuard $endpointGuard) {}

    public function authenticate(string $email, string $password): HrisAuthenticationResult
    {
        $path = config('services.hris.auth_path');

        if (! is_string($path) || $path === '') {
            return HrisAuthenticationResult::unavailable();
        }

        try {
            $response = $this->request()->post($path, [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            return HrisAuthenticationResult::unavailable();
        }

        if ($response->serverError() || $response->status() === 204) {
            return HrisAuthenticationResult::unavailable();
        }

        if ($response->clientError()) {
            return HrisAuthenticationResult::rejected();
        }

        if (! $response->successful()) {
            return HrisAuthenticationResult::unavailable();
        }

        $employeeId = $response->json('data.employee_id');
        $accountType = $response->json('data.account_type');
        $isActive = $response->json('data.is_active');
        $roles = $response->json('data.roles');

        if ($isActive === false) {
            return HrisAuthenticationResult::rejected();
        }

        if ($isActive !== true) {
            return HrisAuthenticationResult::unavailable();
        }

        if (! is_string($accountType) || $accountType === '') {
            $accountType = 'employee';
        }

        if (! in_array($accountType, ['employee', 'super_admin'], true)) {
            return HrisAuthenticationResult::rejected();
        }

        if (! is_array($roles) || ! array_is_list($roles)) {
            return HrisAuthenticationResult::unavailable();
        }

        $normalizedRoles = [];

        foreach ($roles as $role) {
            if (! is_string($role) || trim($role) === '') {
                return HrisAuthenticationResult::unavailable();
            }

            $normalizedRole = trim($role);

            if (! in_array($normalizedRole, $normalizedRoles, true)) {
                $normalizedRoles[] = $normalizedRole;
            }
        }

        $normalizedEmployeeId = null;

        if (is_string($employeeId) || is_int($employeeId)) {
            $normalizedEmployeeId = trim((string) $employeeId);
            $normalizedEmployeeId = $normalizedEmployeeId === '' ? null : $normalizedEmployeeId;
        }

        if ($accountType === 'employee' && $normalizedEmployeeId === null) {
            return HrisAuthenticationResult::unavailable();
        }

        $name = collect([
            $response->json('data.first_name'),
            $response->json('data.middle_name'),
            $response->json('data.last_name'),
            $response->json('data.name_extension'),
        ])->filter(fn (mixed $part): bool => is_string($part) && trim($part) !== '')
            ->map(fn (string $part): string => trim($part))
            ->implode(' ');

        return HrisAuthenticationResult::authenticated(
            $normalizedEmployeeId,
            $accountType,
            $name === '' ? null : $name,
            $normalizedRoles,
        );
    }

    private function request(): PendingRequest
    {
        $baseUrl = HrisIntegrationSetting::configuredBaseUrl();

        if (! is_string($baseUrl) || $baseUrl === '' || $this->endpointGuard->validationError($baseUrl) !== null) {
            throw new ConnectionException('The HRIS authentication endpoint is not configured safely.');
        }

        $request = Http::baseUrl($this->endpointGuard->normalize($baseUrl))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.hris.auth_connect_timeout', 3))
            ->timeout((int) config('services.hris.auth_timeout', 8))
            ->withOptions(['allow_redirects' => false]);

        $token = config('services.hris.auth_token');

        if (! is_string($token) || $token === '') {
            throw new ConnectionException('The HRIS authentication token is not configured.');
        }

        return $request->withToken($token);
    }
}
