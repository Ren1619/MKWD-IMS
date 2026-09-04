<?php

namespace App\Services;

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCustodian;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyAccountabilityService
{
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

            $document = PropertyAccountabilityDocument::query()->create([
                'document_no' => 'TMP-'.Str::uuid(),
                'document_type' => $documentType,
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
                'issued_by_name' => $issuer->name,
                'issuer_attestation' => $issuerAttestation,
                'issued_at' => $issuedAt,
                'renewal_due_at' => $issuedAt->copy()->addYears(3),
            ]);

            $document->update([
                'document_no' => sprintf(
                    '%s-%s-%06d',
                    $documentType,
                    $issuedAt->format('Y'),
                    $document->getKey(),
                ),
            ]);

            $this->recordAction(
                $document,
                $issuer,
                'issue',
                null,
                'pending_recipient',
                $issuerAttestation,
            );

            return $document->load(['asset', 'actions.actor']);
        });
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
}
