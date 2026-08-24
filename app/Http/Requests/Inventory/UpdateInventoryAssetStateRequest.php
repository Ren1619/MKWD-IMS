<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryAssetStateRequest extends FormRequest
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
            'lifecycle_status' => ['required', Rule::enum(AssetLifecycleStatus::class)],
            'condition_status' => ['required', Rule::enum(AssetConditionStatus::class)],
        ];
    }
}
