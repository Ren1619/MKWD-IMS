<?php

namespace App\Http\Requests\Settings;

use App\Rules\SafeHrisApiUrl;
use App\Services\HrisEndpointGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateHrisIntegrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $baseUrl = $this->input('base_url');

        if (is_string($baseUrl)) {
            $this->merge([
                'base_url' => Str::of($baseUrl)->trim()->rtrim('/')->toString(),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-integrations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(HrisEndpointGuard $endpointGuard): array
    {
        return [
            'base_url' => ['required', 'string', 'max:2048', 'url:https', new SafeHrisApiUrl($endpointGuard)],
        ];
    }
}
