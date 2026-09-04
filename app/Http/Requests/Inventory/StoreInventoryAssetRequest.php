<?php

namespace App\Http\Requests\Inventory;

use App\AssetAccountingClassification;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAssetRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('inv_asset_cats', 'inv_asset_cat_id')->where('is_active', true)],
            'serial_number' => ['required', 'string', 'max:100', 'unique:inventory_assets,serial_number'],
            'property_number' => ['nullable', 'string', 'max:100', 'unique:inventory_assets,property_number'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'available_for_use_date' => [
                Rule::requiredIf(fn (): bool => $this->isPpe()),
                'nullable',
                'date',
                'after_or_equal:acquisition_date',
            ],
            'residual_value_percentage' => [
                Rule::requiredIf(fn (): bool => $this->isPpe()),
                'nullable',
                'numeric',
                'min:5',
                'max:99.99',
            ],
            'residual_value_basis' => [
                Rule::requiredIf(fn (): bool => $this->isPpe()),
                'nullable',
                'string',
                'max:1000',
            ],
            'depreciation_useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'lifecycle_status' => ['required', Rule::enum(AssetLifecycleStatus::class)],
            'condition_status' => ['required', Rule::enum(AssetConditionStatus::class)],
        ];
    }

    private function isPpe(): bool
    {
        return (float) $this->input('acquisition_cost') >= AssetAccountingClassification::CAPITALIZATION_THRESHOLD;
    }
}
