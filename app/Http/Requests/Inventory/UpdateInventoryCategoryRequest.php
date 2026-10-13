<?php

namespace App\Http\Requests\Inventory;

use App\InventoryCategoryType;
use App\Models\InventoryAssetCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateInventoryCategoryRequest extends FormRequest
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
        $type = InventoryCategoryType::tryFrom((string) $this->route('type')) ?? InventoryCategoryType::Major;
        $categoryId = (int) $this->route('category');
        $parentRule = $type->parentTable() && $type->parentPrimaryKey()
            ? Rule::exists($type->parentTable(), $type->parentPrimaryKey())
            : null;
        $nameRule = Rule::unique($type->table(), 'name')->ignore($categoryId, $type->primaryKey());

        if ($parentColumn = $type->parentColumn()) {
            $nameRule->where($parentColumn, $this->integer('parent_id'));
        }

        return [
            'parent_id' => [Rule::requiredIf($type->parentColumn() !== null), 'nullable', 'integer', $parentRule],
            'code' => ['required', 'string', 'max:20', Rule::unique($type->table(), 'code')->ignore($categoryId, $type->primaryKey())],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_vehicle' => [Rule::requiredIf($type === InventoryCategoryType::Asset), 'boolean'],
            'account_code' => ['nullable', 'string', 'max:50'],
            'default_useful_life_months' => [Rule::requiredIf($type === InventoryCategoryType::Asset), 'nullable', 'integer', 'min:1', 'max:1200'],
            'default_residual_value_percentage' => [Rule::requiredIf($type === InventoryCategoryType::Asset), 'nullable', 'numeric', 'min:0', 'max:99.99'],
            'is_depreciable' => [Rule::requiredIf($type === InventoryCategoryType::Asset), 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'code' => Str::upper(trim((string) $this->input('code'))),
            'name' => Str::squish((string) $this->input('name')),
            'description' => filled($this->input('description'))
                ? Str::squish((string) $this->input('description'))
                : null,
        ];

        if ((string) $this->route('type') === InventoryCategoryType::Asset->value) {
            $category = InventoryAssetCategory::query()->find((int) $this->route('category'));
            $data['is_vehicle'] = $this->boolean('is_vehicle');
            $data['account_code'] = $this->exists('account_code')
                ? (filled($this->input('account_code')) ? Str::upper(trim((string) $this->input('account_code'))) : null)
                : $category?->account_code;
            $data['default_useful_life_months'] = $this->exists('default_useful_life_months')
                ? $this->integer('default_useful_life_months')
                : $category?->default_useful_life_months;
            $data['default_residual_value_percentage'] = $this->input(
                'default_residual_value_percentage',
                $category?->default_residual_value_percentage,
            );
            $data['is_depreciable'] = $this->exists('is_depreciable')
                ? $this->boolean('is_depreciable')
                : ($category?->is_depreciable ?? true);
        }

        $this->merge($data);
    }
}
