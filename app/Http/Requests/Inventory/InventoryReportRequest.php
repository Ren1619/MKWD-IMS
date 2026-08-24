<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReportRequest extends FormRequest
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
            'report' => ['nullable', Rule::in(['consumables', 'assets'])],
            'search' => ['nullable', 'string', 'max:100'],
            'records' => ['nullable', Rule::in(['active', 'archived'])],
            'item_status' => ['nullable', Rule::in(['active', 'inactive'])],
            'attention' => ['nullable', Rule::in(['low_stock', 'expiring', 'expired'])],
            'lifecycle_status' => ['nullable', Rule::enum(AssetLifecycleStatus::class)],
            'condition_status' => ['nullable', Rule::enum(AssetConditionStatus::class)],
            'custody_status' => ['nullable', Rule::enum(AssetCustodyStatus::class)],
        ];
    }

    /**
     * @return array{
     *     report: string,
     *     search: string,
     *     records: string,
     *     item_status: string,
     *     attention: string,
     *     lifecycle_status: string,
     *     condition_status: string,
     *     custody_status: string
     * }
     */
    public function filters(): array
    {
        return [
            'report' => $this->string('report')->toString() ?: 'consumables',
            'search' => $this->string('search')->toString(),
            'records' => $this->string('records')->toString() ?: 'active',
            'item_status' => $this->string('item_status')->toString(),
            'attention' => $this->string('attention')->toString(),
            'lifecycle_status' => $this->string('lifecycle_status')->toString(),
            'condition_status' => $this->string('condition_status')->toString(),
            'custody_status' => $this->string('custody_status')->toString(),
        ];
    }
}
