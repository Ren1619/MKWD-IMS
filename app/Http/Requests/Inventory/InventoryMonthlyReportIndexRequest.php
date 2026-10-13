<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMonthlyReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['prepared', 'checked', 'noted', 'approved', 'transmitted', 'cancelled'])],
            'period_month' => ['nullable', 'date_format:Y-m', 'before:'.now('Asia/Manila')->startOfMonth()->format('Y-m')], 'fund_cluster' => ['nullable', 'string', 'max:50'],
            'series_category_id' => ['nullable', 'integer', Rule::exists('inv_series_cats', 'inv_series_cat_id')], 'include_zero' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
