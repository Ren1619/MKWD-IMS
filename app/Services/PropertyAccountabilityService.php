<?php

namespace App\Services;

use App\AssetAccountingStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCustodian;
use App\Models\PropertyAccountabilityDocument;
use App\Models\PropertyDocumentSequence;
use App\Models\User;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableValueClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyAccountabilityService
{
    public function __construct(private InventoryAssetLifecycleService $lifecycle) {}

    public function issue(
        InventoryAsset $asset,
        InventoryAssetCustodian $assignment,
        User $issuer,
        ?PropertyAccountabilityDocument $supersedes = null,
    ): PropertyAccountabilityDocument {
        return DB::transaction(function () use ($asset, $assignment, $issuer, $supersedes): PropertyAccountabilityDocument {
            $lockedAsset = InventoryAsset::query()
                ->whereKey($asset->getKey())
                ->lockForUpdate()
                ->with('currentCustodian')
                ->firstOrFail();

            if (
                $lockedAsset->current_custodian_reference_id !== $assignment->hris_reference_id
                || $lockedAsset->currentCustodian === null
                || $assignment->unassigned_at !== null
            ) {
                throw ValidationException::withMessages([
                    'asset' => 'The selected custody assignment is no longer active.',
                ]);
            }

            $cost = (float) ($lockedAsset->acquisition_cost ?? 0);

            if ($cost <= 0) {
                throw ValidationException::withMessages([
                    'asset' => 'An acquisition cost is required before issuing a PAR or ICS.',
                ]);
            }

            $documentType = $lockedAsset->accounting_classification->accountabilityDocumentType();

            if ($documentType === null) {
                throw ValidationException::withMessages([
                    'asset' => 'Resolve the asset accounting classification before issuing an accountability document.',
                ]);
            }
            $issuedAt = now();
            $issuerAttestation = 'I certify that the property was issued as described and custody was assigned to the named recipient.';
            $valueClass = $documentType === 'ICS' ? $lockedAsset->semi_expendable_value_class : null;
            $sequence = $this->nextSequence($documentType, $valueClass?->value ?? 'all', $issuedAt->year);
            $accountabilityExpiresAt = $valueClass === SemiExpendableValueClass::Low
                ? $issuedAt->copy()->addMonths($lockedAsset->depreciation_useful_life_months)->toDateString()
                : null;

            $document = PropertyAccountabilityDocument::query()->create([
                'document_no' => $this->formatDocumentNo($documentType, $valueClass, $issuedAt->year, $sequence),
                'document_type' => $documentType,
                'value_class' => $valueClass?->value,
                'sequence_no' => $sequence,
                'inventory_asset_id' => $lockedAsset->getKey(),
                'inventory_asset_custodian_id' => $assignment->getKey(),
                'recipient_reference_id' => $lockedAsset->currentCustodian->id,
                'issued_by_user_id' => $issuer->id,
                'supersedes_document_id' => $supersedes?->getKey(),
                'status' => 'pending_recipient',
                'entity_name' => config('app.name'),
                'fund_cluster' => $lockedAsset->fund_cluster,
                'asset_name' => $lockedAsset->name,
                'asset_description' => $lockedAsset->description,
                'property_number' => $lockedAsset->property_number,
                'serial_number' => $lockedAsset->serial_number,
                'unit_of_measure' => $lockedAsset->unit_of_measure,
                'quantity' => $lockedAsset->quantity_per_property_card,
                'acquisition_date' => $lockedAsset->acquisition_date,
                'acquisition_cost' => $cost,
                'estimated_useful_life_months' => $lockedAsset->depreciation_useful_life_months,
                'recipient_name' => $lockedAsset->currentCustodian->name,
                'recipient_code' => $lockedAsset->currentCustodian->code,
                'recipient_position' => data_get($lockedAsset->currentCustodian->metadata, 'position.name'),
                'recipient_office' => data_get($lockedAsset->currentCustodian->metadata, 'position.section.name')
                    ?? data_get($lockedAsset->currentCustodian->metadata, 'position.division.name')
                    ?? data_get($lockedAsset->currentCustodian->metadata, 'position.department.name'),
                'supplier_name' => $lockedAsset->supplier_name,
                'invoice_reference' => $lockedAsset->invoice_no
                    ?? $lockedAsset->inspection_acceptance_report_no
                    ?? $lockedAsset->purchase_order_no,
                'fund_code' => $lockedAsset->fund_cluster,
                'issued_by_name' => $issuer->name,
                'issuer_attestation' => $issuerAttestation,
                'issued_at' => $issuedAt,
                'renewal_due_at' => $issuedAt->copy()->addYears((int) config('inventory.semi_expendable.ics_renewal_years', 3)),
                'accountability_expires_at' => $accountabilityExpiresAt,
            ]);

            $this->recordAction(
                $document,
                $issuer,
                'issue',
                null,
                'pending_recipient',
                $issuerAttestation,
            );

            if ($documentType === 'ICS') {
                $lockedAsset->update([
                    'accounting_status' => AssetAccountingStatus::IssuedAndExpensed,
                    'physical_status' => SemiExpendablePhysicalStatus::Issued,
                    'accountability_status' => SemiExpendableAccountabilityStatus::PendingRecipient,
                    'accountability_expires_at' => $accountabilityExpiresAt,
                    'available_for_reissue' => false,
                ]);
                $isRenewal = $supersedes !== null
                    && $supersedes->inventory_asset_custodian_id === $assignment->getKey();
                $isReissue = ! $isRenewal && $lockedAsset->events()->whereIn('event_type', [
                    InventoryAssetEventType::Issuance->value,
                    InventoryAssetEventType::Reissue->value,
                ])->exists();

                if (! $isRenewal) {
                    $this->lifecycle->recordSystemEvent($lockedAsset, $isReissue ? InventoryAssetEventType::Reissue : InventoryAssetEventType::Issuance, [
                        'event_date' => $issuedAt->toDateString(),
                        'reference_no' => $document->document_no,
                        'amount' => $lockedAsset->acquisition_cost,
                        'quantity' => 1,
                        'notes' => $isReissue ? 'Returned serviceable property reissued under a new ICS.' : 'Property issued; accounting inventory expensed while physical accountability continues.',
                        'details' => ['document_id' => $document->getKey(), 'recipient_reference_id' => $document->recipient_reference_id],
                    ], $issuer);
                }
                $this->lifecycle->recordSystemEvent($lockedAsset, $supersedes ? InventoryAssetEventType::IcsRenewed : InventoryAssetEventType::IcsCreated, [
                    'event_date' => $issuedAt->toDateString(),
                    'reference_no' => $document->document_no,
                    'notes' => $supersedes ? 'ICS renewed or replaced; prior ICS history retained.' : 'ICS accountability document created.',
                    'details' => ['document_id' => $document->getKey(), 'supersedes_document_id' => $supersedes?->getKey()],
                ], $issuer);
            }

            return $document->load(['asset', 'actions.actor']);
        }, attempts: 3);
    }

    public function issueForCurrentAssignment(
        InventoryAsset $asset,
        User $issuer,
    ): PropertyAccountabilityDocument {
        $assignment = $asset->custodianAssignments()
            ->whereNull('unassigned_at')
            ->first();

        if ($assignment === null || $asset->current_custodian_reference_id === null) {
            throw ValidationException::withMessages([
                'asset' => 'Assign a custodian before issuing an accountability document.',
            ]);
        }

        if ($asset->accountabilityDocuments()->whereIn('status', ['pending_recipient', 'active'])->exists()) {
            throw ValidationException::withMessages([
                'asset' => 'This asset already has a current accountability document.',
            ]);
        }

        return $this->issue($asset, $assignment, $issuer);
    }

    public function acknowledge(
        PropertyAccountabilityDocument $document,
        User $actor,
        ?string $remarks = null,
        bool $witnessed = false,
    ): PropertyAccountabilityDocument {
        return DB::transaction(function () use ($document, $actor, $remarks, $witnessed): PropertyAccountabilityDocument {
            $lockedDocument = PropertyAccountabilityDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDocument->status !== 'pending_recipient') {
                throw ValidationException::withMessages([
                    'action' => 'Only a pending document may be acknowledged.',
                ]);
            }

            $isRecipient = $actor->hris_reference_id !== null
                && $actor->hris_reference_id === $lockedDocument->recipient_reference_id;

            if (! $isRecipient && ! ($witnessed && $actor->canManageInventory())) {
                throw ValidationException::withMessages([
                    'action' => 'Only the named recipient may acknowledge this document.',
                ]);
            }

            if ($witnessed && blank($remarks)) {
                throw ValidationException::withMessages([
                    'remarks' => 'Describe how the recipient acknowledgment was verified.',
                ]);
            }

            $attestation = $witnessed
                ? 'I certify that the named recipient acknowledged custody through the verification described in the remarks.'
                : 'I acknowledge receipt and accept responsibility for the proper use and safekeeping of the listed government property.';

            $lockedDocument->update([
                'status' => 'active',
                'acknowledged_by_user_id' => $actor->id,
                'recipient_attestation' => $attestation,
                'acknowledged_at' => now(),
            ]);

            if ($lockedDocument->document_type === 'ICS') {
                $lockedDocument->asset()->update([
                    'accountability_status' => SemiExpendableAccountabilityStatus::Accountable,
                ]);
            }

            $this->recordAction(
                $lockedDocument,
                $actor,
                $witnessed ? 'record_witnessed_acknowledgment' : 'acknowledge',
                'pending_recipient',
                'active',
                $attestation,
                $remarks,
            );

            return $lockedDocument->refresh();
        });
    }

    public function renew(
        PropertyAccountabilityDocument $document,
        User $actor,
        ?string $remarks = null,
    ): PropertyAccountabilityDocument {
        return DB::transaction(function () use ($document, $actor, $remarks): PropertyAccountabilityDocument {
            $lockedDocument = PropertyAccountabilityDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->with(['asset', 'custodianAssignment'])
                ->firstOrFail();

            if (! in_array($lockedDocument->status, ['active', 'pending_recipient'], true)) {
                throw ValidationException::withMessages([
                    'action' => 'Only a current document may be renewed.',
                ]);
            }

            $previousStatus = $lockedDocument->status;
            $closureReason = $remarks ?: 'Renewed accountability document.';

            $lockedDocument->update([
                'status' => 'superseded',
                'closed_at' => now(),
                'closure_reason' => $closureReason,
            ]);

            $this->recordAction(
                $lockedDocument,
                $actor,
                'supersede',
                $previousStatus,
                'superseded',
                'I certify that this document is superseded by a renewed accountability document.',
                $closureReason,
            );

            return $this->issue(
                $lockedDocument->asset,
                $lockedDocument->custodianAssignment,
                $actor,
                $lockedDocument,
            );
        });
    }

    public function closeCurrentForAsset(
        InventoryAsset $asset,
        User $actor,
        string $status,
        string $reason,
    ): void {
        $documents = $asset->accountabilityDocuments()
            ->whereIn('status', ['pending_recipient', 'active'])
            ->lockForUpdate()
            ->get();

        foreach ($documents as $document) {
            $previousStatus = $document->status;

            $document->update([
                'status' => $status,
                'closed_at' => now(),
                'closure_reason' => $reason,
            ]);

            $this->recordAction(
                $document,
                $actor,
                $status,
                $previousStatus,
                $status,
                "I certify that this accountability document was {$status} for the stated reason.",
                $reason,
            );
        }
    }

    public function cancel(
        PropertyAccountabilityDocument $document,
        User $actor,
        string $reason,
    ): void {
        DB::transaction(function () use ($document, $actor, $reason): void {
            $lockedDocument = PropertyAccountabilityDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->with('asset')
                ->firstOrFail();

            if ($lockedDocument->status !== 'pending_recipient') {
                throw ValidationException::withMessages([
                    'action' => 'Only an unacknowledged document may be cancelled.',
                ]);
            }

            $lockedDocument->update([
                'status' => 'cancelled',
                'closed_at' => now(),
                'closure_reason' => $reason,
            ]);

            $this->recordAction(
                $lockedDocument,
                $actor,
                'cancel',
                'pending_recipient',
                'cancelled',
                'I certify that this document was cancelled for the stated reason.',
                $reason,
            );

            $lockedDocument->custodianAssignment()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);
            $lockedDocument->asset()->update([
                'current_custodian_reference_id' => null,
            ]);
            if ($lockedDocument->document_type === 'ICS') {
                $accountingStatus = $lockedDocument->asset->accountabilityDocuments()
                    ->where('document_type', 'ICS')
                    ->count() === 1
                    ? AssetAccountingStatus::OnHand
                    : AssetAccountingStatus::IssuedAndExpensed;
                $lockedDocument->asset()->update([
                    'accounting_status' => $accountingStatus,
                    'physical_status' => SemiExpendablePhysicalStatus::InPropertyCustody,
                    'accountability_status' => SemiExpendableAccountabilityStatus::Unassigned,
                    'accountability_expires_at' => null,
                ]);
            }
        });
    }

    private function recordAction(
        PropertyAccountabilityDocument $document,
        User $actor,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        string $attestation,
        ?string $remarks = null,
    ): void {
        $document->actions()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'attestation' => $attestation,
            'remarks' => $remarks,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function nextSequence(string $documentType, string $valueClass, int $year): int
    {
        PropertyDocumentSequence::query()->firstOrCreate([
            'document_type' => $documentType,
            'value_class' => $valueClass,
            'year' => $year,
        ]);
        $sequence = PropertyDocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('value_class', $valueClass)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $sequence->increment('last_number');

        return $sequence->last_number;
    }

    private function formatDocumentNo(
        string $documentType,
        ?SemiExpendableValueClass $valueClass,
        int $year,
        int $sequence,
    ): string {
        $prefix = $documentType === 'ICS'
            ? sprintf('ICS-%s', $valueClass === SemiExpendableValueClass::Low ? 'LV' : 'HV')
            : $documentType;

        return sprintf('%s-%d-%06d', $prefix, $year, $sequence);
    }
}
