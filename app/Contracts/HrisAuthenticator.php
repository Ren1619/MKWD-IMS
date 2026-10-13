<?php

namespace App\Contracts;

use App\Integrations\Hris\HrisAuthenticationResult;

interface HrisAuthenticator
{
    public function authenticate(string $email, string $password): HrisAuthenticationResult;
}
