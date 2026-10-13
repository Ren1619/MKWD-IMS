<?php

namespace App\Http\Requests\Inventory;

use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TransitionProcurementRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['submit_intake', 'return_for_compliance', 'verify_documents', 'prepare_document', 'sign', 'submit', 'budget_review', 'approve', 'forward', 'order', 'record_delivery', 'accept', 'reject', 'cancel'])],
            'intake_details' => ['nullable', 'array:approved_request_reference,end_user,department,division,is_replacement'],
            'intake_details.approved_request_reference' => ['nullable', 'string', 'max:255'],
            'intake_details.end_user' => ['nullable', 'string', 'max:255'],
            'intake_details.department' => ['nullable', 'string', 'max:255'],
            'intake_details.division' => ['nullable', 'string', 'max:255'],
            'intake_details.is_replacement' => ['nullable', 'boolean'],
            'document_waivers' => ['nullable', 'array'],
            'document_waivers.*' => ['nullable', 'string', 'max:1000'],
            'preparation' => ['nullable', 'array:requisition_type,charge_account,abc,caf_number,place_of_delivery,vehicle_plate,are_number,target_completion_date,estimated_cost,work_description,prepared_by_division,prepared_by_user_id,recommending_user_id,countersigning_user_id,gm_user_id'],
            'preparation.requisition_type' => ['nullable', 'string', 'max:100'],
            'preparation.charge_account' => ['nullable', 'string', 'max:100'],
            'preparation.abc' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'preparation.caf_number' => ['nullable', 'string', 'max:100'],
            'preparation.place_of_delivery' => ['nullable', 'string', 'max:255'],
            'preparation.vehicle_plate' => ['nullable', 'string', 'max:100'],
            'preparation.are_number' => ['nullable', 'string', 'max:100'],
            'preparation.target_completion_date' => ['nullable', 'date'],
            'preparation.estimated_cost' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'preparation.work_description' => ['nullable', 'string', 'max:10000'],
            'preparation.prepared_by_division' => ['required_if:action,prepare_document', 'nullable', Rule::in(['PMMD', 'PWSD'])],
            'preparation.prepared_by_user_id' => ['nullable', 'integer', $this->signatoryRule()],
            'preparation.recommending_user_id' => ['nullable', 'integer', $this->signatoryRule()],
            'preparation.countersigning_user_id' => ['nullable', 'integer', $this->signatoryRule()],
            'preparation.gm_user_id' => ['nullable', 'integer', $this->signatoryRule()],
            'endorsement_recipient' => [Rule::requiredIf($this->input('action') === 'forward' && $this->route('procurementRequest')?->preprocurement !== null), 'nullable', 'string', 'max:255'],
            'attested' => ['accepted'], 'remarks' => ['nullable', 'string', 'max:2000'], 'procurement_mode' => ['nullable', 'string', 'max:100'],
            'purchase_order_no' => ['nullable', 'string', 'max:100'], 'inspection_acceptance_no' => ['nullable', 'string', 'max:100'],
            'delivery_reference' => ['nullable', 'string', 'max:100'], 'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'actual_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'line_receipts' => ['nullable', 'array'],
            'line_estimates' => ['nullable', 'array', 'max:50'],
            'line_estimates.*' => ['array:line_id,estimated_unit_cost'],
            'line_estimates.*.line_id' => ['required', 'integer', 'distinct'],
            'line_estimates.*.estimated_unit_cost' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'line_receipts.*' => ['array:line_id,quantity_received,actual_unit_cost'],
            'line_receipts.*.line_id' => ['required', 'integer'],
            'line_receipts.*.quantity_received' => ['required', 'integer', 'min:1'],
            'line_receipts.*.actual_unit_cost' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'funding_source' => ['nullable', 'string', 'max:150'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'ppmp_reference' => ['nullable', 'string', 'max:100'],
            'app_reference' => ['nullable', 'string', 'max:100'],
            'line_categories' => ['nullable', 'array'],
            'line_categories.*' => ['array:line_id,series_category_id'],
            'line_categories.*.line_id' => ['required', 'integer'],
            'line_categories.*.series_category_id' => ['required', 'integer', Rule::exists('inv_series_cats', 'inv_series_cat_id')->where('is_active', true)],
        ];
    }

    private function signatoryRule(): Exists
    {
        return Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereIn('role', [UserRole::SuperAdmin->value, UserRole::InventoryManager->value]));
    }
}
