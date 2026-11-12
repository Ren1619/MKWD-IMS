<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\PrintIssuanceSlipRequest;
use App\Models\HrisReference;
use App\Models\InventoryItemStockOut;
use App\Models\IssuanceSlip;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\TemporaryHeadApprovalAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupplyRequestDocumentController extends Controller
{
    public function request(Request $request, SupplyRequest $supplyRequest): View
    {
        $this->authorizeDocument($request->user(), $supplyRequest);

        return view('inventory.issuance-request', ['record' => $supplyRequest->load('lines')]);
    }

    public function slip(PrintIssuanceSlipRequest $request, IssuanceSlip $slip): View
    {
        $this->authorizeDocument($request->user(), $slip->supplyRequest);
        $stockOuts = InventoryItemStockOut::query()->where('issuance_slip_id', $slip->id)
            ->with(['item', 'allocations'])->get()->groupBy('supply_request_line_id');
        $lines = collect($slip->lines)->map(function (array $line) use ($stockOuts): array {
            $issues = $stockOuts->get($line['supply_request_line_id'], collect());
            $allocations = $issues->flatMap->allocations;
            $amountInCents = $allocations->sum(fn ($allocation): int => $allocation->quantity * (int) round((float) $allocation->unit_cost * 100));

            return [...$line, 'item_code' => $issues->first()?->item?->stock_number,
                'unit_cost' => $allocations->isEmpty() ? null : $amountInCents / 100 / $line['quantity_issued'],
                'amount' => $allocations->isEmpty() ? null : $amountInCents / 100, 'charge_account' => null];
        });
        $signatories = collect(['prepared_by', 'checked_by', 'approved_by'])->mapWithKeys(function (string $role) use ($request): array {
            $employee = HrisReference::query()->find($request->validated($role));

            return [$role => ['name' => $employee?->name, 'position' => data_get($employee?->metadata, 'position.name')]];
        });

        return view('inventory.issuance-slip', compact('slip', 'lines', 'signatories'));
    }

    private function authorizeDocument(User $user, SupplyRequest $record): void
    {
        abort_unless($user->canManageInventory()
            || $record->requester_user_id === $user->id
            || ($user->hris_reference_id !== null && $record->requester_reference_id === $user->hris_reference_id)
            || $record->actions()->where('actor_user_id', $user->id)->exists()
            || app(TemporaryHeadApprovalAccess::class)->canApprove($user, $record), 403);
    }
}
