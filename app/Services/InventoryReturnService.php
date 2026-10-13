<?php

namespace App\Services;

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryItemStockOutAllocation;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReturnService
{
    public function __construct(
        private InventoryStockGuard $guard,
        private InventoryStockService $stock,
        private AuditLogger $audit,
    ) {}

    /** @return list<string> */
    public static function committedStatuses(): array
    {
        return ['submitted', 'verified', 'approved'];
    }

    /** @return Builder<InventoryItemStockOutAllocation> */
    public function sources(?int $supplyRequestId = null, ?string $search = null): Builder
    {
        return InventoryItemStockOutAllocation::query()
            ->with(['batch', 'stockOut.item' => fn ($query) => $query->withTrashed()])
            ->withSum(['returnLines as committed_quantity' => fn ($query) => $query->whereHas('document', fn ($documents) => $documents->whereIn('status', self::committedStatuses()))], 'quantity_committed')
            ->whereHas('stockOut', function ($query) use ($supplyRequestId, $search): void {
                if ($supplyRequestId !== null) {
                    $query->whereHas('supplyRequestLine', fn ($lines) => $lines->where('supply_request_id', $supplyRequestId));
                }
                if (filled($search)) {
                    $query->where(fn ($nested) => $nested->where('ris_no', 'like', '%'.$search.'%')
                        ->orWhere('recipient_name', 'like', '%'.$search.'%')
                        ->orWhereHas('item', fn ($items) => $items->withoutGlobalScope(SoftDeletingScope::class)->where('name', 'like', '%'.$search.'%')));
                }
            })
            ->orderByDesc('inventory_item_stock_out_allocation_id');
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): InventoryReturn
    {
        return DB::transaction(function () use ($data, $actor): InventoryReturn {
            $this->guard->lock();
            $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $previous = InventoryReturn::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($previous !== null) {
                if ($previous->created_by_user_id !== $actor->id || $previous->request_hash !== $requestHash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to a different return.']);
                }

                return $previous;
            }
            $lineData = [];
            $party = null;
            foreach ($data['lines'] as $input) {
                if ($data['source_mode'] === 'linked') {
                    $source = InventoryItemStockOutAllocation::query()->whereKey($input['source_allocation_id'])
                        ->with(['batch', 'stockOut.item' => fn ($query) => $query->withTrashed(), 'stockOut.supplyRequestLine'])->lockForUpdate()->firstOrFail();
                    $available = $source->quantity - $this->committedQuantity($source->getKey());
                    if ($input['quantity'] > $available) {
                        throw ValidationException::withMessages(['lines' => "Only {$available} issued units remain returnable from this batch."]);
                    }
                    $issue = $source->stockOut;
                    if (blank($issue->recipient_name)) {
                        throw ValidationException::withMessages(['lines' => 'The source issue has no recorded accountable person. Use the legacy evidence workflow instead.']);
                    }
                    if ($data['return_date'] < $issue->stocked_out_at->toDateString()) {
                        throw ValidationException::withMessages(['return_date' => 'The return date cannot precede any of its source issues.']);
                    }
                    $identity = $issue->recipient_reference_id !== null ? 'employee:'.$issue->recipient_reference_id : 'name:'.mb_strtolower(trim($issue->recipient_name ?? ''));
                    if ($party !== null && $party['identity'] !== $identity) {
                        throw ValidationException::withMessages(['lines' => 'Use a separate return slip for each accountable person.']);
                    }
                    if (filled($issue->department_name) && $issue->department_name !== $data['department_name']) {
                        throw ValidationException::withMessages(['department_name' => 'The returning department must match the source issue.']);
                    }
                    $party = ['identity' => $identity, 'name' => $issue->recipient_name, 'reference_id' => $issue->recipient_reference_id];
                    $item = $issue->item;
                    $lineData[] = [
                        'inventory_item_id' => $item->getKey(), 'source_allocation_id' => $source->getKey(),
                        'source_supply_request_id' => $issue->supplyRequestLine?->supply_request_id,
                        'source_issue_reference' => $issue->ris_no ?? 'ISSUE-'.$issue->getKey(),
                        'stock_number' => $item->stock_number, 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure,
                        'quantity_declared' => $input['quantity'], 'quantity_committed' => $input['quantity'],
                        'unit_cost' => $source->unit_cost, 'expiration_date' => $source->batch->expiration_date?->toDateString(),
                    ];
                } else {
                    $item = InventoryItem::query()->whereKey($input['inventory_item_id'])->firstOrFail();
                    $lineData[] = [
                        'inventory_item_id' => $item->getKey(), 'stock_number' => $item->stock_number, 'item_name' => $item->name,
                        'unit_of_measure' => $item->unit_of_measure, 'quantity_declared' => $input['quantity'], 'quantity_committed' => $input['quantity'],
                        'unit_cost' => $input['unit_cost'], 'valuation_basis' => $input['valuation_basis'],
                        'expiration_date' => $input['expiration_date'] ?? null, 'source_issue_reference' => $data['legacy_issue_reference'],
                    ];
                }
            }
            $reference = isset($data['returned_by_reference_id']) ? HrisReference::query()->whereKey($data['returned_by_reference_id'])->firstOrFail() : null;
            $date = now('Asia/Manila')->toDateString();
            $number = (int) InventoryReturn::query()->whereDate('document_date', $date)->max('daily_number') + 1;
            $returnedByName = $party !== null ? $party['name'] : ($reference !== null ? $reference->name : $data['returned_by_name']);
            $document = InventoryReturn::query()->create([
                'document_no' => sprintf('RMS-%s-%04d', now('Asia/Manila')->format('m-d-y'), $number),
                'document_date' => $date, 'daily_number' => $number, 'return_date' => $data['return_date'],
                'source_mode' => $data['source_mode'], 'returned_by_name' => $returnedByName,
                'returned_by_reference_id' => $party['reference_id'] ?? $reference?->id,
                'department_name' => $data['department_name'], 'reason' => $data['reason'], 'endorsement_reference' => $data['endorsement_reference'],
                'legacy_issue_reference' => $data['source_mode'] === 'legacy' ? $data['legacy_issue_reference'] : null,
                'created_by_user_id' => $actor->id, 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $requestHash,
            ]);
            $document->lines()->createMany($lineData);
            $this->recordAction($document, $actor, 'submit', null, $data['reason'], ['intake' => $data]);

            return $document;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function transition(InventoryReturn $document, array $data, User $actor): void
    {
        DB::transaction(function () use ($document, $data, $actor): void {
            $this->guard->lock();
            $document = InventoryReturn::query()->whereKey($document->id)->with(['lines.item', 'lines.sourceAllocation.batch'])->lockForUpdate()->firstOrFail();
            $from = $document->status;
            $action = $data['action'];
            $allowed = match ($action) {
                'verify' => ['submitted'],
                'approve' => ['verified'],
                'reject' => ['submitted', 'verified'],
                'cancel' => ['submitted', 'verified'],
                'endorse_accounting' => ['approved'],
                default => [],
            };
            if (! in_array($from, $allowed, true)) {
                throw ValidationException::withMessages(['action' => 'This return action is unavailable at its current stage.']);
            }
            $isReturningActor = $this->isReturningActor($document, $actor);
            if (in_array($action, ['verify', 'approve'], true) && ($document->created_by_user_id === $actor->id || $isReturningActor || ($action === 'approve' && $document->verified_by_user_id === $actor->id))) {
                throw ValidationException::withMessages(['action' => 'The recorder, accountable person, verifier, and approver must remain independent.']);
            }
            if ($action === 'verify') {
                $this->verify($document, $data, $actor);
            } elseif ($action === 'approve') {
                $this->approve($document, $actor);
            } elseif ($action === 'endorse_accounting') {
                if ($document->accounting_endorsed_at !== null) {
                    throw ValidationException::withMessages(['action' => 'Accounting endorsement has already been recorded.']);
                }
                $document->update(['accounting_endorsed_at' => now()]);
            } else {
                $document->update(['status' => $action === 'cancel' ? 'cancelled' : 'rejected']);
            }
            $this->recordAction($document, $actor, $action, $from, $data['remarks'], collect($data)->except(['attested', 'action', 'remarks'])->all());
        }, attempts: 3);
    }

    /** @return list<string> */
    public function allowedActions(InventoryReturn $document, User $actor): array
    {
        $independent = $document->created_by_user_id !== $actor->id
            && ! $this->isReturningActor($document, $actor);

        return match ($document->status) {
            'submitted' => [...($independent ? ['verify'] : []), 'reject', 'cancel'],
            'verified' => [...($independent && $document->verified_by_user_id !== $actor->id ? ['approve'] : []), 'reject', 'cancel'],
            'approved' => $document->accounting_endorsed_at === null ? ['endorse_accounting'] : [],
            default => [],
        };
    }

    private function isReturningActor(InventoryReturn $document, User $actor): bool
    {
        if ($document->returned_by_reference_id !== null) {
            return $actor->hris_reference_id !== null && $document->returned_by_reference_id === $actor->hris_reference_id;
        }

        return mb_strtolower(trim($document->returned_by_name)) === mb_strtolower(trim($actor->name));
    }

    /** @param array<string, mixed> $data */
    private function verify(InventoryReturn $document, array $data, User $actor): void
    {
        if (! isset($data['lines']) || ! is_array($data['lines'])) {
            throw ValidationException::withMessages(['lines' => 'Inspect every line before verifying this return.']);
        }
        $input = collect($data['lines']);
        $expected = $document->lines->modelKeys();
        sort($expected);
        if ($input->pluck('line_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $expected) {
            throw ValidationException::withMessages(['lines' => 'Inspect each return line exactly once; lines must belong to this document.']);
        }
        $acceptedAny = false;
        foreach ($document->lines as $line) {
            $inspection = $input->firstWhere('line_id', $line->id);
            $accepted = (int) $inspection['quantity_accepted'];
            if ($accepted > $line->quantity_declared) {
                throw ValidationException::withMessages(['lines' => 'Accepted quantity cannot exceed the declared return quantity.']);
            }
            if ($accepted > 0 && ($inspection['condition'] !== 'good' || blank($inspection['storage_location'] ?? null))) {
                throw ValidationException::withMessages(['lines' => 'Only serviceable quantities with an assigned storage location may be accepted.']);
            }
            if ($accepted > 0 && $line->expiration_date !== null && $line->expiration_date->toDateString() < now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['lines' => 'Expired quantities must be rejected from usable stock.']);
            }
            $line->update([
                'quantity_accepted' => $accepted, 'quantity_committed' => $accepted,
                'condition' => $inspection['condition'], 'storage_location' => $inspection['storage_location'] ?? null,
                'inspection_remarks' => $inspection['inspection_remarks'],
            ]);
            $acceptedAny = $acceptedAny || $accepted > 0;
        }
        $document->update(['status' => $acceptedAny ? 'verified' : 'rejected', 'verified_by_user_id' => $actor->id, 'inspection_reference' => $data['inspection_reference']]);
    }

    private function approve(InventoryReturn $document, User $actor): void
    {
        $this->guard->assertMovementAllowed();
        foreach ($document->lines->sortBy('inventory_item_id') as $line) {
            if (($line->quantity_accepted ?? 0) === 0) {
                continue;
            }
            if ($line->item === null || $line->item->status !== 'active') {
                throw ValidationException::withMessages(['action' => "Restore and activate {$line->item_name} before returning it to usable stock."]);
            }
            if ($line->expiration_date !== null && $line->expiration_date->toDateString() < now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['action' => "The accepted batch of {$line->item_name} has expired since inspection. Reject this slip and prepare a corrected return."]);
            }
            if ($line->source_allocation_id !== null && $this->committedQuantity($line->source_allocation_id) > $line->sourceAllocation->quantity) {
                throw ValidationException::withMessages(['action' => 'Return commitments exceed the original issued quantity.']);
            }
            $batch = $this->stock->receiveReturn($line->item, [
                'quantity' => $line->quantity_accepted, 'unit_cost' => $line->unit_cost,
                'expiration_date' => $line->expiration_date?->toDateString(), 'received_at' => now('Asia/Manila')->toDateString(),
                'reference_no' => $document->document_no, 'source' => 'Return to stock',
                'batch_notes' => $line->inspection_remarks.'; Location: '.$line->storage_location,
            ]);
            $line->posting()->create(['inventory_item_batch_id' => $batch->getKey(), 'quantity' => $line->quantity_accepted, 'unit_cost' => $line->unit_cost]);
        }
        $document->update(['status' => 'approved', 'approved_by_user_id' => $actor->id, 'approved_at' => now()]);
    }

    private function committedQuantity(int $sourceAllocationId): int
    {
        return (int) InventoryReturnLine::query()->where('source_allocation_id', $sourceAllocationId)
            ->whereHas('document', fn ($query) => $query->whereIn('status', self::committedStatuses()))->sum('quantity_committed');
    }

    /** @param array<string, mixed> $metadata */
    private function recordAction(InventoryReturn $document, User $actor, string $action, ?string $from, string $remarks, array $metadata = []): void
    {
        $document->actions()->create(['actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action,
            'from_status' => $from, 'to_status' => $document->status, 'remarks' => $remarks, 'metadata' => $metadata]);
        $this->audit->record('inventory.return.'.$action, $document->document_no.': '.$action, $document, newValues: $metadata, actor: $actor);
    }
}
