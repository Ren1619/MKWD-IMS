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
use App\Models\InventoryReturn;
use App\Models\InventoryReturnLine;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\DepartmentLiquidationService;
use App\Services\InventoryReturnService;
use App\Services\IssuanceLiquidationService;
use App\Services\SupplyRequestWorkflow;
use App\Services\TemporaryHeadApprovalAccess;
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
            ->when(! $user->canManageInventory(), fn ($query) => $query->where(function ($query) use ($user): void {
                $query->where('requester_user_id', $user->id)
                    ->when($user->hris_reference_id !== null, fn ($query) => $query->orWhere('requester_reference_id', $user->hris_reference_id))
                    ->orWhereHas('actions', fn ($query) => $query->where('actor_user_id', $user->id))
                    ->orWhere(function ($query) use ($user): void {
                        $access = app(TemporaryHeadApprovalAccess::class);
                        $query->whereIn('requester_reference_id', $access->employees($user)->select('id'))
                            ->where(function ($query) use ($user, $access): void {
                                $query->where('status', $access->pendingStatus($user));
                                if ($access->pendingStatus($user) === 'division_approved') {
                                    $query->orWhere(function ($query) use ($user, $access): void {
                                        $query->where('status', 'submitted')->whereIn('requester_reference_id', $access->employees($user)
                                            ->whereNull('metadata->position->division')->select('id'));
                                    });
                                }
                            });
                    });
            }))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ris_no', 'like', "%{$search}%")
                        ->orWhere('irf_no', 'like', "%{$search}%")
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
            ->with(['requestedFor', 'lines.item:inventory_item_id,name,quantity,unit_of_measure', 'lines.item.batches', 'actions.actor:id,name', 'issuanceSlips'])
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $activeReturnRequestIds = InventoryReturnLine::query()
            ->whereIn('source_supply_request_id', $requests->getCollection()->modelKeys())
            ->whereHas('document', fn ($query) => $query->whereIn('status', InventoryReturnService::committedStatuses()))
            ->distinct()
            ->pluck('source_supply_request_id')
            ->all();
        foreach ($requests as $record) {
            $record->setAttribute('has_active_return', in_array($record->getKey(), $activeReturnRequestIds, true));
            $record->setAttribute('can_approve_as_head', app(TemporaryHeadApprovalAccess::class)->canApprove($user, $record));
            $record->setAttribute('approval_stage', app(TemporaryHeadApprovalAccess::class)->requiredStage($record));
            $record->unsetRelation('requestedFor');
        }
        $liquidations = app(IssuanceLiquidationService::class);
        $departmentLiquidations = app(DepartmentLiquidationService::class);
        $liquidationDocuments = DepartmentLiquidation::query()->with(['supplyRequest', 'issuanceSlip.supplyRequest'])
            ->when(! $user->canManageInventory(), fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('supply_request_id', $requests->getCollection()->modelKeys())
                ->orWhereHas('issuanceSlip', fn ($query) => $query->whereIn('supply_request_id', $requests->getCollection()->modelKeys()))))
            ->latest()->get()->each(fn (DepartmentLiquidation $document) => $document->setAttribute('available_action', $user->canManageInventory() ? $departmentLiquidations->availableAction($document, $user) : null));
        $returns = InventoryReturn::query()
            ->whereHas('lines', fn ($query) => $query->whereIn('source_supply_request_id', $requests->getCollection()->modelKeys()))
            ->with(['lines' => fn ($query) => $query->whereIn('source_supply_request_id', $requests->getCollection()->modelKeys())])
            ->get();
        foreach ($requests as $record) {
            $record->setAttribute('return_documents', $returns->filter(fn ($document) => $document->lines->contains('source_supply_request_id', $record->id))
                ->map(fn ($document): array => ['document_no' => $document->document_no, 'status' => $document->status,
                    'return_date' => $document->return_date?->toDateString(),
                    'lines' => $document->lines->where('source_supply_request_id', $record->id)->values()->map(fn ($line): array => ['item_description' => $line->item_name, 'quantity' => $line->quantity_declared, 'unit_of_measure' => $line->unit_of_measure])])->values());
        }
        foreach ($requests as $record) {
            foreach ($record->issuanceSlips as $slip) {
                $slip->setAttribute('available_action', $liquidations->availableAction($slip, $user));
            }
        }

        $issuanceContext = $this->issuanceContext($user);
        $nextIrfNumber = (int) DB::table('issuance_request_sequences')
            ->where('date', $issuanceContext['date'])
            ->value('last_number') + 1;

        return Inertia::render('Inventory/Requests/Index', [
            'requests' => $requests,
            'issuanceAllocations' => $user->canManageInventory() ? $departmentLiquidations->allocations(now('Asia/Manila')->toDateString()) : [],
            'liquidationDocuments' => $liquidationDocuments,
            'items' => $items,
            'canManage' => $user->canManageInventory(),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'requesterOffice' => $user->employee?->officeName(),
            'employees' => $user->isSuperAdmin() ? HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (HrisReference $employee): array => [...$employee->toSelectionOption(), 'position_name' => data_get($employee->metadata, 'position.name')]) : [],
            'responsibilityCenters' => [],
            'issuanceContext' => [
                ...$issuanceContext,
                'irf_no' => $nextIrfNumber <= 999 ? sprintf('%s-%03d', substr($issuanceContext['date'], 5), $nextIrfNumber) : null,
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
                    throw ValidationException::withMessages(['issuance_form' => 'The daily IRF number limit has been reached.']);
                }
                DB::table('issuance_request_sequences')->where('date', $date)->update(['last_number' => $number]);
                $record->update([
                    'irf_no' => sprintf('%s-%03d', substr($date, 5), $number),
                    'irf_date' => $date,
                    'irf_sequence' => $number,
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
