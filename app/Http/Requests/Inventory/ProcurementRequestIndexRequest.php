<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementRequestIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'draft', 'for_budget_review', 'for_approval', 'approved',
                'forwarded_to_procurement', 'ordered', 'delivered', 'accepted',
                'rejected', 'cancelled',
            ])],
            'queue' => ['nullable', Rule::in(['needs_action', 'completed'])],
        ];
    }
}
