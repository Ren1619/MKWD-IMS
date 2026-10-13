<?php

namespace App\Http\Requests\Inventory;

use App\Models\HrisReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionProcurementDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = fn () => Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true);

        return ['action' => ['required', Rule::in(['inspect', 'accept', 'cancel', 'record_supplier_return', 'authorize_supplier_return', 'endorse', 'postpone'])], 'attested' => ['accepted'], 'remarks' => ['required', 'string', 'max:3000'],
            'evaluation_date' => ['required_if:action,inspect', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'order_reference_date' => ['required_if:action,inspect', 'nullable', 'date_format:Y-m-d', 'before_or_equal:evaluation_date'],
            'invoice_date' => ['required_if:action,inspect', 'nullable', 'date_format:Y-m-d', 'before_or_equal:evaluation_date'],
            'delivery_receipt_date' => ['required_if:action,inspect', 'nullable', 'date_format:Y-m-d', 'before_or_equal:evaluation_date'],
            'inspection_site' => ['required_if:action,inspect', 'nullable', 'string', 'max:255'],
            'evaluation_findings' => ['required_if:action,inspect', 'nullable', 'string', 'max:3000'],
            'evaluation_comments' => ['nullable', 'string', 'max:3000'],
            'evaluator_reference_id' => ['required_if:action,inspect', 'nullable', 'integer', $employee()],
            'inspection_chair_reference_id' => ['required_if:action,inspect', 'nullable', 'integer', $employee()],
            'inspection_vice_reference_id' => ['required_if:action,inspect', 'nullable', 'integer', $employee()],
            'inspection_end_user_reference_id' => ['required_if:action,inspect', 'nullable', 'integer', $employee()],
            'evaluator_signature_reference' => ['required_if:action,inspect', 'nullable', 'string', 'max:120'],
            'evaluation_chair_signature_reference' => ['required_if:action,inspect', 'nullable', 'string', 'max:120'],
            'return_notice_date' => ['required_if:action,authorize_supplier_return', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'return_purpose' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:1000'],
            'return_reason' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:3000'],
            'return_action_required' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:3000'],
            'return_preparer_reference_id' => ['required_if:action,authorize_supplier_return', 'nullable', 'integer', $employee()],
            'return_end_user_reference_id' => ['required_if:action,authorize_supplier_return', 'nullable', 'integer', $employee()],
            'return_procurement_head_reference_id' => ['required_if:action,authorize_supplier_return', 'nullable', 'integer', $employee()],
            'return_chair_reference_id' => ['required_if:action,authorize_supplier_return', 'nullable', 'integer', $employee()],
            'return_preparer_signature_reference' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:120'],
            'return_end_user_signature_reference' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:120'],
            'return_procurement_head_signature_reference' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:120'],
            'return_chair_signature_reference' => ['required_if:action,authorize_supplier_return', 'nullable', 'string', 'max:120'],
            'resumption_reference' => ['nullable', 'string', 'max:255'],
            'notice_submission_key' => ['required_if:action,postpone', 'nullable', 'uuid'],
            'notice_date' => ['required_if:action,postpone', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'original_inspection_at' => ['required_if:action,postpone', 'nullable', 'date_format:Y-m-d\\TH:i'],
            'proposed_inspection_at' => ['required_if:action,postpone', 'nullable', 'date_format:Y-m-d\\TH:i'],
            'original_site' => ['required_if:action,postpone', 'nullable', 'string', 'max:255'],
            'proposed_site' => ['required_if:action,postpone', 'nullable', 'string', 'max:255'],
            'postponement_reason' => ['required_if:action,postpone', 'nullable', Rule::in(['no_quorum', 'technical_representative_unavailable', 'safety_force_majeure', 'other'])],
            'supplier_contact_name' => ['required_if:action,postpone', 'nullable', 'string', 'max:255'],
            'supplier_address' => ['required_if:action,postpone', 'nullable', 'string', 'max:1000'],
            'lot_reference' => ['required_if:action,postpone', 'nullable', 'string', 'max:120'],
            'chair_reference_id' => ['required_if:action,postpone', 'nullable', 'integer', $employee()],
            'authority_reference_id' => ['required_if:action,postpone', 'nullable', 'integer', $employee()],
            'chair_signature_reference' => ['required_if:action,postpone', 'nullable', 'string', 'max:120'],
            'authority_signature_reference' => ['required_if:action,postpone', 'nullable', 'string', 'max:120'],
            'notice_sent_reference' => ['required_if:action,postpone', 'nullable', 'string', 'max:120'],
            'inspection_reference' => ['required_if:action,inspect', 'nullable', 'string', 'max:120'],
            'coa_witness_name' => ['nullable', 'string', 'max:255'],
            'supplier_notification_reference' => ['nullable', 'string', 'max:255'],
            'signed_acceptance_reference' => ['required_if:action,accept', 'nullable', 'string', 'max:255'],
            'return_reference' => ['required_if:action,record_supplier_return', 'nullable', 'string', 'max:255'], 'returned_at' => ['required_if:action,record_supplier_return', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'endorsement_reference' => ['required_if:action,endorse', 'nullable', 'string', 'max:255'], 'endorsement_recipient' => ['required_if:action,endorse', 'nullable', 'string', 'max:255'],
            'lines' => ['required_if:action,inspect', 'nullable', 'array', 'max:100'], 'lines.*' => ['required', 'array:line_id,quantity_accepted,condition,disposition,storage_location,disposition_reference,findings,nonconformities'],
            'lines.*.nonconformities' => ['present', 'array', 'max:4'],
            'lines.*.nonconformities.*' => ['required', Rule::in(['packaging', 'label', 'count', 'brand_model'])],
            'lines.*.line_id' => ['required', 'integer', 'distinct'], 'lines.*.quantity_accepted' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.condition' => ['required', Rule::in(['conforming', 'damaged', 'nonconforming'])],
            'lines.*.disposition' => ['required', Rule::in(['warehouse', 'direct_use', 'calibration'])],
            'lines.*.storage_location' => ['nullable', 'string', 'max:255'], 'lines.*.disposition_reference' => ['nullable', 'string', 'max:255'], 'lines.*.findings' => ['required', 'string', 'max:3000']];
    }
}
