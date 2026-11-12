<?php

namespace App\Services;

use App\Models\DepartmentLiquidation;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentLiquidationService
{
    /** @return list<array<string, mixed>> */
    public function allocations(string $date): array
    {
        $slips = IssuanceSlip::query()->whereDate('issued_at', '<=', $date)
            ->with('supplyRequest.lines')->latest('id')->get();
        $issues = DB::table('inventory_item_stock_outs')->whereIn('issuance_slip_id', $slips->modelKeys())
            ->whereDate('stocked_out_at', '<=', $date)->orderBy('inventory_item_stock_out_id')->get()->groupBy('issuance_slip_id');
        $previous = DepartmentLiquidation::query()->whereIn('issuance_slip_id', $slips->modelKeys())
            ->where('status', 'approved')->latest('id')->get()->unique('issuance_slip_id')->keyBy('issuance_slip_id');
        $legacy = DepartmentLiquidation::query()->whereNull('issuance_slip_id')
            ->whereIn('supply_request_id', $slips->pluck('supply_request_id'))->get()->groupBy('supply_request_id');
        $result = [];
        foreach ($slips as $slip) {
            $allocation = $this->buildAllocation($slip, $issues->get($slip->id, collect()), $previous->get($slip->id), $legacy->get($slip->supply_request_id, collect()));
            if ($allocation['lines'] !== [] && $allocation['blocked_reason'] === null) {
                $result[] = $allocation;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function allocation(IssuanceSlip $slip, string $date): array
    {
        $slip->loadMissing('supplyRequest.lines');
        $issues = DB::table('inventory_item_stock_outs')->where('issuance_slip_id', $slip->id)
            ->whereDate('stocked_out_at', '<=', $date)->orderBy('inventory_item_stock_out_id')->get();
        $previous = DepartmentLiquidation::query()->where('issuance_slip_id', $slip->id)->where('status', 'approved')->latest('id')->first();
        $legacy = DepartmentLiquidation::query()->whereNull('issuance_slip_id')->where('supply_request_id', $slip->supply_request_id)->get();

        return $this->buildAllocation($slip, $slip->issued_at->toDateString() <= $date ? $issues : collect(), $previous, $legacy);
    }

    /**
     * @param  Collection<int, \stdClass>  $issues
     * @param  Collection<int, DepartmentLiquidation>  $legacy
     * @return array<string, mixed>
     */
    private function buildAllocation(IssuanceSlip $slip, Collection $issues, ?DepartmentLiquidation $previous, Collection $legacy): array
    {
        $request = $slip->supplyRequest;
        $previousLines = collect($previous->lines ?? [])->keyBy('supply_request_line_id');
        $requestLines = $request->lines->keyBy('id');
        $lines = [];
        foreach ($slip->lines as $line) {
            $lineId = $line['supply_request_line_id'];
            $lineIssues = $issues->where('supply_request_line_id', $lineId);
            $quantity = (int) $lineIssues->sum('quantity');
            if ($quantity === 0) {
                continue;
            }
            $lines[] = ['supply_request_line_id' => $lineId, 'inventory_item_id' => $requestLines->get($lineId)?->inventory_item_id,
                'item_description' => $line['item_description'], 'unit_of_measure' => $line['unit_of_measure'],
                'quantity_issued' => $quantity, 'previously_utilized' => $previousLines->get($lineId)['quantity_utilized'] ?? ($slip->status === 'approved' ? ($line['quantity_utilized'] ?? 0) : 0),
                'source_stock_out_ids' => $lineIssues->pluck('inventory_item_stock_out_id')->map(fn ($id): int => (int) $id)->values()->all()];
        }
        $blocked = $legacy->contains(function (DepartmentLiquidation $document) use ($slip, $issues): bool {
            if (in_array($document->status, ['submitted', 'checked'], true)) {
                return true;
            }
            if ($document->status !== 'approved') {
                return false;
            }
            $coveredIds = collect($document->lines)->flatMap(fn (array $line): array => $line['source_stock_out_ids'] ?? []);

            return $coveredIds->isEmpty()
                ? $slip->issued_at->lte($document->liquidation_date)
                : $coveredIds->intersect($issues->pluck('inventory_item_stock_out_id'))->isNotEmpty();
        });

        return ['issuance_slip_id' => $slip->id, 'is_no' => $slip->slip_no, 'issued_at' => $slip->issued_at->toDateString(),
            'supply_request_id' => $slip->supply_request_id,
            'department' => $slip->snapshot['department'] ?? $request->office_name,
            'charge_code' => $slip->snapshot['charge_code'] ?? $request->responsibility_center_code,
            'requested_by_name' => $slip->snapshot['requested_by'] ?? $request->requester_name,
            'return_slip_no' => $request->return_slip_no, 'return_slip_date' => $request->return_slip_date?->toDateString(),
            'blocked_reason' => $blocked ? 'This issuance slip is covered by a historical request liquidation. Retain and review that existing record.' : null,
            'lines' => $lines];
    }

    /** @param array<string, mixed> $data */
    public function submit(User $actor, array $data): DepartmentLiquidation
    {
        return DB::transaction(function () use ($actor, $data): DepartmentLiquidation {
            $slip = IssuanceSlip::query()->findOrFail($data['issuance_slip_id']);
            $request = SupplyRequest::query()->whereKey($slip->supply_request_id)->lockForUpdate()->with('lines')->firstOrFail();
            $slip = IssuanceSlip::query()->whereKey($slip->id)->lockForUpdate()->firstOrFail();
            $slip->setRelation('supplyRequest', $request);
            if (DepartmentLiquidation::query()->where('active_issuance_slip_id', $slip->id)->exists()) {
                throw ValidationException::withMessages(['issuance_slip_id' => 'This issuance slip already has a liquidation awaiting review.']);
            }
            $previous = DepartmentLiquidation::query()->where('issuance_slip_id', $slip->id)->where('status', 'approved')->latest('id')->first();
            if ($previous !== null && $data['liquidation_date'] < $previous->liquidation_date->toDateString()) {
                throw ValidationException::withMessages(['liquidation_date' => 'The date cannot precede the last approved liquidation for this issuance slip.']);
            }
            $allocation = $this->allocation($slip, $data['liquidation_date']);
            if ($allocation['blocked_reason'] !== null) {
                throw ValidationException::withMessages(['issuance_slip_id' => $allocation['blocked_reason']]);
            }
            $inputLines = $data['lines'] ?? [];
            if (! is_array($inputLines)) {
                throw ValidationException::withMessages(['lines' => 'Provide the material utilization for this issuance slip.']);
            }
            $submitted = collect($inputLines)->keyBy('supply_request_line_id');
            if ($allocation['lines'] === [] || count($allocation['lines']) !== $submitted->count() || count($inputLines) !== $submitted->count()) {
                throw ValidationException::withMessages(['lines' => 'Include every issued line from the selected issuance slip as of the reporting date.']);
            }
            $lines = [];
            foreach ($allocation['lines'] as $line) {
                $entry = $submitted->get($line['supply_request_line_id']);
                $used = $entry['quantity_utilized'] ?? null;
                if ($used === null || $used < $line['previously_utilized'] || $used > $line['quantity_issued']) {
                    throw ValidationException::withMessages(['lines' => 'Utilization cannot exceed the issued quantity or fall below previously approved utilization for this issuance slip.']);
                }
                $lines[] = [...$line, 'quantity_utilized' => (int) $used, 'balance' => $line['quantity_issued'] - (int) $used, 'usage_details' => $entry['usage_details'] ?? null];
            }
            $returnSlipNumber = $data['return_slip_no'] ?? null;
            $returnSlipDate = $data['return_slip_date'] ?? null;
            if (blank($returnSlipNumber) || blank($returnSlipDate)) {
                throw ValidationException::withMessages(['return_slip_no' => 'Provide a return slip number and date.']);
            }
            if ($returnSlipDate < $slip->issued_at->toDateString() || $returnSlipDate > $data['liquidation_date']) {
                throw ValidationException::withMessages(['return_slip_date' => 'The return slip date must fall between this issuance slip and liquidation.']);
            }
            $numberDate = now('Asia/Manila')->toDateString();
            DB::table('department_liquidation_sequences')->insertOrIgnore(['date' => $numberDate, 'last_number' => 0]);
            $sequence = DB::table('department_liquidation_sequences')->where('date', $numberDate)->lockForUpdate()->first();
            $number = $sequence->last_number + 1;
            if ($number > 999) {
                throw ValidationException::withMessages(['issuance_slip_id' => 'The daily liquidation number limit has been reached.']);
            }
            DB::table('department_liquidation_sequences')->where('date', $numberDate)->update(['last_number' => $number]);

            return DepartmentLiquidation::query()->create([
                'document_no' => sprintf('%s-%03d', substr($numberDate, 5), $number), 'liquidation_date' => $data['liquidation_date'],
                'issuance_slip_id' => $slip->id, 'active_issuance_slip_id' => $slip->id, 'is_no' => $slip->slip_no,
                'department' => $allocation['department'] ?? '', 'charge_code' => $allocation['charge_code'],
                'return_slip_no' => $returnSlipNumber, 'return_slip_date' => $returnSlipDate,
                'requested_by_user_id' => $actor->id, 'requested_by_name' => $allocation['requested_by_name'],
                'lines' => $lines, 'history' => [$this->historyEntry('submit', $actor, $data['remarks'] ?? null)],
            ]);
        });
    }

    public function availableAction(DepartmentLiquidation $document, User $actor): ?string
    {
        if (! $actor->canManageInventory() || collect($document->history)->contains('actor_user_id', $actor->id)) {
            return null;
        }
        $request = $document->issuanceSlip?->supplyRequest ?? $document->supplyRequest;
        if ($request === null) {
            return null;
        }
        $isRequester = $request->requester_reference_id !== null ? $actor->hris_reference_id === $request->requester_reference_id : $actor->id === $request->requester_user_id;
        if ($isRequester) {
            return null;
        }

        return ['submitted' => 'check', 'checked' => 'approve'][$document->status] ?? null;
    }

    public function transition(DepartmentLiquidation $document, User $actor, string $action, ?string $remarks): void
    {
        DB::transaction(function () use ($document, $actor, $action, $remarks): void {
            $document = DepartmentLiquidation::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($this->availableAction($document, $actor) !== $action) {
                throw ValidationException::withMessages(['action' => 'This action requires the correct workflow state and a separate reviewer.']);
            }
            $document->history = [...$document->history, $this->historyEntry($action, $actor, $remarks)];
            $document->status = $action === 'check' ? 'checked' : 'approved';
            if ($document->status === 'approved') {
                $document->setAttribute('active_supply_request_id', null);
                $document->setAttribute('active_issuance_slip_id', null);
            }
            $document->save();
        });
    }

    /** @return array{action: string, actor_user_id: int, actor_name: string, at: string, remarks: string|null, attestation: string} */
    private function historyEntry(string $action, User $actor, ?string $remarks): array
    {
        return ['action' => $action, 'actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'at' => now()->toIso8601String(), 'remarks' => $remarks, 'attestation' => 'I confirm this issuance liquidation is accurate.'];
    }
}
