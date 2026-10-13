<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryAssetAccountingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $asset = $this->route('asset');

        if ($asset instanceof InventoryAsset && ! $this->exists('depreciation_useful_life_months')) {
            $this->merge([
                'depreciation_useful_life_months' => $asset->depreciation_useful_life_months,
            ]);
        }
    }

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
        $asset = $this->route('asset');
        $acquisitionDate = $asset instanceof InventoryAsset
            ? $asset->acquisition_date->toDateString()
            : today()->toDateString();

        return [
            'available_for_use_date' => [
                'required',
                'date',
                'after_or_equal:'.$acquisitionDate,
            ],
            'residual_value_percentage' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'residual_value_basis' => ['required', 'string', 'max:1000'],
            'depreciation_useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
        ];
    }
}
