<?php

namespace App\Services;

use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssuanceLiquidationService
{
    public function availableAction(IssuanceSlip $slip, User $actor): ?string
    {
        if (! $actor->canManageInventory()) {
            return null;
        }
        $action = ['pending' => 'submit', 'submitted' => 'check', 'checked' => 'approve'][$slip->status] ?? null;
        if ($action !== null && $action !== 'submit') {
            $reference = $slip->snapshot['requester_reference_id'];
            $isRequester = $reference !== null ? $actor->hris_reference_id === $reference : $actor->id === $slip->snapshot['requester_user_id'];
            if ($isRequester || collect($slip->history)->contains('actor_user_id', $actor->id)) {
                return null;
            }
        }

        return $action;
    }

    /** @param list<array{supply_request_line_id: int, item_description: string, unit_of_measure: string, quantity_issued: int}> $lines */
    public function issue(SupplyRequest $request, User $actor, array $lines): IssuanceSlip
    {
        $date = now('Asia/Manila')->toDateString();
        DB::table('issuance_slip_sequences')->insertOrIgnore(['date' => $date, 'last_number' => 0]);
        $sequence = DB::table('issuance_slip_sequences')->where('date', $date)->lockForUpdate()->first();
        $number = $sequence->last_number + 1;
        if ($number > 999) {
            throw ValidationException::withMessages(['action' => 'The daily issuance slip number limit has been reached.']);
        }
        DB::table('issuance_slip_sequences')->where('date', $date)->update(['last_number' => $number]);

        return $request->issuanceSlips()->create([
            'slip_no' => sprintf('%s-%03d', substr($date, 5), $number),
            'issued_at' => $date,
            'issued_by_user_id' => $actor->id,
            'snapshot' => ['department' => $request->office_name, 'charge_code' => $request->responsibility_center_code, 'requested_by' => $request->requester_name, 'isf_no' => $request->isf_no, 'requester_user_id' => $request->requester_user_id, 'requester_reference_id' => $request->requester_reference_id],
            'lines' => $lines,
            'history' => [],
        ]);
    }

    /** @param array<string, mixed> $data */
    public function transition(IssuanceSlip $slip, User $actor, array $data): void
    {
        DB::transaction(function () use ($slip, $actor, $data): void {
            $slip = IssuanceSlip::query()->whereKey($slip->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'] ?? null;
            $expected = ['submit' => 'pending', 'check' => 'submitted', 'approve' => 'checked'];
            if (! is_string($action) || ! isset($expected[$action]) || $slip->status !== $expected[$action]) {
                throw ValidationException::withMessages(['action' => 'This liquidation action is not available in the current state.']);
            }
            $history = $slip->history;
            if ($this->availableAction($slip, $actor) !== $action) {
                throw ValidationException::withMessages(['action' => 'The requester, recorder, checker, and approver must be separate people with inventory management access.']);
            }
            if ($action === 'submit') {
                $submittedLines = $data['lines'] ?? [];
                if (! is_array($submittedLines)) {
                    throw ValidationException::withMessages(['lines' => 'Provide the utilized quantities for this slip.']);
                }
                $input = collect($submittedLines)->keyBy('supply_request_line_id');
                if ($input->count() !== count($slip->lines)) {
                    throw ValidationException::withMessages(['lines' => 'Account for every issued line exactly once.']);
                }
                $lines = [];
                foreach ($slip->lines as $line) {
                    $used = $input->get($line['supply_request_line_id'])['quantity_utilized'] ?? null;
                    if ($used === null || $used < 0 || $used > $line['quantity_issued']) {
                        throw ValidationException::withMessages(['lines' => 'Utilized quantities must be between zero and the issued quantity for each slip line.']);
                    }
                    $lines[] = [...$line, 'quantity_utilized' => (int) $used, 'balance' => $line['quantity_issued'] - (int) $used];
                }
                if ($data['liquidation_date'] < $slip->issued_at->toDateString()) {
                    throw ValidationException::withMessages(['liquidation_date' => 'Liquidation cannot precede issuance.']);
                }
                $slip->lines = $lines;
                $slip->setAttribute('liquidation_date', $data['liquidation_date']);
            }
            $history[] = ['action' => $action, 'actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'at' => now()->toIso8601String(), 'remarks' => $data['remarks'] ?? null, 'attestation' => 'I confirm this liquidation is accurate and authorized.'];
            $slip->history = $history;
            $slip->status = match ($action) {
                'submit' => 'submitted',
                'check' => 'checked',
                'approve' => 'approved',
            };
            $slip->save();
        });
    }
}
