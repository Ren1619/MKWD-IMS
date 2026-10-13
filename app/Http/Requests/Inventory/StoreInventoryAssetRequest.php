<?php

namespace App\Http\Requests\Inventory;

use App\AssetAccountingClassification;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\Models\InventoryAssetCategory;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryAssetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('acquisition_method')) {
            $this->merge(['acquisition_method' => 'purchase']);
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
        return [
            'category_id' => ['required', 'integer', Rule::exists('inv_asset_cats', 'inv_asset_cat_id')->where('is_active', true)],
            'subcategory_id' => [
                'required',
                'integer',
                Rule::exists('inventory_asset_subcategories', 'inventory_asset_subcategory_id')
                    ->where('inventory_asset_category_id', $this->integer('category_id'))
                    ->where('is_active', true),
            ],
            'serial_number' => [Rule::prohibitedIf($this->integer('registration_quantity', 1) > 1), 'nullable', Rule::requiredIf($this->integer('registration_quantity', 1) === 1 && blank($this->input('property_number'))), 'string', 'max:100', 'unique:inventory_assets,serial_number'],
            'property_number' => [Rule::prohibitedIf($this->integer('registration_quantity', 1) > 1), 'nullable', Rule::requiredIf($this->integer('registration_quantity', 1) === 1 && blank($this->input('serial_number'))), 'string', 'max:100', 'unique:inventory_assets,property_number'],
            'registration_quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'property_number_prefix' => [Rule::requiredIf($this->integer('registration_quantity', 1) > 1), 'nullable', 'string', 'max:90', 'regex:/^[A-Za-z0-9._-]+$/'],
            'old_property_number' => ['nullable', 'string', 'max:100'],
            'account_code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'fund_cluster' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'specifications' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_method' => ['required', Rule::in(['purchase', 'donation', 'transfer', 'construction', 'other'])],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'purchase_order_no' => ['nullable', 'string', 'max:120'],
            'inspection_acceptance_report_no' => ['nullable', 'string', 'max:120'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'disbursement_voucher_no' => ['nullable', 'string', 'max:120'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'inspection_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'acceptance_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'acquisition_cost' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999999.99',
            ],
            'directly_attributable_costs' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'available_for_use_date' => [
                Rule::requiredIf(fn (): bool => $this->isDepreciablePpe()),
                'nullable',
                'date',
                'after_or_equal:acquisition_date',
            ],
            'residual_value_percentage' => [
                Rule::requiredIf(fn (): bool => $this->isDepreciablePpe()),
                'nullable',
                'numeric',
                'min:0',
                'max:99.99',
            ],
            'residual_value_basis' => [
                Rule::requiredIf(fn (): bool => $this->isDepreciablePpe()),
                'nullable',
                'string',
                'max:1000',
            ],
            'depreciation_useful_life_months' => [
                Rule::requiredIf(fn (): bool => ! $this->isPpeRegistry() || $this->isDepreciablePpe()),
                'nullable',
                'integer',
                'min:1',
                'max:1200',
            ],
            'useful_life_override_reason' => ['nullable', 'string', 'max:1000'],
            'lifecycle_status' => ['required', Rule::enum(AssetLifecycleStatus::class)],
            'condition_status' => ['required', Rule::enum(AssetConditionStatus::class)],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $totalCost = BigDecimal::of((string) $this->input('acquisition_cost', 0))
                ->plus((string) $this->input('directly_attributable_costs', 0));
            $threshold = BigDecimal::of(AssetAccountingClassification::capitalizationThreshold());
            $classificationMatches = $this->isPpeRegistry()
                ? $totalCost->isGreaterThanOrEqualTo($threshold)
                : $totalCost->isLessThan($threshold);

            if (! $classificationMatches) {
                $validator->errors()->add(
                    'acquisition_cost',
                    $this->isPpeRegistry()
                        ? 'The total recognized cost must meet the PPE capitalization threshold.'
                        : 'The total recognized cost must be below the PPE capitalization threshold.',
                );
            }

            if ($this->isPpeRegistry() && $this->integer('registration_quantity', 1) > 1) {
                $validator->errors()->add('registration_quantity', 'Batch registration is currently available only for semi-expendable property.');
            }

            if (! $this->isPpeRegistry()) {
                $defaultUsefulLife = InventoryAssetCategory::query()
                    ->whereKey($this->integer('category_id'))
                    ->value('default_useful_life_months');

                if ($defaultUsefulLife !== null
                    && $this->integer('depreciation_useful_life_months') !== (int) $defaultUsefulLife
                    && blank($this->input('useful_life_override_reason'))) {
                    $validator->errors()->add('useful_life_override_reason', 'Explain why the category useful-life default is being overridden.');
                }
            }

            $delivery = $this->date('delivery_date');
            $inspection = $this->date('inspection_date');
            $acceptance = $this->date('acceptance_date');

            if ($delivery && $inspection && $inspection->lt($delivery)) {
                $validator->errors()->add('inspection_date', 'The inspection date cannot be earlier than delivery.');
            }

            if ($inspection && $acceptance && $acceptance->lt($inspection)) {
                $validator->errors()->add('acceptance_date', 'The acceptance date cannot be earlier than inspection.');
            }
        }];
    }

    private function isDepreciablePpe(): bool
    {
        if (! $this->isPpeRegistry()) {
            return false;
        }

        return InventoryAssetCategory::query()
            ->whereKey($this->integer('category_id'))
            ->value('is_depreciable') ?? true;
    }

    private function isPpeRegistry(): bool
    {
        return $this->routeIs('inventory.assets.store');
    }
}
