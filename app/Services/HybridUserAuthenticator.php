<?php

namespace App\Services;

use App\Contracts\HrisAuthenticator;
use App\HrisAuthenticationStatus;
use App\Models\HrisReference;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HybridUserAuthenticator
{
    public function __construct(private HrisAuthenticator $hrisAuthenticator) {}

    public function authenticate(string $email, string $password): ?User
    {
        if (! config('services.hris.auth_enabled', false)) {
            return $this->authenticateDevelopmentFallback($email, $password);
        }

        $result = $this->hrisAuthenticator->authenticate($email, $password);

        if ($result->status === HrisAuthenticationStatus::Rejected) {
            return null;
        }

        if ($result->status === HrisAuthenticationStatus::Unavailable) {
            return $this->authenticateDevelopmentFallback($email, $password);
        }

        $role = $this->mapHrisRoles($result->roles);

        if ($result->employeeId !== null) {
            return $this->authenticateHrisUser($email, $password, $result->employeeId, $role);
        }

        if ($result->accountType === 'super_admin' && $role === UserRole::SuperAdmin) {
            return $this->authenticateHrisSuperAdmin($email, $password, $result->name, $role);
        }

        return null;
    }

    private function authenticateHrisSuperAdmin(
        string $email,
        string $password,
        ?string $name,
        UserRole $role,
    ): ?User {
        return DB::transaction(function () use ($email, $password, $name, $role): ?User {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user?->hris_reference_id !== null) {
                return null;
            }

            if ($user === null) {
                $user = User::query()->create([
                    'hris_reference_id' => null,
                    'name' => $name ?? $email,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $user->is_active) {
                return null;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'role' => $role,
                'last_hris_authenticated_at' => now(),
            ])->saveQuietly();

            session()->put('ims_authentication_source', 'hris');

            return $user;
        });
    }

    private function authenticateHrisUser(
        string $email,
        string $password,
        string $employeeId,
        UserRole $role,
    ): ?User {
        return DB::transaction(function () use ($email, $password, $employeeId, $role): ?User {
            $employee = HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->where('external_id', $employeeId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($employee === null) {
                return null;
            }

            $linkedUser = User::query()->where('hris_reference_id', $employee->getKey())->first();
            $emailUser = User::query()->where('email', $email)->first();

            if ($linkedUser !== null && $emailUser !== null && ! $linkedUser->is($emailUser)) {
                return null;
            }

            $user = $linkedUser ?? $emailUser;

            if ($user !== null
                && $user->hris_reference_id !== null
                && $user->hris_reference_id !== $employee->getKey()) {
                return null;
            }

            if ($user === null) {
                $user = User::query()->create([
                    'hris_reference_id' => $employee->getKey(),
                    'name' => $employee->name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $user->is_active) {
                return null;
            }

            $user->forceFill([
                'hris_reference_id' => $employee->getKey(),
                'password' => Hash::make($password),
                'role' => $role,
                'last_hris_authenticated_at' => now(),
            ])->saveQuietly();

            session()->put('ims_authentication_source', 'hris');

            return $user;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    private function mapHrisRoles(array $roles): UserRole
    {
        if (in_array(UserRole::SuperAdmin->value, $roles, true)) {
            return UserRole::SuperAdmin;
        }

        if (in_array(UserRole::InventoryManager->value, $roles, true)) {
            return UserRole::InventoryManager;
        }

        return UserRole::Employee;
    }

    private function authenticateDevelopmentFallback(string $email, string $password): ?User
    {
        if (! app()->environment(['local', 'testing'])
            || ! config('services.hris.development_fallback_enabled', false)) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user?->is_active
            || ! $user->local_fallback_enabled
            || ! Hash::check($password, $user->password)) {
            return null;
        }

        $user->forceFill(['last_fallback_authenticated_at' => now()])->saveQuietly();
        session()->put('ims_authentication_source', 'development_fallback');

        return $user;
    }
}
