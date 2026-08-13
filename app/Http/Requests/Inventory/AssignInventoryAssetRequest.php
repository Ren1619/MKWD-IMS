<?php

namespace App\Http\Requests\Inventory;

use App\Models\HrisReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignInventoryAssetRequest extends FormRequest
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
            'hris_reference_id' => [
                'required',
                'integer',
                Rule::exists('hris_references', 'id')->where(fn ($query) => $query
                    ->where('type', HrisReference::TYPE_EMPLOYEE)
                    ->where('is_active', true)),
            ],
        ];
    }
}
