<?php

namespace App\Rules;

use App\Services\HrisEndpointGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafeHrisApiUrl implements ValidationRule
{
    public function __construct(private HrisEndpointGuard $endpointGuard) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = $this->endpointGuard->validationError($value);

        if ($message !== null) {
            $fail($message);
        }
    }
}
