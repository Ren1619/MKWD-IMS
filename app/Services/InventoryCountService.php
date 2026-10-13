<?php

namespace App\Services;

use App\Models\InventoryCountAction;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryStockControl;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryCountService
{
    public function __construct(
        private InventoryStockGuard $guard,
        private InventoryStockService $stock,
        private AuditLogger $audit,
    ) {}

    /** @param array{title: string, scheduled_date: string, memorandum_reference: string, team_details: string} $data */
    public function create(array $data, User $actor): InventoryCountSession
    {
        return DB::transaction(function () use ($data, $actor): InventoryCountSession {
            $session = InventoryCountSession::query()->create([...$data, 'created_by_user_id' => $actor->id]);
            $session->update(['document_no' => sprintf('COUNT-%s-%06d', now('Asia/Manila')->format('Y'), $session->id)]);
            $this->recordAction($session, $actor, 'plan');

            return $session;
        });
    }

    /** @param array<string, mixed> $data */
    public function transition(InventoryCountSession $session, array $data, User $actor): void
    {
        DB::transaction(function () use ($session, $data, $actor): void {
            $control = $this->guard->lock();
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            $allowed = match ($action) {
                'start' => ['planned'],
                'submit' => ['counting'],
                'verify', 'return_for_recount' => ['submitted'],
                'approve' => ['verified'],
                'cancel' => ['planned', 'counting', 'submitted', 'verified'],
                default => [],
            };
            $this->assertStatus($session, $allowed);
            if ($action === 'start') {
                $this->start($session, $control);
            } elseif ($action === 'submit' || $action === 'verify' || $action === 'approve') {
                $this->assertComplete($session);
                if ($action === 'verify' || $action === 'approve') {
                    $participants = $session->lines()->join('inventory_count_entries', 'inventory_count_lines.id', '=', 'inventory_count_entries.inventory_count_line_id')
                        ->pluck('recorded_by_user_id')->push($session->created_by_user_id);
                    if ($participants->contains($actor->id) || ($action === 'approve' && $session->verified_by_user_id === $actor->id)) {
                        throw ValidationException::withMessages(['action' => 'The planner/count recorders, verifier, and approver must be independent people.']);
                    }
                }
                if ($action === 'submit') {
                    $session->update(['status' => 'submitted']);
                } elseif ($action === 'verify') {
                    $session->update(['status' => 'verified', 'verified_by_user_id' => $actor->id]);
                } else {
                    $this->approve($session, $control, $actor);
                }
            } elseif ($action === 'return_for_recount') {
                $session->update(['status' => 'counting', 'verified_by_user_id' => null]);
            } elseif ($action === 'cancel') {
                $session->update(['status' => 'cancelled', 'completed_at' => now()]);
                if ($control->active_count_session_id === $session->id) {
                    $control->update(['active_count_session_id' => null]);
                }
            }
            $this->recordAction($session, $actor, $action, $data['remarks'] ?? null);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function record(InventoryCountSession $session, InventoryCountLine $line, array $data, User $actor): void
    {
        DB::transaction(function () use ($session, $line, $data, $actor): void {
            $this->guard->assertMovementAllowed($session->id);
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($session, ['counting']);
            $line = $session->lines()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $latest = $line->latestEntry()->first();
            if (($data['latest_entry_id'] ?? null) !== $latest?->id) {
                throw ValidationException::withMessages(['quantity' => 'Another observation has been saved. Reload this item before recording a recount.']);
            }
            if ($latest !== null && blank($data['remarks'] ?? null)) {
                throw ValidationException::withMessages(['remarks' => 'Explain the recount or correction; previous observations remain on record.']);
            }
            $entry = $line->entries()->create([
                ...collect($data)->only(['quantity', 'condition', 'location', 'aisle', 'bin', 'counter_name', 'checker_name', 'validator_name', 'remarks'])->all(),
                'observed_quantity' => $data['quantity'],
                'after_count_issues_at_observation' => $line->after_count_issues,
                'quantity' => $data['quantity'] + $line->after_count_issues,
                'recorded_by_user_id' => $actor->id,
            ]);
            $line->update(collect($data)->only(['adjustment_unit_cost', 'valuation_basis'])->all());
            $this->recordAction($session, $actor, $latest === null ? 'record_count' : 'recount', $data['remarks'] ?? null, ['line_id' => $line->id, 'entry_id' => $entry->id, 'previous_entry_id' => $latest?->id]);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function movement(InventoryCountSession $session, array $data, User $actor): void
    {
        DB::transaction(function () use ($session, $data, $actor): void {
            $this->guard->assertMovementAllowed($session->id);
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($session, ['counting']);
            $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $previous = InventoryCountAction::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($previous !== null) {
                if ($previous->inventory_count_session_id !== $session->id || $previous->actor_user_id !== $actor->id || ($previous->metadata['request_hash'] ?? null) !== $requestHash) {
                    throw ValidationException::withMessages(['action' => 'This movement key has already been used for a different submission.']);
                }

                return;
            }
            $line = $session->lines()->whereKey($data['line_id'])->with('latestEntry')->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['quantity'];
            if ($data['action'] === 'segregated_receipt') {
                $item = $this->stock->stockIn($line->item, [
                    'quantity' => $quantity, 'unit_cost' => $data['unit_cost'],
                    'reference_no' => $data['reference_no'], 'received_at' => now('Asia/Manila')->toDateString(),
                    'source' => 'Segregated receipt during '.$session->document_no,
                    'batch_notes' => $data['remarks'], 'expiration_date' => $data['expiration_date'] ?? null,
                ], $session->id);
                $line->increment('segregated_receipts', $quantity);
                $metadata = ['line_id' => $line->id, 'batch_id' => $item->batches->sortByDesc('inventory_item_batch_id')->first()->getKey(), 'quantity' => $quantity, 'approval_reference' => $data['approval_reference']];
            } else {
                $afterCount = $data['timing'] === 'after_count';
                if (($afterCount && $line->latestEntry === null) || (! $afterCount && $line->latestEntry !== null)) {
                    throw ValidationException::withMessages(['timing' => 'Before-count issues require an uncounted item; after-count issues require an existing count.']);
                }
                $availableCountedQuantity = $line->latestEntry?->quantity - $line->after_count_issues;
                if ($afterCount && $quantity > $availableCountedQuantity) {
                    throw ValidationException::withMessages(['quantity' => 'The emergency issue exceeds the remaining counted quantity.']);
                }
                if (! $afterCount && $quantity > $line->book_quantity - $line->before_count_issues) {
                    throw ValidationException::withMessages(['quantity' => 'Before-count issues cannot consume segregated receipts.']);
                }
                try {
                    $issue = $this->stock->stockOut($line->item, [
                        'quantity' => $quantity, 'recipient_name' => $data['recipient_name'],
                        'ris_no' => $data['reference_no'], 'stocked_out_at' => now('Asia/Manila')->toDateString(),
                        'notes' => ($afterCount ? 'ISSUED AFTER COUNT' : 'BEFORE COUNT, AFTER-CUT-OFF').': '.$data['remarks'],
                    ], $session->id);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
                }
                $line->increment($afterCount ? 'after_count_issues' : 'before_count_issues', $quantity);
                $this->invalidateReservations($line->inventory_item_id, $session, $actor);
                $metadata = ['line_id' => $line->id, 'stock_out_id' => $issue->getKey(), 'quantity' => $quantity, 'timing' => $data['timing'], 'approval_reference' => $data['approval_reference']];
            }
            $this->recordAction($session, $actor, $data['action'], $data['remarks'], [...$metadata, 'request_hash' => $requestHash], $data['idempotency_key']);
        }, attempts: 3);
    }

    private function start(InventoryCountSession $session, InventoryStockControl $control): void
    {
        if ($control->active_count_session_id !== null) {
            throw ValidationException::withMessages(['action' => 'Finish or cancel the active physical count before starting another.']);
        }
        if ($session->scheduled_date->toDateString() > now('Asia/Manila')->toDateString()) {
            throw ValidationException::withMessages(['action' => 'The count cannot start before its scheduled date.']);
        }
        $items = InventoryItem::query()->where(fn ($query) => $query->where('status', 'active')->orWhere('quantity', '>', 0))
            ->with(['batches' => fn ($query) => $query->lockForUpdate()])
            ->orderBy('inventory_item_id')->lockForUpdate()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['action' => 'There are no consumable inventory items to count.']);
        }
        foreach ($items as $item) {
            $batches = $item->batches;
            $quantity = (int) $batches->sum('quantity_remaining');
            if ($quantity !== $item->quantity) {
                throw ValidationException::withMessages(['action' => "Resolve the batch-to-stock mismatch for {$item->name} before establishing cut-off."]);
            }
            $amount = BigDecimal::zero();
            foreach ($batches as $batch) {
                $amount = $amount->plus(BigDecimal::of($batch->unit_cost)->multipliedBy($batch->quantity_remaining));
            }
            $session->lines()->create([
                'inventory_item_id' => $item->getKey(), 'tag_no' => $session->document_no.'-'.$item->getKey(),
                'stock_number' => $item->stock_number, 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure,
                'book_quantity' => $quantity, 'unit_cost' => $quantity > 0 ? (string) $amount->dividedBy($quantity, 2, RoundingMode::HalfUp) : '0.00',
            ]);
        }
        $session->update(['status' => 'counting', 'cutoff_at' => now()]);
        $control->update(['active_count_session_id' => $session->id]);
    }

    private function assertComplete(InventoryCountSession $session): void
    {
        $session->load('lines.entries');
        foreach ($session->lines as $line) {
            $entries = $line->entries->sortByDesc('id')->values();
            $entry = $entries->first();
            if ($entry === null) {
                throw ValidationException::withMessages(['action' => "Record a physical count, including an explicit zero, for {$line->item_name}."]);
            }
            $variance = $entry->quantity - ($line->book_quantity - $line->before_count_issues);
            if ($variance !== 0 && ($entries->count() < 2 || $entries[1]->quantity !== $entry->quantity || blank($entry->remarks))) {
                throw ValidationException::withMessages(['action' => "Confirm the variance for {$line->item_name} with an agreeing recount and explanation."]);
            }
            if ($entry->quantity < $line->after_count_issues) {
                throw ValidationException::withMessages(['action' => "The count for {$line->item_name} cannot be below its after-count issues."]);
            }
            if ($variance > 0 && ($line->adjustment_unit_cost === null || blank($line->valuation_basis))) {
                throw ValidationException::withMessages(['action' => "Provide the approved unit-cost basis for the overage of {$line->item_name}."]);
            }
        }
    }

    private function approve(InventoryCountSession $session, InventoryStockControl $control, User $actor): void
    {
        if ($control->active_count_session_id !== $session->id) {
            throw ValidationException::withMessages(['action' => 'The stock cut-off is no longer active for this count.']);
        }
        $session->load('lines.latestEntry');
        foreach ($session->lines->sortBy('inventory_item_id') as $line) {
            $item = InventoryItem::withTrashed()->whereKey($line->inventory_item_id)->lockForUpdate()->firstOrFail();
            $expectedLive = $line->book_quantity - $line->before_count_issues - $line->after_count_issues + $line->segregated_receipts;
            if ($item->quantity !== $expectedLive) {
                throw ValidationException::withMessages(['action' => "Stock changed outside the count controls for {$line->item_name}; investigate before approval."]);
            }
            $variance = $line->latestEntry->quantity - ($line->book_quantity - $line->before_count_issues);
            if ($variance > 0) {
                $received = $this->stock->stockIn($item, ['quantity' => $variance, 'unit_cost' => $line->adjustment_unit_cost,
                    'source' => 'Physical count adjustment', 'reference_no' => $session->document_no,
                    'received_at' => now('Asia/Manila')->toDateString(), 'batch_notes' => $line->valuation_basis], $session->id);
                $batch = $received->batches->sortByDesc('inventory_item_batch_id')->first();
                $line->adjustments()->create(['inventory_item_batch_id' => $batch->getKey(), 'quantity' => $variance, 'unit_cost' => $batch->unit_cost]);
            } elseif ($variance < 0) {
                $remaining = -$variance;
                $segregatedSource = 'Segregated receipt during '.$session->document_no;
                foreach ($item->batches()->where('quantity_remaining', '>', 0)
                    ->where(fn ($query) => $query->whereNull('source')->orWhere('source', '!=', $segregatedSource))
                    ->orderBy('received_at')->orderBy('batch_number')->lockForUpdate()->get() as $batch) {
                    $deduction = min($remaining, $batch->quantity_remaining);
                    if ($deduction > 0) {
                        $batch->decrement('quantity_remaining', $deduction);
                        $line->adjustments()->create(['inventory_item_batch_id' => $batch->getKey(), 'quantity' => -$deduction, 'unit_cost' => $batch->unit_cost]);
                        $remaining -= $deduction;
                    }
                    if ($remaining === 0) {
                        break;
                    }
                }
                if ($remaining !== 0) {
                    throw ValidationException::withMessages(['action' => 'The shortage exceeds the available receipt batches.']);
                }
                $item->update(['quantity' => (int) $item->batches()->sum('quantity_remaining')]);
            }
            if ($variance !== 0) {
                $this->invalidateReservations($item->getKey(), $session, $actor);
            }
        }
        $session->update(['status' => 'approved', 'approved_by_user_id' => $actor->id, 'completed_at' => now()]);
        $control->update(['active_count_session_id' => null]);
    }

    private function invalidateReservations(int $itemId, InventoryCountSession $session, User $actor): void
    {
        $requestIds = SupplyRequestLine::query()->where('inventory_item_id', $itemId)
            ->whereColumn('quantity_reserved', '>', 'quantity_released')->pluck('supply_request_id');
        $requests = SupplyRequest::query()->whereIn('id', $requestIds)->whereIn('status', ['ready_for_release', 'awaiting_replenishment', 'partially_released'])->lockForUpdate()->get();
        foreach ($requests as $request) {
            $from = $request->status;
            $request->lines()->update(['quantity_reserved' => DB::raw('quantity_released')]);
            $request->update(['status' => 'approved']);
            $request->actions()->create(['actor_user_id' => $actor->id, 'action' => 'count_adjustment_requires_review', 'from_status' => $from, 'to_status' => 'approved',
                'remarks' => 'Reservations cleared during '.$session->document_no.'; review stock availability before release.',
                'attestation' => 'Reservations reconciled with the physical count movement.']);
        }
    }

    /** @param list<string> $statuses */
    private function assertStatus(InventoryCountSession $session, array $statuses): void
    {
        if (! in_array($session->status, $statuses, true)) {
            throw ValidationException::withMessages(['action' => 'This action is unavailable at the current count stage.']);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordAction(InventoryCountSession $session, User $actor, string $action, ?string $remarks = null, array $metadata = [], ?string $idempotencyKey = null): void
    {
        $session->actions()->create(['actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'remarks' => $remarks, 'metadata' => $metadata, 'idempotency_key' => $idempotencyKey]);
        $this->audit->record('inventory.count.'.$action, 'Physical count '.$session->document_no.': '.$action, $session, newValues: $metadata, actor: $actor);
    }
}
