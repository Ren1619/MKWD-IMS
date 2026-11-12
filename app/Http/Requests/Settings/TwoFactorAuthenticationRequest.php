<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends ValidatedFormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
