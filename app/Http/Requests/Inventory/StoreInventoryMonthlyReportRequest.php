<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['period_month' => ['required', 'date_format:Y-m', 'before:'.now('Asia/Manila')->startOfMonth()->format('Y-m')],
            'fund_cluster' => ['required', 'string', 'max:50'], 'series_category_id' => ['nullable', 'integer', Rule::exists('inv_series_cats', 'inv_series_cat_id')], 'include_zero' => ['required', 'boolean'],
            'checked_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', ['inventory_manager', 'super_admin'])],
            'noted_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', ['inventory_manager', 'super_admin'])],
            'approved_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', ['inventory_manager', 'super_admin'])],
            'reconciliation_reference' => ['required', 'string', 'max:3000'], 'correction_reason' => ['nullable', 'string', 'max:3000'], 'attested' => ['accepted'], 'idempotency_key' => ['required', 'uuid']];
    }
}
