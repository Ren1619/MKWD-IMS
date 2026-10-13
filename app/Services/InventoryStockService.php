<?php

namespace App\Services;

use App\Models\HrisReference;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    public function __construct(private InventoryStockGuard $guard) {}

    public function issuableQuantity(InventoryItem $item): int
    {
        return (int) $item->batches()->eligibleForIssue(now('Asia/Manila')->toDateString())->sum('quantity_remaining');
    }

    /** @param array<string, mixed> $receipt */
    public function initialize(InventoryItem $item, array $receipt): void
    {
        DB::transaction(function () use ($item, $receipt): void {
            $this->guard->assertMovementAllowed();
            if ((int) $receipt['quantity'] > 0) {
                $this->createBatch($item, $receipt);
            }
            $this->synchronizeQuantity($item);
        });
    }

    /** @param array<string, mixed> $receipt */
    public function stockIn(InventoryItem $item, array $receipt, ?int $countSessionId = null): InventoryItem
    {
        $quantity = (int) $receipt['quantity'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $receipt, $countSessionId): InventoryItem {
            $this->guard->assertMovementAllowed($countSessionId);
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $this->createBatch($lockedItem, $receipt);
            $this->synchronizeQuantity($lockedItem);

            $lockedItem->refresh();
            $lockedItem->load(['batches', 'seriesCategory.classCategory.majorCategory']);

            return $lockedItem;
        });
    }

    /** @param array<string, mixed> $receipt */
    public function receiveReturn(InventoryItem $item, array $receipt): InventoryItemBatch
    {
        if ((int) $receipt['quantity'] <= 0) {
            throw new InvalidArgumentException('Returned quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $receipt): InventoryItemBatch {
            $this->guard->assertMovementAllowed();
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $batch = $this->createBatch($lockedItem, $receipt);
            $this->synchronizeQuantity($lockedItem);

            return $batch;
        });
    }

    /** @param array<string, mixed> $data */
    public function stockOut(InventoryItem $item, array $data, ?int $countSessionId = null): InventoryItemStockOut
    {
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock-out quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $data, $quantity, $countSessionId): InventoryItemStockOut {
            $this->guard->assertMovementAllowed($countSessionId);
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $remaining = $quantity;
            $issueDate = Carbon::parse($data['stocked_out_at'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->toDateString();
            if ($issueDate > now('Asia/Manila')->toDateString()) {
                throw new InvalidArgumentException('A future issuance cannot be posted as an actual stock release.');
            }

            $batches = $lockedItem->batches()
                ->eligibleForIssue($issueDate)
                ->when($countSessionId !== null, function ($query) use ($countSessionId): void {
                    $source = 'Segregated receipt during '.InventoryCountSession::query()->findOrFail($countSessionId)->document_no;
                    $query->where(fn ($batchQuery) => $batchQuery->whereNull('source')->orWhere('source', '!=', $source));
                })
                ->reorder()
                ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiration_date')
                ->orderBy('received_at')
                ->orderBy('batch_number')
                ->lockForUpdate()
                ->get();

            if ($batches->sum('quantity_remaining') < $quantity) {
                throw new InvalidArgumentException('Only '.$batches->sum('quantity_remaining').' units are issuable on this date. Expired, future-dated, and count-segregated batches cannot be released.');
            }

            $stockOut = $lockedItem->stockOuts()->create([
                ...$data,
                'department_name' => $data['department_name'] ?? data_get(HrisReference::query()->whereKey($data['recipient_reference_id'] ?? null)->first()?->metadata, 'position.department.name'),
                'stocked_out_at' => $issueDate,
            ]);

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $deduction = min($remaining, $batch->quantity_remaining);
                $batch->decrement('quantity_remaining', $deduction);
                $stockOut->allocations()->create([
                    'inventory_item_batch_id' => $batch->getKey(),
                    'quantity' => $deduction,
                    'unit_cost' => $batch->unit_cost,
                ]);
                $remaining -= $deduction;
            }

            $this->synchronizeQuantity($lockedItem);

            return $stockOut->load('allocations.batch');
        });
    }

    /** @param array<string, mixed> $receipt */
    private function createBatch(InventoryItem $item, array $receipt): InventoryItemBatch
    {
        $quantity = (int) $receipt['quantity'];
        $nextBatchNumber = ((int) $item->batches()->max('batch_number')) + 1;

        return $item->batches()->create([
            'batch_number' => $nextBatchNumber,
            'quantity_in' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $receipt['unit_cost'],
            'received_at' => $receipt['received_at'] ?? now()->toDateString(),
            'expiration_date' => $receipt['expiration_date'] ?? null,
            'source' => $receipt['source'] ?? null,
            'reference_no' => $receipt['reference_no'] ?? null,
            'notes' => $receipt['batch_notes'] ?? null,
        ]);
    }

    private function synchronizeQuantity(InventoryItem $item): void
    {
        $item->update([
            'quantity' => (int) $item->batches()->sum('quantity_remaining'),
        ]);
    }
}
