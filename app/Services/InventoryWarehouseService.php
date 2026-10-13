<?php

namespace App\Services;

use App\Models\InventoryBatchPlacement;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventoryWarehouseService
{
    public function __construct(private InventoryStockGuard $guard, private AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function place(InventoryItemBatch $batch, array $data, User $actor): InventoryBatchPlacement
    {
        Gate::forUser($actor)->authorize('manage-inventory');

        return DB::transaction(function () use ($batch, $data, $actor): InventoryBatchPlacement {
            $this->guard->assertMovementAllowed();
            $item = InventoryItem::withTrashed()->whereKey($batch->inventory_item_id)->lockForUpdate()->firstOrFail();
            $batch = InventoryItemBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            if ($item->trashed() || $item->status !== 'active') {
                throw ValidationException::withMessages(['warehouse' => 'Restore and activate the catalog item before recording warehouse placement.']);
            }
            $latest = $batch->placements()->latest('id')->first();
            if (($latest?->id) !== (isset($data['latest_placement_id']) ? (int) $data['latest_placement_id'] : null)) {
                throw ValidationException::withMessages(['latest_placement_id' => 'Another placement was recorded. Reload the batch before confirming its location.']);
            }
            if ($batch->quantity_remaining <= 0 || $batch->quantity_remaining !== (int) $data['observed_quantity']) {
                throw ValidationException::withMessages(['observed_quantity' => 'The observed quantity must match the current remaining batch quantity. Investigate discrepancies through receiving, return, or physical-count procedures.']);
            }
            if ($batch->received_at->toDateString() > now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['warehouse' => 'A future-dated receipt cannot be confirmed as stored today. Correct its source record first.']);
            }
            $placement = $batch->placements()->create([
                ...collect($data)->only(['warehouse', 'area', 'rack', 'bin', 'observed_quantity', 'source_evidence_reference', 'remarks'])->all(),
                'version' => $latest === null ? 1 : $latest->version + 1,
                'source_details' => ['stock_number' => $item->stock_number, 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure,
                    'batch_number' => $batch->batch_number, 'source_reference' => $batch->reference_no, 'source' => $batch->source,
                    'received_at' => $batch->received_at->toDateString(), 'expiration_date' => $batch->expiration_date?->toDateString()],
                'recorded_by_user_id' => $actor->id, 'recorded_by_name' => $actor->name,
            ]);
            $this->audit->record('inventory.warehouse.placed', 'Storage confirmed for '.$item->stock_number.' batch '.$batch->batch_number, $batch,
                oldValues: $latest?->toArray() ?? [], newValues: $placement->toArray(), actor: $actor);

            return $placement;
        }, attempts: 3);
    }
}
