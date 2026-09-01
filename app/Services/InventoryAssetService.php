<?php

namespace App\Services;

use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAssetService
{
    public function __construct(private PropertyAccountabilityService $accountability) {}

    public function assign(InventoryAsset $asset, HrisReference $reference, User $actor): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $reference, $actor): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedAsset->is_assignable) {
                throw new InvalidArgumentException('Disposed or lost assets cannot be assigned.');
            }

            $supersededDocument = $lockedAsset->accountabilityDocuments()
                ->whereIn('status', ['pending_recipient', 'active'])
                ->first();

            $this->accountability->closeCurrentForAsset($lockedAsset, $actor, 'superseded', 'Custody transferred to a different accountable person.');
            $lockedAsset->custodianAssignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            $assignment = $lockedAsset->custodianAssignments()->create([
                'hris_reference_id' => $reference->id,
                'assigned_at' => now(),
            ]);

            $lockedAsset->update(['current_custodian_reference_id' => $reference->id]);
            $this->accountability->issue($lockedAsset->refresh(), $assignment, $actor, $supersededDocument);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    public function unassign(InventoryAsset $asset, User $actor, string $reason = 'Property returned to the Property Unit.'): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $actor, $reason): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $this->accountability->closeCurrentForAsset($lockedAsset, $actor, 'returned', $reason);
            $lockedAsset->custodianAssignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
            $lockedAsset->update([
                'current_custodian_reference_id' => null,
            ]);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    /** @param array<string, mixed> $data */
    public function borrow(InventoryAsset $asset, array $data): InventoryAssetBorrowing
    {
        return DB::transaction(function () use ($asset, $data): InventoryAssetBorrowing {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->activeBorrowing()->exists()) {
                throw new InvalidArgumentException('This asset is already borrowed.');
            }

            if (! $lockedAsset->is_borrowable) {
                throw new InvalidArgumentException('Only active assets in good or fair condition can be borrowed.');
            }

            $borrowing = $lockedAsset->borrowings()->create([
                ...$data,
                'borrowed_at' => now(),
                'status' => 'borrowed',
            ]);

            return $borrowing;
        });
    }

    public function returnBorrowed(InventoryAsset $asset, ?string $notes = null): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $notes): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $borrowing = $lockedAsset->activeBorrowing()->first();

            if (! $borrowing) {
                throw new InvalidArgumentException('This asset has no active borrowing record.');
            }

            $borrowing->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_notes' => $notes,
            ]);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    /** @param array{lifecycle_status: string, condition_status: string} $state */
    public function updateState(InventoryAsset $asset, array $state): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $state): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $nextLifecycle = AssetLifecycleStatus::from($state['lifecycle_status']);

            if ($nextLifecycle === AssetLifecycleStatus::Disposed && $lockedAsset->determineCustodyStatus() !== AssetCustodyStatus::Available) {
                throw new InvalidArgumentException('Return and unassign the asset before marking it as disposed.');
            }

            $lockedAsset->update($state);

            return $lockedAsset->refresh();
        });
    }
}
