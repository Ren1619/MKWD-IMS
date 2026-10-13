<?php

namespace App\Services;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PreprocurementWorkflow
{
    /** @return array<string, array{label: string, documents: array<string, array{label: string, required: bool}>}> */
    public static function categories(): array
    {
        $common = [
            'approved_request' => ['label' => 'Approved request with department manager endorsement', 'required' => true],
            'caf' => ['label' => 'Certification of Availability of Fund (CAF)', 'required' => true],
            'app' => ['label' => 'Annual Procurement Plan (APP)', 'required' => true],
            'ppmp' => ['label' => 'Project Procurement Management Plan (PPMP)', 'required' => true],
        ];
        $specifications = ['specifications' => ['label' => 'Terms of reference / detailed specifications', 'required' => true]];
        $optionalSpecifications = ['specifications' => ['label' => 'Terms of reference / detailed specifications (if necessary)', 'required' => false]];
        $replacement = [
            'waste_report' => ['label' => 'Waste Material Report (replacement)', 'required' => false],
            'pre_repair' => ['label' => 'Pre-repair Inspection Report (replacement)', 'required' => false],
            'spot_report' => ['label' => 'Spot Report (replacement)', 'required' => false],
        ];

        return [
            'supplies' => ['label' => 'Common supplies', 'documents' => [...$common, ...$specifications, 'market_scoping' => ['label' => 'Market Scoping / Evaluation Report', 'required' => true]]],
            'vehicle_maintenance' => ['label' => 'Vehicle / equipment preventive maintenance', 'documents' => [...$common, ...$optionalSpecifications, ...$replacement, 'history_ledger' => ['label' => 'History Ledger', 'required' => true], 'pre_repair' => ['label' => 'Pre-repair Inspection Report', 'required' => true], 'spot_report' => ['label' => 'Driver’s Spot Report', 'required' => true]]],
            'project' => ['label' => 'Project procurement', 'documents' => [...$common, ...$specifications, 'work_order' => ['label' => 'Work Order', 'required' => true], 'program_of_works' => ['label' => 'Program of Works', 'required' => true]]],
            'equipment' => ['label' => 'Equipment procurement', 'documents' => [...$common, ...$optionalSpecifications, ...$replacement]],
            'other_services' => ['label' => 'Other non-common supplies / services', 'documents' => [...$common, 'approved_request' => ['label' => 'Approved proposal / request letter / office memorandum', 'required' => true], ...$optionalSpecifications, 'sample' => ['label' => 'Sample / template (if necessary)', 'required' => false]]],
        ];
    }

