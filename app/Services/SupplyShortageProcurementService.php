<?php

namespace App\Services;

use App\Models\ProcurementRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use Illuminate\Support\Str;

class SupplyShortageProcurementService
{
    public function synchronize(SupplyRequest $supplyRequest, User $actor): ?ProcurementRequest
    {
        $supplyRequest->loadMissing('lines.item');
        $shortages = $supplyRequest->lines
            ->map(fn (SupplyRequestLine $line): array => [
                'line' => $line,
                'quantity' => max(0, $line->quantity_approved - $line->quantity_reserved),
            ])
            ->filter(fn (array $shortage): bool => $shortage['quantity'] > 0)
            ->values();
        $procurementRequest = ProcurementRequest::query()
            ->where('supply_request_id', $supplyRequest->getKey())
            ->whereNotIn('status', ['accepted', 'rejected', 'cancelled'])
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if ($shortages->isEmpty()) {
            if (in_array($procurementRequest?->status, ['draft', 'intake', 'returned_for_compliance'], true)) {
                $fromStatus = $procurementRequest->status;
                $procurementRequest->update(['status' => 'cancelled', 'completed_at' => now()]);
                $procurementRequest->actions()->create([
                    'actor_user_id' => $actor->getKey(),
                    'action' => 'cancel_shortage_resolved',
                    'from_status' => $fromStatus,
                    'to_status' => 'cancelled',
                    'remarks' => 'The linked supply shortage was resolved before procurement submission.',
                    'attestation' => 'Automatically reconciled with the linked supply request.',
                ]);
            }

            return null;
        }

        if ($procurementRequest === null) {
            $hasExistingItems = $shortages->contains(
                fn (array $shortage): bool => $shortage['line']->inventory_item_id !== null,
            );
            $hasNewItems = $shortages->contains(
                fn (array $shortage): bool => $shortage['line']->inventory_item_id === null,
            );
            $type = $hasExistingItems && $hasNewItems
                ? 'mixed'
                : ($hasExistingItems ? 'replenishment' : 'new_item');
            $procurementRequest = ProcurementRequest::query()->create([
                'pr_no' => 'TMP-'.Str::uuid(),
                'supply_request_id' => $supplyRequest->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'type' => $type,
                'source' => 'request_shortage',
                'status' => 'intake',
                'procurement_category' => 'supplies',
                'preprocurement' => ['approved_request_reference' => $supplyRequest->ris_no, 'end_user' => $supplyRequest->requester_name ?? $supplyRequest->requester?->name, 'department' => '', 'division' => '', 'is_replacement' => false],
                'purpose' => "Fulfill shortage for {$supplyRequest->ris_no}: {$supplyRequest->purpose}",
                'responsibility_center_code' => $supplyRequest->responsibility_center_code,
                'required_at' => $supplyRequest->date_needed,
            ]);
            $procurementRequest->update([
                'pr_no' => sprintf('REQ-%s-%06d', now()->format('Y'), $procurementRequest->getKey()),
            ]);
            $procurementRequest->actions()->create([
                'actor_user_id' => $actor->getKey(),
                'action' => 'receive_from_shortage',
                'to_status' => 'intake',
                'remarks' => "Automatically prepared from {$supplyRequest->ris_no}.",
                'attestation' => 'Generated from reviewed and approved supply shortages.',
            ]);
        }

        if (! in_array($procurementRequest->status, ['draft', 'intake', 'returned_for_compliance'], true)) {
            return $procurementRequest;
        }

        $shortageLineIds = $shortages->map(
            fn (array $shortage): int => $shortage['line']->getKey(),
        );
        $procurementRequest->lines()
            ->whereNotNull('supply_request_line_id')
            ->whereNotIn('supply_request_line_id', $shortageLineIds)
            ->delete();

        foreach ($shortages as $shortage) {
            /** @var SupplyRequestLine $line */
            $line = $shortage['line'];
            $procurementRequest->lines()->updateOrCreate(
                ['supply_request_line_id' => $line->getKey()],
                [
                    'inventory_item_id' => $line->inventory_item_id,
                    'series_category_id' => $line->item?->series_category_id,
                    'item_name' => $line->inventory_item_id !== null ? $line->item->name : $line->item_name,
                    'specifications' => $line->specifications,
                    'unit_of_measure' => $line->inventory_item_id !== null ? $line->item->unit_of_measure : $line->unit_of_measure,
                    'quantity' => $shortage['quantity'],
                    'estimated_unit_cost' => $line->estimated_unit_cost ?? 0,
                ],
            );
        }

        return $procurementRequest->refresh();
    }
}
