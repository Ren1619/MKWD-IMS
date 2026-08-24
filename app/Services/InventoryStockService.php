<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    /** @param array<string, mixed> $receipt */
    public function initialize(InventoryItem $item, array $receipt): void
    {
        $quantity = (int) $receipt['quantity'];

        if ($quantity > 0) {
            $this->createBatch($item, $receipt);
        }

        $this->synchronizeQuantity($item);
    }

    /** @param array<string, mixed> $receipt */
    public function stockIn(InventoryItem $item, array $receipt): InventoryItem
    {
        $quantity = (int) $receipt['quantity'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $receipt): InventoryItem {
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $this->createBatch($lockedItem, $receipt);
            $this->synchronizeQuantity($lockedItem);

            $lockedItem->refresh();
            $lockedItem->load(['batches', 'seriesCategory.classCategory.majorCategory']);

            return $lockedItem;
        });
    }

    /** @param array<string, mixed> $data */
    public function stockOut(InventoryItem $item, array $data): InventoryItemStockOut
    {
        $quantity = (int) $data['quantity'];

        return DB::transaction(function () use ($item, $data, $quantity): InventoryItemStockOut {
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $remaining = $quantity;

            $batches = $lockedItem->batches()
                ->where('quantity_remaining', '>', 0)
                ->reorder('received_at')
                ->orderBy('batch_number')
                ->lockForUpdate()
                ->get();

            if ($batches->sum('quantity_remaining') < $quantity) {
                throw new InvalidArgumentException("Only {$lockedItem->quantity} units are available.");
            }

            $stockOut = $lockedItem->stockOuts()->create([
                ...$data,
                'stocked_out_at' => $data['stocked_out_at'] ?? now()->toDateString(),
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
