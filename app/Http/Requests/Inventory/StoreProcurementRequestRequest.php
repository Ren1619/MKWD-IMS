<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryAssetSubcategory;
use App\Services\PreprocurementWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProcurementRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        if ($this->isRequestForm()) {
            return [
                'request_form' => ['required', 'accepted'],
                'document_kind' => ['required', Rule::in(['PR', 'JO'])],
                'required_at' => ['required', 'date'],
                'purpose' => ['required', 'string', 'max:2000'],
                'abc' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
                'charge_account' => ['nullable', 'string', Rule::in([])],
                'lines' => ['required', 'array', 'min:1', 'max:50'],
                'lines.*' => ['required', 'array:quantity,unit_of_measure,item_name'],
                'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'lines.*.unit_of_measure' => ['required', 'string', 'max:50'],
                'lines.*.item_name' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'document_kind' => ['required', Rule::in($this->route('document_kind') ? [$this->route('document_kind')] : ['PR', 'JO'])],
            'procurement_category' => ['required', Rule::in(array_keys(PreprocurementWorkflow::categories()))],
            'preprocurement' => ['required', 'array:approved_request_reference,end_user,department,division,is_replacement'],
            'preprocurement.approved_request_reference' => ['required', 'string', 'max:255'],
            'preprocurement.end_user' => ['required', 'string', 'max:255'],
            'preprocurement.department' => ['required', 'string', 'max:255'],
            'preprocurement.division' => ['required', 'string', 'max:255'],
            'preprocurement.is_replacement' => ['required', 'boolean'],
            'supply_request_id' => ['nullable', 'exists:supply_requests,id'], 'type' => ['required', Rule::in(['replenishment', 'new_item', 'mixed'])],
            'source' => ['required', Rule::in(['manual', 'low_stock', 'request_shortage'])], 'purpose' => ['required', 'string', 'max:2000'],
            'funding_source' => ['nullable', 'string', 'max:150'], 'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'ppmp_reference' => ['nullable', 'string', 'max:100'], 'app_reference' => ['nullable', 'string', 'max:100'],
            'app_cse_classification' => ['nullable', 'string', 'max:100'], 'required_at' => ['nullable', 'date'],
            'lines' => [Rule::requiredIf($this->input('document_kind') === 'PR'), 'array', Rule::when($this->input('document_kind') === 'PR', ['min:1']), 'max:50'], 'lines.*.inventory_item_id' => ['nullable', 'exists:inventory_items,inventory_item_id'],
            'lines.*.item_kind' => ['required', Rule::in(['consumable', 'property'])],
            'lines.*.series_category_id' => ['nullable', 'exists:inv_series_cats,inv_series_cat_id'],
            'lines.*.asset_category_id' => ['nullable', 'integer', Rule::exists('inv_asset_cats', 'inv_asset_cat_id')->where('is_active', true)],
            'lines.*.asset_subcategory_id' => ['nullable', 'integer', Rule::exists('inventory_asset_subcategories', 'inventory_asset_subcategory_id')->where('is_active', true)],
            'lines.*.item_name' => ['required', 'string', 'max:255'], 'lines.*.specifications' => ['nullable', 'string', 'max:2000'],
            'lines.*.unit_of_measure' => ['required', 'string', 'max:50'], 'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.estimated_unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isRequestForm()) {
                    return;
                }

                $lines = collect($this->input('lines', []));
                $hasExistingItems = $lines->contains(
                    fn (mixed $line): bool => is_array($line) && filled($line['inventory_item_id'] ?? null),
                );
                $hasNewItems = $lines->contains(
                    fn (mixed $line): bool => is_array($line) && blank($line['inventory_item_id'] ?? null),
                );
                $type = $this->string('type')->toString();

                if ($this->input('document_kind') === 'JO') {
                    if ($lines->isNotEmpty() || filled($this->input('supply_request_id'))) {
                        $validator->errors()->add('lines', 'Job orders use a description of work and cannot contain stock lines or supply-request links.');
                    }

                    return;
                }

                if (filled($this->input('supply_request_id'))) {
                    $validator->errors()->add('supply_request_id', 'Supply shortages are linked automatically by the supply request workflow.');
                }

                if ($type === 'replenishment' && $hasNewItems) {
                    $validator->errors()->add('lines', 'Replenishment requests may only contain existing catalog items.');
                }

                if ($type === 'new_item' && $hasExistingItems) {
                    $validator->errors()->add('lines', 'New item requests may only contain new catalog items.');
                }

                if ($type === 'mixed' && (! $hasExistingItems || ! $hasNewItems)) {
                    $validator->errors()->add('lines', 'Mixed requests must contain at least one existing catalog item and one new catalog item.');
                }

                $lines->each(function (mixed $line, int $index) use ($validator): void {
                    if (! is_array($line)) {
                        return;
                    }

                    $isExisting = filled($line['inventory_item_id'] ?? null);
                    $isProperty = ($line['item_kind'] ?? 'consumable') === 'property';

                    if ($isExisting && $isProperty) {
                        $validator->errors()->add("lines.{$index}.item_kind", 'Existing stock catalog items must remain classified as consumable inventory.');
                    }

                    if (! $isProperty) {
                        return;
                    }

                    if (blank($line['asset_category_id'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.asset_category_id", 'Select a property category.');
                    }

                    if (blank($line['asset_subcategory_id'] ?? null)) {
                        $validator->errors()->add("lines.{$index}.asset_subcategory_id", 'Select a property subcategory.');

                        return;
                    }

                    $subcategoryBelongsToCategory = InventoryAssetSubcategory::query()
                        ->whereKey($line['asset_subcategory_id'])
                        ->where('inventory_asset_category_id', $line['asset_category_id'] ?? null)
                        ->exists();

                    if (! $subcategoryBelongsToCategory) {
                        $validator->errors()->add("lines.{$index}.asset_subcategory_id", 'The selected property subcategory does not belong to the selected category.');
                    }
                });
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isRequestForm()) {
            return;
        }

        $this->merge([
            'lines' => collect($this->input('lines', []))
                ->map(function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    return [...$line, 'item_kind' => $line['item_kind'] ?? 'consumable'];
                })
                ->all(),
        ]);
    }

    public function isRequestForm(): bool
    {
        return $this->isMethod('post') && $this->routeIs('inventory.procurement.store') && $this->boolean('request_form');
    }
}
