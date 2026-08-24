<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAssetIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'records' => ['nullable', Rule::in(['active', 'archived'])],
            'lifecycle_status' => ['nullable', Rule::enum(AssetLifecycleStatus::class)],
            'condition_status' => ['nullable', Rule::enum(AssetConditionStatus::class)],
            'custody_status' => ['nullable', Rule::enum(AssetCustodyStatus::class)],
        ];
    }
}
