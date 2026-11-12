<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Services\InventoryMonthlyReportBuilder;
use Illuminate\Validation\Rule;

class StoreInventoryMonthlyReportRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['selected_report' => ['nullable', Rule::in(array_keys(InventoryMonthlyReportBuilder::catalog()))], 'period_month' => ['required', 'date_format:Y-m', 'before:'.now('Asia/Manila')->startOfMonth()->format('Y-m')],
            'fund_cluster' => ['required', 'string', 'max:50'], 'series_category_id' => ['nullable', 'integer', Rule::exists('inv_series_cats', 'inv_series_cat_id')], 'include_zero' => ['required', 'boolean'],
            'checked_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'noted_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'approved_by_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'reconciliation_reference' => ['nullable', 'string', 'max:3000'], 'correction_reason' => ['nullable', 'string', 'max:3000'], 'attested' => ['accepted'], 'idempotency_key' => ['required', 'uuid']];
    }
}