    /** @param array<string, mixed> $data */
    public function transition(ProcurementRequest $request, User $actor, array $data): string
    {
        $details = $request->preprocurement ?? [];
        $action = $data['action'];
        $allowed = match ($action) {
            'submit_intake' => ['intake', 'returned_for_compliance'],
            'return_for_compliance', 'verify_documents' => ['for_document_review'],
            'prepare_document' => ['ready_for_preparation'],
            'sign' => ['for_signatures'],
            default => [],
        };
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'This pre-procurement action is not allowed at the current stage.']);
        }
        if ($action === 'return_for_compliance') {
            if (blank($data['remarks'] ?? null)) {
                throw ValidationException::withMessages(['remarks' => 'Explain what the end user must correct.']);
            }

            return 'returned_for_compliance';
        }
        if ($action === 'submit_intake') {
            $details = [...$details, ...($data['intake_details'] ?? [])];
            foreach (['approved_request_reference', 'end_user', 'department', 'division'] as $field) {
                if (blank($details[$field] ?? null)) {
                    throw ValidationException::withMessages(["intake_details.{$field}" => 'This intake field is required before document review.']);
                }
            }
            $request->update(['preprocurement' => $details]);

            return 'for_document_review';
        }
        if ($action === 'verify_documents') {
            if ($actor->id === $request->created_by_user_id) {
                throw ValidationException::withMessages(['action' => 'Another inventory manager must verify the supporting documents.']);
            }
            $documents = self::categories()[$request->procurement_category]['documents'] ?? [];
            $waivers = $data['document_waivers'] ?? [];
            foreach ($documents as $key => $document) {
                $hasAttachment = $request->attachments()->where('document_key', $key)->exists();
                if (! $hasAttachment && ($document['required'] || blank($waivers[$key] ?? null))) {
                    throw ValidationException::withMessages(['document_waivers' => "Upload {$document['label']} or explain why an optional document is not applicable."]);
                }
                if (! $hasAttachment && ($details['is_replacement'] ?? false) && in_array($key, ['waste_report', 'pre_repair', 'spot_report'], true)) {
                    throw ValidationException::withMessages(['document_waivers' => "{$document['label']} is required for replacement requisitions."]);
                }
            }
            $request->update(['preprocurement' => [...$details, 'document_waivers' => $waivers, 'documents_verified_by' => $actor->id, 'documents_verified_at' => now()->toIso8601String()]]);

            return 'ready_for_preparation';
        }
        if ($action === 'prepare_document') {
            $preparation = $data['preparation'] ?? [];
            foreach ($data['line_estimates'] ?? [] as $estimate) {
                $line = $request->lines()->whereKey($estimate['line_id'])->first();
                if ($line === null) {
                    throw ValidationException::withMessages(['line_estimates' => 'An estimate does not belong to this requisition.']);
                }
                $line->update(['estimated_unit_cost' => $estimate['estimated_unit_cost']]);
            }
            if ($request->document_kind === 'PR' && $request->lines()->where('estimated_unit_cost', '<=', 0)->exists()) {
                throw ValidationException::withMessages(['line_estimates' => 'Enter an estimated unit cost greater than zero for every item.']);
            }
            foreach ($data['line_categories'] ?? [] as $category) {
                $line = $request->lines()->whereKey($category['line_id'])->where('item_kind', 'consumable')->first();
                if ($line === null) {
                    throw ValidationException::withMessages(['line_categories' => 'Inventory categories must belong to consumable lines on this requisition.']);
                }
                $line->update(['series_category_id' => $category['series_category_id']]);
            }
            if ($request->lines()->where('item_kind', 'consumable')->whereNull('inventory_item_id')->whereNull('series_category_id')->exists()) {
                throw ValidationException::withMessages(['line_categories' => 'Assign an inventory category to every new consumable item.']);
            }
            $required = ['requisition_type', 'charge_account', 'abc', 'caf_number', 'prepared_by_user_id', 'recommending_user_id', 'countersigning_user_id', 'gm_user_id'];
            $required = [...$required, ...($request->document_kind === 'JO' ? ['target_completion_date', 'estimated_cost', 'work_description'] : ['place_of_delivery'])];
            foreach ($required as $field) {
                if (blank($preparation[$field] ?? null)) {
                    throw ValidationException::withMessages(["preparation.{$field}" => 'This preparation field is required.']);
                }
            }
            if ($request->document_kind === 'PR' && $request->lines()->doesntExist()) {
                throw ValidationException::withMessages(['action' => 'A purchase requisition must contain at least one item.']);
            }
            $signerIds = collect($preparation)->only(['prepared_by_user_id', 'recommending_user_id', 'countersigning_user_id', 'gm_user_id'])->map(fn (mixed $id): int => (int) $id);
            if ($signerIds->unique()->count() !== 4 || in_array((int) $preparation['gm_user_id'], [$request->created_by_user_id, $actor->id], true)) {
                throw ValidationException::withMessages(['preparation.gm_user_id' => 'Assign four distinct signatories; the intake creator and encoder cannot be the GM approver.']);
            }
            $request->update([
                ...collect($data)->only(['funding_source', 'responsibility_center_code', 'app_reference', 'ppmp_reference'])->filter(fn (mixed $value): bool => filled($value))->all(),
                'pr_no' => sprintf('%s-%s-%06d', $request->document_kind, now()->format('Y'), $request->id),
                'preprocurement' => [...$details, ...$preparation, 'prepared_at' => now()->toIso8601String(), 'encoded_by_user_id' => $actor->id, 'signature_route' => $signerIds->map(fn (int $id, string $stage): array => ['stage' => $stage, 'user_id' => $id, 'name' => User::query()->findOrFail($id)->name])->values()->all(), 'signatures' => []],
            ]);

            return 'for_signatures';
        }

        $signatures = $details['signatures'] ?? [];
        $stages = ['prepared_by_user_id', 'recommending_user_id', 'countersigning_user_id'];
        $stage = $stages[count($signatures)] ?? null;
        if ($stage === null || (int) ($details[$stage] ?? 0) !== $actor->id) {
            throw ValidationException::withMessages(['action' => 'Only the assigned signatory for the current step can sign.']);
        }
        $signatures[] = ['stage' => $stage, 'user_id' => $actor->id, 'name' => $actor->name, 'signed_at' => now()->toIso8601String()];
        $request->update(['preprocurement' => [...$details, 'signatures' => $signatures]]);

        return count($signatures) === 3 ? 'for_approval' : 'for_signatures';
    }
}
