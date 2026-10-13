<?php

namespace App\Services;

use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyRequestWorkflow
{
    public function __construct(
        private InventoryStockService $stockService,
        private SupplyShortageProcurementService $shortageProcurement,
        private IssuanceLiquidationService $liquidations,
        private InventoryStockGuard $stockGuard,
    ) {}

    public function transition(SupplyRequest $request, User $actor, string $action, ?string $remarks): SupplyRequest
    {
        return DB::transaction(function () use ($request, $actor, $action, $remarks): SupplyRequest {
            $this->stockGuard->lock();
            $request = SupplyRequest::query()->whereKey($request->getKey())->lockForUpdate()->with('lines.item')->firstOrFail();
            if ($this->isRequestedForActor($request, $actor) && in_array($action, ['approve', 'review', 'release'], true)) {
                throw ValidationException::withMessages(['action' => 'A requester cannot approve, review, or release their own request.']);
            }
            $from = $request->status;
            $to = match ($action) {
                'approve' => $this->allowed($from, ['submitted'], 'approved'),
                'review' => $this->review($request, $actor),
                'release' => $this->release($request, $actor),
                'reject' => $this->allowed($from, ['submitted', 'approved', 'awaiting_replenishment', 'ready_for_release'], 'rejected'),
                'cancel' => $this->allowed($from, ['submitted', 'approved', 'awaiting_replenishment', 'ready_for_release'], 'cancelled'),
                default => throw ValidationException::withMessages(['action' => 'Unsupported workflow action.']),
            };
            $request->update(['status' => $to, 'completed_at' => in_array($to, ['released', 'rejected', 'cancelled'], true) ? now() : null]);
            $request->actions()->create(['actor_user_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'attestation' => 'I confirm this action is accurate and authorized.', 'metadata' => ['ip' => request()->ip()]]);

            return $request->load(['lines.item', 'actions.actor']);
        });
    }

    public function reassessAfterProcurement(
        SupplyRequest $request,
        User $actor,
        string $procurementReference,
    ): SupplyRequest {
        return DB::transaction(function () use ($request, $actor, $procurementReference): SupplyRequest {
            $this->stockGuard->lock();
            $request = SupplyRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->with('lines.item')
                ->firstOrFail();

            if (! in_array($request->status, ['approved', 'awaiting_replenishment', 'partially_released'], true)) {
                return $request;
            }

            $from = $request->status;
            $to = $this->review($request, $actor, synchronizeProcurement: false);
            $request->update(['status' => $to]);
            $request->actions()->create([
                'actor_user_id' => $actor->getKey(),
                'action' => 'reassess_after_procurement',
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => "Stock reservations recalculated after accepting {$procurementReference}.",
                'attestation' => 'Automatically reconciled with the accepted procurement delivery.',
            ]);

            return $request->load(['lines.item', 'actions.actor']);
        });
    }

    private function review(
        SupplyRequest $request,
        User $actor,
        bool $synchronizeProcurement = true,
    ): string {
        $this->allowed($request->status, ['approved', 'awaiting_replenishment', 'partially_released', 'ready_for_release'], 'approved');
        $hasShortage = false;
        foreach ($request->lines as $line) {
            $approved = $line->quantity_requested;
            if ($line->is_new_item || $line->item === null) {
                $line->update(['quantity_approved' => $approved, 'quantity_reserved' => 0]);
                $hasShortage = true;

                continue;
            }
            $otherReserved = (int) SupplyRequestLine::query()
                ->where('inventory_item_id', $line->inventory_item_id)
                ->whereKeyNot($line->getKey())
                ->selectRaw('COALESCE(SUM(quantity_reserved - quantity_released), 0) AS outstanding')
                ->value('outstanding');
            $remainingRequired = max(0, $approved - $line->quantity_released);
            $additionalReservation = min($remainingRequired, max(0, $this->stockService->issuableQuantity($line->item) - $otherReserved));
            $reserved = $line->quantity_released + $additionalReservation;
            $line->update(['quantity_approved' => $approved, 'quantity_reserved' => $reserved]);
            $hasShortage = $hasShortage || $reserved < $approved;
        }

        if ($synchronizeProcurement) {
            $this->shortageProcurement->synchronize($request, $actor);
        }

        return $hasShortage ? 'awaiting_replenishment' : 'ready_for_release';
    }

    private function release(SupplyRequest $request, User $actor): string
    {
        $this->allowed($request->status, ['ready_for_release', 'awaiting_replenishment', 'partially_released'], 'ready_for_release');
        foreach ($request->lines->whereNotNull('inventory_item_id')->groupBy('inventory_item_id') as $lines) {
            $item = $lines->first()->item;
            $remainingReserved = $lines->sum(fn (SupplyRequestLine $line): int => max(0, $line->quantity_reserved - $line->quantity_released));
            if ($item !== null && $remainingReserved > $this->stockService->issuableQuantity($item)) {
                throw ValidationException::withMessages(['action' => 'Reserved batches are no longer issuable. Review stock availability again before releasing this request.']);
            }
        }
        $releasedAny = false;
        $issuedLines = [];
        $issuedStockOuts = [];
        $hasShortage = false;
        foreach ($request->lines as $line) {
            $quantity = $line->quantity_reserved - $line->quantity_released;
            if ($quantity > 0 && $line->item !== null) {
                $issuedStockOuts[] = $this->stockService->stockOut($line->item, ['quantity' => $quantity, 'recipient_reference_id' => $request->requester_reference_id, 'recipient_name' => $request->requester_name, 'ris_no' => $request->ris_no, 'responsibility_center_code' => $request->responsibility_center_code, 'notes' => $request->purpose, 'supply_request_line_id' => $line->id]);
                $line->increment('quantity_released', $quantity);
                $issuedLines[] = ['supply_request_line_id' => $line->id, 'item_description' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure, 'quantity_issued' => $quantity];
                $releasedAny = true;
            }
            $line->refresh();
            $hasShortage = $hasShortage || $line->quantity_released < $line->quantity_approved;
        }
        if (! $releasedAny && $hasShortage) {
            throw ValidationException::withMessages(['action' => 'No reserved stock is available for release.']);
        }

        if ($releasedAny) {
            $slip = $this->liquidations->issue($request, $actor, $issuedLines);
            foreach ($issuedStockOuts as $stockOut) {
                $stockOut->issuanceSlip()->associate($slip);
                $stockOut->save();
            }
        }

        return $hasShortage ? 'partially_released' : 'released';
    }

    private function isRequestedForActor(SupplyRequest $request, User $actor): bool
    {
        if ($request->requester_reference_id !== null) {
            return $actor->hris_reference_id !== null
                && $request->requester_reference_id === $actor->hris_reference_id;
        }

        return $request->requester_user_id === $actor->getKey();
    }

    /** @param list<string> $allowed */
    private function allowed(string $current, array $allowed, string $next): string
    {
        if (! in_array($current, $allowed, true)) {
            throw ValidationException::withMessages(['action' => "This action is not allowed while the request is {$current}."]);
        }

        return $next;
    }
}
