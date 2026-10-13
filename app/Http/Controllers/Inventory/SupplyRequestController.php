<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordSupplyReturnSlipRequest;
use App\Http\Requests\Inventory\StoreSupplyRequestRequest;
use App\Http\Requests\Inventory\SupplyRequestIndexRequest;
use App\Http\Requests\Inventory\TransitionSupplyRequestRequest;
use App\Models\DepartmentLiquidation;
use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\DepartmentLiquidationService;
use App\Services\IssuanceLiquidationService;
use App\Services\SupplyRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(private SupplyRequestWorkflow $workflow) {}

    public function index(SupplyRequestIndexRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->validated();
        $items = InventoryItem::query()
            ->where('status', 'active')
            ->with('batches:inventory_item_batch_id,inventory_item_id,quantity_remaining,unit_cost,received_at,expiration_date')
            ->orderBy('name')
            ->get(['inventory_item_id', 'name', 'stock_number', 'unit_of_measure', 'quantity', 'reorder_point'])
            ->each(function (InventoryItem $item): void {
                $remainingQuantity = $item->batches->sum('quantity_remaining');
                $inventoryValue = $item->batches->sum(
                    fn ($batch): float => $batch->quantity_remaining * (float) $batch->unit_cost,
                );

                $item->setAttribute(
                    'weighted_average_unit_cost',
                    $remainingQuantity > 0 ? number_format($inventoryValue / $remainingQuantity, 2, '.', '') : null,
                );
            });
        $requests = SupplyRequest::query()
            ->when(! $user->canManageInventory(), fn ($query) => $query->where('requester_user_id', $user->id))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ris_no', 'like', "%{$search}%")
                        ->orWhere('isf_no', 'like', "%{$search}%")
                        ->orWhereHas('issuanceSlips', fn ($query) => $query->where('slip_no', 'like', "%{$search}%"))
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('office_name', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('lines', fn ($query) => $query->where('item_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['queue'] ?? null) === 'needs_action', fn ($query) => $query->whereNotIn('status', ['released', 'rejected', 'cancelled']))
            ->when(($filters['queue'] ?? null) === 'completed', fn ($query) => $query->whereIn('status', ['released', 'rejected', 'cancelled']))
            ->when(($filters['queue'] ?? null) === 'awaiting_liquidation', fn ($query) => $query->whereHas('issuanceSlips', fn ($query) => $query->where('status', '!=', 'approved')))
            ->with(['lines.item:inventory_item_id,name,quantity,unit_of_measure', 'lines.item.batches', 'actions.actor:id,name', 'issuanceSlips'])
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $liquidations = app(IssuanceLiquidationService::class);
        $departmentLiquidations = app(DepartmentLiquidationService::class);
        $liquidationDocuments = $user->canManageInventory()
            ? DepartmentLiquidation::query()->with('supplyRequest')->latest()->get()->each(fn (DepartmentLiquidation $document) => $document->setAttribute('available_action', $departmentLiquidations->availableAction($document, $user)))
            : [];
        foreach ($requests as $record) {
            foreach ($record->issuanceSlips as $slip) {
                $slip->setAttribute('available_action', $liquidations->availableAction($slip, $user));
            }
        }

        $issuanceContext = $this->issuanceContext($user);
        $nextIsfNumber = (int) DB::table('issuance_request_sequences')
            ->where('date', $issuanceContext['date'])
            ->value('last_number') + 1;

        return Inertia::render('Inventory/Requests/Index', [
            'requests' => $requests,
            'issuanceAllocations' => $user->canManageInventory() ? $departmentLiquidations->allocations(now('Asia/Manila')->toDateString()) : [],
            'liquidationDocuments' => $liquidationDocuments,
            'items' => $items,
            'canManage' => $user->canManageInventory(),
            'requesterOffice' => $user->employee?->officeName(),
            'employees' => $user->canManageInventory()
                ? HrisReference::query()
                    ->where('type', HrisReference::TYPE_EMPLOYEE)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (HrisReference $employee): array => $employee->toSelectionOption())
                : [],
            'responsibilityCenters' => [],
            'issuanceContext' => [
                ...$issuanceContext,
                'isf_no' => $nextIsfNumber <= 999 ? sprintf('%s-%03d', substr($issuanceContext['date'], 5), $nextIsfNumber) : null,
            ],
            'filters' => $filters,
        ]);
    }

    public function store(StoreSupplyRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();
            $isIssuanceForm = $request->boolean('issuance_form');
            $requester = filled($request->validated('requester_reference_id'))
                ? HrisReference::query()->findOrFail($request->validated('requester_reference_id'))
                : $user->employee;
            $record = SupplyRequest::query()->create(['ris_no' => 'TMP-'.Str::uuid(), 'requester_user_id' => $user->id, 'requester_reference_id' => $requester?->getKey(), 'requester_name' => $requester?->name ?? $user->name, 'office_name' => $requester?->officeName() ?? ($isIssuanceForm ? null : ($request->string('office_name')->toString() ?: null)), 'responsibility_center_code' => $request->validated('responsibility_center_code') ?: null, 'purpose' => $request->validated('purpose'), 'date_needed' => $isIssuanceForm ? null : $request->date('date_needed'), 'status' => 'submitted', 'submitted_at' => now()]);
            $record->update(['ris_no' => sprintf('RIS-%s-%06d', now()->format('Y'), $record->id)]);
            if ($isIssuanceForm) {
                $context = $this->issuanceContext($user);
                $date = $context['date'];
                DB::table('issuance_request_sequences')->insertOrIgnore(['date' => $date, 'last_number' => 0]);
                $sequence = DB::table('issuance_request_sequences')->where('date', $date)->lockForUpdate()->first();
                $number = $sequence->last_number + 1;
                if ($number > 999) {
                    throw ValidationException::withMessages(['issuance_form' => 'The daily ISF number limit has been reached.']);
                }
                DB::table('issuance_request_sequences')->where('date', $date)->update(['last_number' => $number]);
                $record->update([
                    'isf_no' => sprintf('%s-%03d', substr($date, 5), $number),
                    'isf_date' => $date,
                    'isf_sequence' => $number,
                    'office_name' => $context['department'],
                    'issuance_details' => [...$context, 'date' => $date],
                ]);
            }
            foreach ($request->validated('lines') as $line) {
                $item = filled($line['inventory_item_id'] ?? null) ? InventoryItem::findOrFail($line['inventory_item_id']) : null;
                $record->lines()->create(['inventory_item_id' => $item?->getKey(), 'is_new_item' => $isIssuanceForm ? $item === null : (bool) $line['is_new_item'], 'item_name' => $item?->name ?? $line['item_name'], 'specifications' => $line['specifications'] ?? null, 'unit_of_measure' => $item?->unit_of_measure ?? $line['unit_of_measure'], 'quantity_requested' => $line['quantity'], 'estimated_unit_cost' => $line['estimated_unit_cost'] ?? null, 'justification' => $isIssuanceForm && $item === null ? $request->validated('purpose') : ($line['justification'] ?? null)]);
            }

            $record->actions()->create(['actor_user_id' => $user->id, 'action' => 'submit', 'to_status' => 'submitted', 'attestation' => 'I certify this request is necessary for official use.']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supply request submitted.']);

        return back();
    }

    public function recordReturnSlip(RecordSupplyReturnSlipRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplyRequest): void {
            $record = SupplyRequest::query()->whereKey($supplyRequest->id)->lockForUpdate()->firstOrFail();
            $firstIssue = DB::table('inventory_item_stock_outs')
                ->whereIn('supply_request_line_id', $record->lines()->pluck('id'))->min('stocked_out_at');
            if ($firstIssue === null || $request->validated('return_slip_date') < substr($firstIssue, 0, 10)) {
                throw ValidationException::withMessages(['return_slip_date' => 'The return slip must be dated on or after the first release.']);
            }
            $oldReference = ['return_slip_no' => $record->return_slip_no, 'return_slip_date' => $record->return_slip_date?->toDateString()];
            $record->update($request->safe()->only(['return_slip_no', 'return_slip_date']));
            $record->actions()->create([
                'actor_user_id' => $request->user()->id, 'action' => 'record_return_slip',
                'from_status' => $record->status, 'to_status' => $record->status,
                'remarks' => $request->validated('remarks'), 'attestation' => 'I confirm this return slip reference is accurate.',
                'metadata' => ['previous' => $oldReference, 'return_slip_no' => $record->return_slip_no, 'return_slip_date' => $request->validated('return_slip_date')],
            ]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Return slip reference saved.']);

        return back();
    }

    /** @return array{department: string|null, date: string, requested_by: string, checked_by: string|null, approved_by: string|null} */
    private function issuanceContext(User $user): array
    {
        $employee = $user->employee;

        return [
            'department' => data_get($employee?->metadata, 'position.department.name'),
            'date' => now('Asia/Manila')->toDateString(),
            'requested_by' => $employee?->name ?? $user->name,
            'checked_by' => $this->requestManager($employee, 'division'),
            'approved_by' => $this->requestManager($employee, 'department'),
        ];
    }

    private function requestManager(?HrisReference $employee, string $organization): ?string
    {
        $organizationId = data_get($employee?->metadata, "position.{$organization}.id");
        if ($organizationId === null) {
            return null;
        }

        $managers = HrisReference::query()
            ->where('type', HrisReference::TYPE_EMPLOYEE)
            ->where('is_active', true)
            ->where("metadata->position->{$organization}->id", $organizationId)
            ->where('metadata->position->name', ucfirst($organization).' Manager')
            ->limit(2)
            ->get(['name']);

        return $managers->count() === 1 ? $managers->first()->name : null;
    }

    public function transition(TransitionSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->workflow->transition($supplyRequest, $request->user(), $request->string('action')->toString(), $request->string('remarks')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request workflow updated.']);

        return back();
    }
}
