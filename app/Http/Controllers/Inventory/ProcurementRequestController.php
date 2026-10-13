<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ManageProcurementRecordRequest;
use App\Http\Requests\Inventory\ProcurementRequestIndexRequest;
use App\Http\Requests\Inventory\StoreProcurementAttachmentRequest;
use App\Http\Requests\Inventory\StoreProcurementRequestRequest;
use App\Http\Requests\Inventory\TransitionProcurementRequestRequest;
use App\Models\HrisReference;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Services\PreprocurementWorkflow;
use App\Services\ProcurementRequestWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementRequestController extends Controller
{
    public function __construct(private ProcurementRequestWorkflow $workflow) {}

    public function index(ProcurementRequestIndexRequest $request): Response
    {
        $filters = $request->validated();
        $moduleKind = $request->route('document_kind');
        $procurementRequests = ProcurementRequest::query()
            ->when($moduleKind, fn ($query) => $query->where('document_kind', $moduleKind))
            ->when(($filters['queue'] ?? null) === 'archived', fn ($query) => $query->onlyTrashed())
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('pr_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('purchase_order_no', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lines', fn ($query) => $query->where('item_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['queue'] ?? null) === 'needs_action', fn ($query) => $query->whereNotIn('status', ['accepted', 'rejected', 'cancelled', 'endorsed']))
            ->when(($filters['queue'] ?? null) === 'completed', fn ($query) => $query->whereIn('status', ['accepted', 'rejected', 'cancelled', 'endorsed']))
            ->with(['attachments', 'lines.item', 'lines.assetCategory', 'lines.assetSubcategory', 'actions.actor:id,name', 'creator:id,name', 'supplyRequest:id,ris_no'])
            ->withCount('deliveries')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $procurementRequests->getCollection()->each(function (ProcurementRequest $record): void {
            $record->setAttribute('can_delete', $record->canDelete());
            $record->setAttribute('can_archive', $record->canArchive());
        });

        return Inertia::render($request->route('document_kind') === null ? 'Inventory/Procurement/Index' : match ($moduleKind) {
            'PR' => 'Inventory/Procurement/PurchaseRequests', 'JO' => 'Inventory/Procurement/JobOrders'
        }, [
            'moduleKind' => $moduleKind,
            'requestFormContext' => $this->requestFormContext($request->user()),
            'chargeAccounts' => [],
            'procurementRequests' => $procurementRequests,
            'items' => InventoryItem::query()->where('status', 'active')->orderBy('name')->get(['inventory_item_id', 'name', 'unit_of_measure', 'quantity', 'reorder_point', 'reorder_quantity']),
            'seriesCategories' => InventorySeriesCategory::query()->active()->orderBy('name')->get(['inv_series_cat_id', 'name']),
            'assetCategories' => InventoryAssetCategory::query()
                ->active()
                ->with(['subcategories' => fn ($query) => $query->active()->orderBy('name')])
                ->orderBy('name')
                ->get(['inv_asset_cat_id', 'name']),
            'filters' => $filters,
            'procurementCategories' => PreprocurementWorkflow::categories(),
            'signatories' => User::query()->where('is_active', true)->whereIn('role', ['super_admin', 'inventory_manager'])->with('employee')->orderBy('name')->get()->map(fn (User $user): array => [
                ...($user->employee?->toSelectionOption() ?? ['name' => $user->name, 'type' => 'employee', 'code' => null]),
                'id' => $user->id,
            ]),
        ]);
    }

    public function store(StoreProcurementRequestRequest $request): RedirectResponse
    {
        if ($request->isRequestForm()) {
            return $this->storeRequestForm($request);
        }

        DB::transaction(function () use ($request) {
            $record = ProcurementRequest::query()->create([...$request->safe()->except('lines'), 'pr_no' => 'TMP-'.Str::uuid(), 'created_by_user_id' => $request->user()->id, 'status' => 'intake']);
            $record->update(['pr_no' => sprintf('REQ-%s-%06d', now()->format('Y'), $record->id)]);
            $record->lines()->createMany($request->validated('lines', []));
            $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => 'receive_requisition', 'to_status' => 'intake', 'attestation' => 'Approved end-user requisition received for document checking.']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Requisition received. Upload supporting documents before review.']);

        return back();
    }

    /** @return array{date: string, department: string|null, division: string|null, requested_by: string, checked_by: string|null, approved_by: string|null} */
    private function requestFormContext(User $user): array
    {
        $employee = $user->employee;

        return [
            'date' => now()->toDateString(),
            'department' => data_get($employee?->metadata, 'position.department.name'),
            'division' => data_get($employee?->metadata, 'position.division.name'),
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

    private function storeRequestForm(StoreProcurementRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $context = $this->requestFormContext($request->user());
            $record = ProcurementRequest::query()->create([
                'pr_no' => 'TMP-'.Str::uuid(),
                'created_by_user_id' => $request->user()->id,
                'document_kind' => $data['document_kind'],
                'procurement_category' => $data['document_kind'] === 'JO' ? 'other_services' : 'supplies',
                'type' => 'new_item',
                'source' => 'manual',
                'status' => 'intake',
                'purpose' => $data['purpose'],
                'required_at' => $data['required_at'],
                'preprocurement' => [
                    'request_form' => true,
                    ...$context,
                    'end_user' => $context['requested_by'],
                    'requested_by_user_id' => $request->user()->id,
                    'abc' => number_format((float) $data['abc'], 2, '.', ''),
                    'charge_account' => $data['charge_account'] ?? null,
                    'is_replacement' => false,
                ],
            ]);
            $record->update(['pr_no' => sprintf('REQ-%s-%06d', now()->format('Y'), $record->id)]);
            $record->lines()->createMany(array_map(fn (array $line): array => [
                ...$line,
                'item_kind' => 'consumable',
                'estimated_unit_cost' => 0,
            ], $data['lines']));
            $record->actions()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'receive_requisition',
                'to_status' => 'intake',
                'attestation' => 'Request form created by the requisitioner.',
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request created.']);

        return back();
    }

    public function transition(TransitionProcurementRequestRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->assertModule($request, $procurementRequest);
        $this->workflow->transition($procurementRequest, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procurement workflow updated.']);

        return back();
    }

    public function update(StoreProcurementRequestRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->assertModule($request, $procurementRequest);
        DB::transaction(function () use ($request, $procurementRequest): void {
            $record = ProcurementRequest::query()->whereKey($procurementRequest->id)->lockForUpdate()->firstOrFail();
            if (! in_array($record->status, ['intake', 'returned_for_compliance'], true)) {
                throw ValidationException::withMessages(['action' => 'Only intake or returned requisitions may be amended.']);
            }
            if ($record->supply_request_id !== null) {
                throw ValidationException::withMessages(['lines' => 'Linked shortages are maintained by the supply-request workflow. Correct intake details when submitting for document review.']);
            }
            $previous = ['details' => $record->only(['document_kind', 'procurement_category', 'purpose', 'preprocurement']), 'lines' => $record->lines()->get()->toArray()];
            $record->update($request->safe()->except(['lines', 'supply_request_id']));
            $record->lines()->delete();
            $record->lines()->createMany($request->validated('lines', []));
            $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => 'amend_requisition', 'from_status' => $record->status, 'to_status' => $record->status, 'attestation' => 'Requisition corrected before document verification.', 'metadata' => ['previous' => $previous]]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Requisition corrected. Resubmit it for document review when complete.']);

        return back();
    }

    public function uploadAttachment(StoreProcurementAttachmentRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->assertModule($request, $procurementRequest);
        $path = null;
        try {
            DB::transaction(function () use ($request, $procurementRequest, &$path): void {
                $record = ProcurementRequest::query()->whereKey($procurementRequest->id)->lockForUpdate()->firstOrFail();
                if (! in_array($record->status, ['intake', 'returned_for_compliance'], true)) {
                    throw ValidationException::withMessages(['file' => 'Attachments may only be added during intake or compliance.']);
                }
                $documentKey = $request->validated('document_key');
                if (! isset(PreprocurementWorkflow::categories()[$record->procurement_category]['documents'][$documentKey])) {
                    throw ValidationException::withMessages(['document_key' => 'This document is not part of the requisition checklist.']);
                }
                $file = $request->file('file');
                $path = $file->store('procurement/'.$record->id, 'local');
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'The document could not be stored. Please try again.']);
                }
                $attachment = $record->attachments()->create(['uploaded_by_user_id' => $request->user()->id, 'document_key' => $documentKey, 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'size' => $file->getSize()]);
                $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => 'upload_document', 'from_status' => $record->status, 'to_status' => $record->status, 'attestation' => 'Supporting document retained.', 'metadata' => ['attachment_id' => $attachment->id, 'document_key' => $documentKey]]);
            });
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supporting document retained.']);

        return back();
    }

    public function downloadAttachment(Request $request, ProcurementRequest $procurementRequest, ProcurementAttachment $attachment): StreamedResponse
    {
        $this->assertModule($request, $procurementRequest);
        abort_unless($attachment->procurement_request_id === $procurementRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function printDocument(Request $request, ProcurementRequest $procurementRequest): View
    {
        $this->assertModule($request, $procurementRequest);
        abort_if(str_starts_with($procurementRequest->pr_no, 'REQ-'), 422, 'Prepare the PR/JO after document verification before printing.');
        $signerIds = collect($procurementRequest->preprocurement ?? [])->only(['prepared_by_user_id', 'recommending_user_id', 'countersigning_user_id', 'gm_user_id']);

        return view('inventory.procurement-document', ['record' => $procurementRequest->load(['lines', 'actions.actor', 'attachments']), 'signatories' => User::query()->whereIn('id', $signerIds)->pluck('name', 'id')]);
    }

    public function manageRecord(ManageProcurementRecordRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->assertModule($request, $procurementRequest);
        DB::transaction(function () use ($request, $procurementRequest): void {
            $record = ProcurementRequest::withTrashed()->whereKey($procurementRequest->id)->lockForUpdate()->firstOrFail();
            $action = $request->route('record_action');
            $allowed = match ($action) {
                'delete' => $record->canDelete(), 'archive' => $record->canArchive(), 'restore' => $record->trashed(), default => false
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['action' => 'This record cannot be deleted, archived, or restored at its current stage.']);
            }
            $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => $action, 'from_status' => $record->status, 'to_status' => $record->status, 'remarks' => $request->validated('remarks'), 'attestation' => 'Record management decision confirmed.']);
            if ($action === 'restore') {
                $record->restore();
            } else {
                $record->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Record updated. Deleted and archived records remain recoverable in the archive.']);

        return back();
    }

    private function assertModule(Request $request, ProcurementRequest $record): void
    {
        $kind = $request->route('document_kind');
        abort_if($kind !== null && $record->document_kind !== $kind, 404);
    }
}
