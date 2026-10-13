<?php

namespace App\Services;

use App\Models\DepartmentLiquidation;
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
        $requests = SupplyRequest::query()->whereNotNull('isf_no')
            ->whereHas('lines', fn ($query) => $query->where('quantity_released', '>', 0))
            ->with(['lines', 'issuanceSlips'])->latest()->get();
        $lineIds = $requests->flatMap(fn (SupplyRequest $request) => $request->lines->modelKeys());
        $issues = DB::table('inventory_item_stock_outs')->whereIn('supply_request_line_id', $lineIds)
            ->whereDate('stocked_out_at', '<=', $date)->orderBy('inventory_item_stock_out_id')->get();
        $previous = DepartmentLiquidation::query()->whereIn('supply_request_id', $requests->modelKeys())
            ->where('status', 'approved')->latest('id')->get()->unique('supply_request_id')->keyBy('supply_request_id');
        $result = [];
        foreach ($requests as $request) {
            $allocation = $this->buildAllocation($request, $date, $issues->whereIn('supply_request_line_id', $request->lines->modelKeys()), $previous->get($request->id));
            if ($allocation['lines'] !== []) {
                $result[] = $allocation;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function allocation(SupplyRequest $request, string $date): array
    {
        $request->loadMissing(['lines', 'issuanceSlips']);
        $issues = DB::table('inventory_item_stock_outs')->whereIn('supply_request_line_id', $request->lines->modelKeys())
            ->whereDate('stocked_out_at', '<=', $date)->orderBy('inventory_item_stock_out_id')->get();
        $previous = DepartmentLiquidation::query()->where('supply_request_id', $request->id)->where('status', 'approved')->latest('id')->first();

        return $this->buildAllocation($request, $date, $issues, $previous);
    }

    /**
     * @param  Collection<int, \stdClass>  $issues
     * @return array<string, mixed>
     */
    private function buildAllocation(SupplyRequest $request, string $date, Collection $issues, ?DepartmentLiquidation $previous): array
    {
        $previousLines = collect($previous->lines ?? [])->keyBy('supply_request_line_id');
        $legacyUsage = [];
        if ($previous === null) {
            foreach ($request->issuanceSlips->where('status', 'approved')->filter(fn ($slip): bool => $slip->issued_at->toDateString() <= $date) as $slip) {
                foreach ($slip->lines as $line) {
                    $key = $line['supply_request_line_id'];
                    $legacyUsage[$key] = ($legacyUsage[$key] ?? 0) + ($line['quantity_utilized'] ?? 0);
                }
            }
        }
        $lines = [];
        foreach ($request->lines as $line) {
            $lineIssues = $issues->where('supply_request_line_id', $line->id);
            $quantity = (int) $lineIssues->sum('quantity');
            if ($quantity === 0) {
                continue;
            }
            $lines[] = ['supply_request_line_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id,
                'item_description' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure,
                'quantity_issued' => $quantity, 'previously_utilized' => $previousLines->get($line->id)['quantity_utilized'] ?? ($legacyUsage[$line->id] ?? 0),
                'source_stock_out_ids' => $lineIssues->pluck('inventory_item_stock_out_id')->map(fn ($id): int => (int) $id)->values()->all()];
        }

        return ['supply_request_id' => $request->id, 'isf_no' => $request->isf_no,
            'department' => data_get($request->issuance_details, 'department') ?? $request->office_name,
            'charge_code' => $request->responsibility_center_code, 'requested_by_name' => $request->requester_name,
            'return_slip_no' => $request->return_slip_no, 'return_slip_date' => $request->return_slip_date?->toDateString(), 'lines' => $lines];
    }

    /** @param array<string, mixed> $data */
    public function submit(User $actor, array $data): DepartmentLiquidation
    {
        return DB::transaction(function () use ($actor, $data): DepartmentLiquidation {
            $request = SupplyRequest::query()->whereKey($data['supply_request_id'])->lockForUpdate()->with('lines')->firstOrFail();
            if ($request->isf_no === null) {
                throw ValidationException::withMessages(['supply_request_id' => 'Select an Issuance Request Form.']);
            }
            if (DepartmentLiquidation::query()->where('active_supply_request_id', $request->id)->exists()) {
                throw ValidationException::withMessages(['supply_request_id' => 'This ISF already has a liquidation awaiting review.']);
            }
            $previous = DepartmentLiquidation::query()->where('supply_request_id', $request->id)->where('status', 'approved')->latest('id')->first();
            if ($previous !== null && $data['liquidation_date'] < $previous->liquidation_date->toDateString()) {
                throw ValidationException::withMessages(['liquidation_date' => 'The date cannot precede the last approved liquidation for this ISF.']);
            }
            $allocation = $this->allocation($request, $data['liquidation_date']);
            $inputLines = $data['lines'] ?? [];
            if (! is_array($inputLines)) {
                throw ValidationException::withMessages(['lines' => 'Provide the material utilization for this ISF.']);
            }
            $submitted = collect($inputLines)->keyBy('supply_request_line_id');
            if ($allocation['lines'] === [] || count($allocation['lines']) !== $submitted->count()) {
                throw ValidationException::withMessages(['lines' => 'Include every issued line from the selected ISF as of the reporting date.']);
            }
            $lines = [];
            foreach ($allocation['lines'] as $line) {
                $entry = $submitted->get($line['supply_request_line_id']);
                $used = $entry['quantity_utilized'] ?? null;
                if ($used === null || $used < $line['previously_utilized'] || $used > $line['quantity_issued']) {
                    throw ValidationException::withMessages(['lines' => 'Utilization cannot exceed the issued quantity or fall below previously approved utilization for this ISF.']);
                }
                $lines[] = [...$line, 'quantity_utilized' => (int) $used, 'balance' => $line['quantity_issued'] - (int) $used, 'usage_details' => $entry['usage_details'] ?? null];
            }
            $returnSlipNumber = $data['return_slip_no'] ?? $request->return_slip_no;
            $returnSlipDate = $data['return_slip_date'] ?? $request->return_slip_date?->toDateString();
            if (filled($returnSlipNumber)) {
                $firstIssue = DB::table('inventory_item_stock_outs')->whereIn('supply_request_line_id', $request->lines->modelKeys())->min('stocked_out_at');
                if (($returnSlipDate ?? '') < substr($firstIssue, 0, 10) || $returnSlipDate > $data['liquidation_date']) {
                    throw ValidationException::withMessages(['return_slip_date' => 'The return slip date must fall between issuance and liquidation.']);
                }
            }
            $numberDate = now('Asia/Manila')->toDateString();
            DB::table('department_liquidation_sequences')->insertOrIgnore(['date' => $numberDate, 'last_number' => 0]);
            $sequence = DB::table('department_liquidation_sequences')->where('date', $numberDate)->lockForUpdate()->first();
            $number = $sequence->last_number + 1;
            if ($number > 999) {
                throw ValidationException::withMessages(['supply_request_id' => 'The daily liquidation number limit has been reached.']);
            }
            DB::table('department_liquidation_sequences')->where('date', $numberDate)->update(['last_number' => $number]);

            return DepartmentLiquidation::query()->create([
                'document_no' => sprintf('%s-%03d', substr($numberDate, 5), $number), 'liquidation_date' => $data['liquidation_date'],
                'supply_request_id' => $request->id, 'active_supply_request_id' => $request->id, 'isf_no' => $request->isf_no,
                'department' => $allocation['department'] ?? '', 'charge_code' => $allocation['charge_code'],
                'return_slip_no' => $returnSlipNumber, 'return_slip_date' => $returnSlipDate,
                'requested_by_user_id' => $actor->id, 'requested_by_name' => $request->requester_name,
                'lines' => $lines, 'history' => [$this->historyEntry('submit', $actor, $data['remarks'] ?? null)],
            ]);
        });
    }

    public function availableAction(DepartmentLiquidation $document, User $actor): ?string
    {
        if ($document->supply_request_id === null || ! $actor->canManageInventory() || collect($document->history)->contains('actor_user_id', $actor->id)) {
            return null;
        }
        $request = $document->supplyRequest;
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
            }
            $document->save();
        });
    }

    /** @return array{action: string, actor_user_id: int, actor_name: string, at: string, remarks: string|null, attestation: string} */
    private function historyEntry(string $action, User $actor, ?string $remarks): array
    {
        return ['action' => $action, 'actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'at' => now()->toIso8601String(), 'remarks' => $remarks, 'attestation' => 'I confirm this ISF liquidation is accurate.'];
    }
}
