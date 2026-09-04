<?php

namespace App\Http\Requests\Inventory;

use App\AssetAccountingClassification;
use App\Models\InventoryAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInventoryAssetRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:inv_asset_cats,inv_asset_cat_id'],
            'subcategory_id' => [
                'required',
                'integer',
                Rule::exists('inventory_asset_subcategories', 'inventory_asset_subcategory_id')
                    ->where('inventory_asset_category_id', $this->integer('category_id')),
            ],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('inventory_assets', 'serial_number')->ignore($this->route('asset'))],
            'property_number' => ['nullable', 'string', 'max:100', Rule::unique('inventory_assets', 'property_number')->ignore($this->route('asset'))],
            'name' => ['required', 'string', 'max:255'],
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
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $asset = $this->route('asset');

            if (! $asset instanceof InventoryAsset) {
                return;
            }

            $nextClassification = AssetAccountingClassification::fromAcquisitionCost(
                $this->input('acquisition_cost'),
            );

            if (
                $nextClassification !== $asset->accounting_classification
                && $asset->accountabilityDocuments()->whereIn('status', ['pending_recipient', 'active'])->exists()
            ) {
                $validator->errors()->add(
                    'acquisition_cost',
                    'Return or supersede the current PAR/ICS before changing this asset across the PHP 50,000 threshold.',
                );
            }
        }];
    }

    private function isPpe(): bool
    {
        return (float) $this->input('acquisition_cost') >= AssetAccountingClassification::CAPITALIZATION_THRESHOLD;
    }
}
