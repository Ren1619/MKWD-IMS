<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableValueClass;
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
            'value_class' => ['nullable', Rule::enum(SemiExpendableValueClass::class)],
            'accountability_status' => ['nullable', Rule::enum(SemiExpendableAccountabilityStatus::class)],
            'physical_status' => ['nullable', Rule::enum(SemiExpendablePhysicalStatus::class)],
            'ics_renewal' => ['nullable', Rule::in(['due_soon', 'overdue'])],
            'sort' => ['nullable', Rule::in(['inventory_asset_id', 'property_number', 'name', 'acquisition_date', 'acquisition_cost', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
