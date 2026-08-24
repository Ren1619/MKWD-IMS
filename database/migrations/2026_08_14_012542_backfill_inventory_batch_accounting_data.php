<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('inventory_items')
            ->orderBy('inventory_item_id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('inventory_item_batches')
                        ->where('inventory_item_id', $item->inventory_item_id)
                        ->update([
                            'unit_cost' => $item->price,
                            'expiration_date' => $item->expiration_date,
                        ]);

                    $this->reconstructAllocations((int) $item->inventory_item_id);
                }
            }, 'inventory_item_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_item_stock_out_allocations')->delete();

        DB::table('inventory_items')
            ->orderBy('inventory_item_id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $batch = DB::table('inventory_item_batches')
                        ->where('inventory_item_id', $item->inventory_item_id)
                        ->orderBy('received_at')
                        ->orderBy('batch_number')
                        ->first();

                    if ($batch !== null) {
                        DB::table('inventory_items')
                            ->where('inventory_item_id', $item->inventory_item_id)
                            ->update([
                                'price' => $batch->unit_cost,
                                'expiration_date' => $batch->expiration_date,
                            ]);
                    }
                }
            }, 'inventory_item_id');
    }

    private function reconstructAllocations(int $inventoryItemId): void
    {
        $batches = DB::table('inventory_item_batches')
            ->where('inventory_item_id', $inventoryItemId)
            ->orderBy('received_at')
            ->orderBy('batch_number')
            ->get()
            ->map(fn ($batch): array => [
                'id' => (int) $batch->inventory_item_batch_id,
                'unallocated' => (int) $batch->quantity_in - (int) $batch->quantity_remaining,
                'unit_cost' => $batch->unit_cost,
            ])
            ->all();

        $stockOuts = DB::table('inventory_item_stock_outs')
            ->where('inventory_item_id', $inventoryItemId)
            ->orderBy('stocked_out_at')
            ->orderBy('inventory_item_stock_out_id')
            ->get();

        $now = now();

        foreach ($stockOuts as $stockOut) {
            $remaining = (int) $stockOut->quantity;

            foreach ($batches as &$batch) {
                if ($remaining === 0) {
                    break;
                }

                if ($batch['unallocated'] === 0) {
                    continue;
                }

                $allocated = min($remaining, $batch['unallocated']);

                DB::table('inventory_item_stock_out_allocations')->insert([
                    'inventory_item_stock_out_id' => $stockOut->inventory_item_stock_out_id,
                    'inventory_item_batch_id' => $batch['id'],
                    'quantity' => $allocated,
                    'unit_cost' => $batch['unit_cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $batch['unallocated'] -= $allocated;
                $remaining -= $allocated;
            }
            unset($batch);
        }
    }
};
