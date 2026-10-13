<?php

namespace App\Integrations\Hris;

use App\HrisAuthenticationStatus;

readonly class HrisAuthenticationResult
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public HrisAuthenticationStatus $status,
        public ?string $employeeId = null,
        public ?string $accountType = null,
        public ?string $name = null,
        public array $roles = [],
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public static function authenticated(
        ?string $employeeId,
        string $accountType = 'employee',
        ?string $name = null,
        array $roles = [],
    ): self {
        return new self(HrisAuthenticationStatus::Authenticated, $employeeId, $accountType, $name, $roles);
    }

    public static function rejected(): self
    {
        return new self(HrisAuthenticationStatus::Rejected);
    }

    public static function unavailable(): self
    {
        return new self(HrisAuthenticationStatus::Unavailable);
    }
}
