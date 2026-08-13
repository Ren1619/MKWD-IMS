<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    public function initialize(InventoryItem $item, int $quantity): void
    {
        if ($quantity > 0) {
            $this->createBatch($item, $quantity, now()->toDateString());
        }

        $this->synchronizeQuantity($item);
    }

    public function stockIn(InventoryItem $item, int $quantity, ?string $receivedAt = null): InventoryItem
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock-in quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $receivedAt): InventoryItem {
            $lockedItem = InventoryItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $this->createBatch($lockedItem, $quantity, $receivedAt ?? now()->toDateString());
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
                ->oldest('batch_number')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $deduction = min($remaining, $batch->quantity_remaining);
                $batch->decrement('quantity_remaining', $deduction);
                $remaining -= $deduction;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException("Only {$lockedItem->quantity} units are available.");
            }

            $stockOut = $lockedItem->stockOuts()->create([
                ...$data,
                'stocked_out_at' => $data['stocked_out_at'] ?? now()->toDateString(),
            ]);

            $this->synchronizeQuantity($lockedItem);

            return $stockOut;
        });
    }

    private function createBatch(InventoryItem $item, int $quantity, string $receivedAt): InventoryItemBatch
    {
        $nextBatchNumber = ((int) $item->batches()->max('batch_number')) + 1;

        return $item->batches()->create([
            'batch_number' => $nextBatchNumber,
            'quantity_in' => $quantity,
            'quantity_remaining' => $quantity,
            'received_at' => $receivedAt,
        ]);
    }

    private function synchronizeQuantity(InventoryItem $item): void
    {
        $item->update([
            'quantity' => (int) $item->batches()->sum('quantity_remaining'),
        ]);
    }
}
