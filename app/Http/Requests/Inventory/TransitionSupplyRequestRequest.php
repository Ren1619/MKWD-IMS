<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'review', 'release', 'reject', 'cancel'])], 'attested' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'procurement_mode' => ['nullable', 'string', 'max:100'],
        ];
    }
}
