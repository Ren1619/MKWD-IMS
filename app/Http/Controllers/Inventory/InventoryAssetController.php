<?php

namespace App\Http\Controllers\Inventory;

use App\AssetAccountingClassification;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssignInventoryAssetRequest;
use App\Http\Requests\Inventory\InventoryAssetIndexRequest;
use App\Http\Requests\Inventory\PrintInventoryAssetLapsingScheduleRequest;
use App\Http\Requests\Inventory\ReturnInventoryAssetRequest;
use App\Http\Requests\Inventory\StoreInventoryAssetRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetAccountingRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetStateRequest;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\User;
use App\Services\InventoryAssetLifecycleService;
use App\Services\InventoryAssetService;
use App\Services\PpeDepreciationCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class InventoryAssetController extends Controller
{
    public function __construct(
        private InventoryAssetService $assetService,
        private InventoryAssetLifecycleService $lifecycle,
    ) {}

    public function index(InventoryAssetIndexRequest $request): Response
    {
        return $this->registry($request, AssetAccountingClassification::Ppe);
    }

    public function semiExpendableIndex(InventoryAssetIndexRequest $request): Response
    {
        return $this->registry($request, AssetAccountingClassification::SemiExpendable);
    }

    public function show(Request $request, InventoryAsset $asset): Response
    {
        return $this->details($request, $asset, AssetAccountingClassification::Ppe);
    }

    public function showSemiExpendable(Request $request, InventoryAsset $asset): Response
    {
        return $this->details($request, $asset, AssetAccountingClassification::SemiExpendable);
    }

    private function details(Request $request, InventoryAsset $asset, AssetAccountingClassification $classification): Response
    {
        abort_unless($asset->accounting_classification === $classification, 404);
        $user = $request->user();
        abort_unless($user->canViewAllPropertyAssets()
            || ($user->hris_reference_id !== null && $asset->current_custodian_reference_id === $user->hris_reference_id), 403);

        $asset->load([
            'category', 'subcategory', 'currentCustodian:id,name,code',
            'activeBorrowing.borrowerReference:id,name,code',
            'events.recordedBy:id,name', 'latestIcsDocument',
            'borrowings.borrowerReference:id,name,code',
            'accountabilityDocuments',
        ]);

        return Inertia::render('Inventory/Assets/Show', [
            'asset' => $asset,
            'employees' => $classification === AssetAccountingClassification::Ppe
                ? HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)
                    ->get(['id', 'type', 'code', 'name', 'email', 'metadata'])
                    ->map(fn (HrisReference $employee): array => $employee->toSelectionOption())
                : [],
            'reportYear' => today()->year,
        ]);
    }

    public function lapsingSchedule(PrintInventoryAssetLapsingScheduleRequest $request, InventoryAsset $asset, PpeDepreciationCalculator $calculator): View
    {
        abort_unless($asset->accounting_classification === AssetAccountingClassification::Ppe, 404);
        $data = $request->validated();
        $year = (int) $data['year'];
        $asset->loadMissing('events');
        $openingDate = CarbonImmutable::create($year, 1, 1)->subDay();
        $previous = $openingDate->lt($asset->acquisition_date) ? null : $calculator->calculate($asset, $openingDate);
        $opening = $previous;
        $rows = [];
        for ($month = 1; $month <= 12; $month++) {
            $date = CarbonImmutable::create($year, $month, 1)->endOfMonth();
            $valuation = $date->lt($asset->acquisition_date) ? null : $calculator->calculate($asset, $date);
            $rows[] = [
                'month' => $date->format('F'),
                'valuation' => $valuation,
                'expense' => round((float) ($valuation?->accumulatedDepreciation ?? 0) - (float) ($previous?->accumulatedDepreciation ?? 0), 2),
            ];
            $previous = $valuation;
        }

        return view('inventory.asset-lapsing-schedule', [
            'asset' => $asset, 'year' => $year, 'opening' => $opening, 'rows' => $rows,
            'preparedBy' => HrisReference::findOrFail($data['prepared_by']),
            'certifiedBy' => HrisReference::findOrFail($data['certified_by']),
        ]);
    }

    private function registry(
        InventoryAssetIndexRequest $request,
        AssetAccountingClassification $classification,
    ): Response {
        $filters = $request->safe()->only($classification === AssetAccountingClassification::SemiExpendable
            ? ['search', 'condition_status', 'accountability_status', 'ics_renewal', 'records']
            : ['search', 'records', 'lifecycle_status', 'condition_status', 'custody_status', 'sort', 'direction', 'value_class', 'accountability_status', 'physical_status', 'ics_renewal']);
        $user = $request->user();
        $assets = InventoryAsset::query()
            ->where('accounting_classification', $classification)
            ->when(
                ! $user->canViewAllPropertyAssets(),
                fn ($query) => $user->hris_reference_id === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('current_custodian_reference_id', $user->hris_reference_id),
            )
            ->when(($filters['records'] ?? '') === 'archived', fn ($query) => $query->onlyTrashed())
            ->with([
                'category',
                'subcategory',
                'currentCustodian:id,name,code',
                'activeBorrowing.borrowerReference:id,name,code',
                'events' => fn ($query) => $query->with('recordedBy:id,name')->limit(25),
                'latestIcsDocument',
            ])
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where(function ($nested) use ($filters) {
                $search = '%'.($filters['search'] ?? '').'%';
                $nested->where('item_description', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('property_number', 'like', $search)
                    ->orWhere('brand', 'like', $search)
                    ->orWhere('model', 'like', $search)
                    ->orWhere('location', 'like', $search)
                    ->orWhere('supplier_name', 'like', $search)
                    ->orWhere('invoice_no', 'like', $search)
                    ->orWhereHas('currentCustodian', fn ($employee) => $employee->where('name', 'like', $search))
                    ->orWhereHas('accountabilityDocuments', fn ($document) => $document->where('document_no', 'like', $search));
            }))
            ->when(filled($filters['lifecycle_status'] ?? null), fn ($query) => $query->where('lifecycle_status', ($filters['lifecycle_status'] ?? '')))
            ->when(filled($filters['condition_status'] ?? null), fn ($query) => $query->where('condition_status', ($filters['condition_status'] ?? '')))
            ->when(($filters['custody_status'] ?? '') === 'borrowed', fn ($query) => $query->whereHas('activeBorrowing'))
            ->when(($filters['custody_status'] ?? '') === 'assigned', fn ($query) => $query->whereNotNull('current_custodian_reference_id')->whereDoesntHave('activeBorrowing'))
            ->when(($filters['custody_status'] ?? '') === 'available', fn ($query) => $query->whereNull('current_custodian_reference_id')->where('lifecycle_status', '!=', AssetLifecycleStatus::TransferredOut)->whereDoesntHave('activeBorrowing'))
            ->when(($filters['custody_status'] ?? '') === 'transferred_out', fn ($query) => $query->where('lifecycle_status', AssetLifecycleStatus::TransferredOut))
            ->when(filled($filters['value_class'] ?? null), fn ($query) => $query->where('semi_expendable_value_class', ($filters['value_class'] ?? '')))
            ->when(filled($filters['accountability_status'] ?? null), fn ($query) => $query->where('accountability_status', ($filters['accountability_status'] ?? '')))
            ->when(filled($filters['physical_status'] ?? null), fn ($query) => $query->where('physical_status', ($filters['physical_status'] ?? '')))
            ->when(($filters['ics_renewal'] ?? '') === 'overdue', fn ($query) => $query->whereHas('latestIcsDocument', fn ($document) => $document->whereDate('renewal_due_at', '<', today())->whereIn('status', ['pending_recipient', 'pending_note', 'active'])))
            ->when(($filters['ics_renewal'] ?? '') === 'due_soon', fn ($query) => $query->whereHas('latestIcsDocument', fn ($document) => $document->whereBetween('renewal_due_at', [today(), today()->addDays((int) config('inventory.semi_expendable.renewal_due_soon_days', 90))])->whereIn('status', ['pending_recipient', 'pending_note', 'active'])))
            ->orderBy(
                ($filters['sort'] ?? '') ?: 'inventory_asset_id',
                ($filters['direction'] ?? '') ?: 'desc',
            )
            ->paginate(15)
            ->appends($filters);

        return Inertia::render('Inventory/Assets/Index', [
            'assets' => $assets,
            'categories' => InventoryAssetCategory::query()
                ->active()
                ->with(['subcategories' => fn ($query) => $query->active()->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'employees' => HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'type', 'code', 'name', 'email', 'metadata'])
                ->map(fn (HrisReference $employee): array => $employee->toSelectionOption()),
            'filters' => $filters,
            'registryType' => $classification->value,
            'registrySummary' => $classification === AssetAccountingClassification::SemiExpendable
                ? $this->semiExpendableSummary($user)
                : $this->ppeSummary($user),
            'capitalizationThreshold' => (float) AssetAccountingClassification::capitalizationThreshold(),
            'assetEventOptions' => array_map(
                fn (InventoryAssetEventType $type): array => ['value' => $type->value, 'label' => $type->label()],
                $classification === AssetAccountingClassification::Ppe
                    ? InventoryAssetEventType::userRecordable()
                    : InventoryAssetEventType::semiExpendableRecordable(),
            ),
            'assetStateOptions' => [
                'lifecycles' => array_map(
                    fn (AssetLifecycleStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    AssetLifecycleStatus::cases(),
                ),
                'conditions' => array_map(
                    fn (AssetConditionStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    AssetConditionStatus::cases(),
                ),
            ],
        ]);
    }

    public function store(StoreInventoryAssetRequest $request): RedirectResponse
    {
        $asset = $this->lifecycle->register($request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'PPE item registered.']);

        return to_route($this->registryRoute($asset));
    }

    public function storeSemiExpendable(StoreInventoryAssetRequest $request): RedirectResponse
    {
        $assets = $this->lifecycle->registerBatch($request->validated(), $request->user());
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $assets->count() === 1
                ? 'Semi-expendable property registered.'
                : "{$assets->count()} individually tracked semi-expendable items registered.",
        ]);

        return to_route('inventory.semi_expendable.index');
    }

    public function update(UpdateInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->updateDetails($asset, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['location' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset updated.']);

        return to_route($this->registryRoute($asset));
    }

    public function updateAccounting(
        UpdateInventoryAssetAccountingRequest $request,
        InventoryAsset $asset,
    ): RedirectResponse {
        if ($asset->accounting_classification !== AssetAccountingClassification::Ppe) {
            throw ValidationException::withMessages([
                'residual_value_percentage' => 'Residual value and depreciation settings apply only to PPE.',
            ]);
        }

        $this->lifecycle->updateAccounting($asset, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'PPE accounting settings updated.']);

        return back();
    }

    public function destroy(InventoryAsset $asset): RedirectResponse
    {
        if ($asset->current_custodian_reference_id !== null || $asset->activeBorrowing()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This asset cannot be archived while assigned or borrowed. Return and unassign it first.',
            ]);

            return to_route($this->registryRoute($asset));
        }

        if (
            $asset->accountabilityDocuments()->exists()
            || $asset->events()->whereNotIn('event_type', [
                InventoryAssetEventType::Registered->value,
                InventoryAssetEventType::Accepted->value,
            ])->exists()
        ) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This historical property record cannot be archived. Post the appropriate return, loss, or disposal transaction instead.',
            ]);

            return to_route($this->registryRoute($asset));
        }

        $asset->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset archived.']);

        return to_route($this->registryRoute($asset));
    }

    public function restore(InventoryAsset $asset): RedirectResponse
    {
        $asset->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset restored.']);

        return to_route($this->registryRoute($asset), ['records' => 'archived']);
    }

    public function assign(AssignInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        if ($asset->current_custodian_reference_id !== null) {
            throw ValidationException::withMessages(['hris_reference_id' => 'Use a Property Transfer Report with verification, approval, and handover for assigned property.']);
        }
        $reference = HrisReference::query()->findOrFail($request->integer('hris_reference_id'));

        try {
            $this->assetService->assign(
                $asset,
                $reference,
                $request->user(),
                $request->validated('replacement_justification'),
                initialAssignmentOnly: true,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['hris_reference_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Custodian assigned.']);

        return to_route($this->registryRoute($asset));
    }

    public function unassign(InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->unassign($asset, request()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['asset' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Custodian unassigned.']);

        return to_route($this->registryRoute($asset));
    }

    public function returnBorrowed(ReturnInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->returnBorrowed($asset, $request->user(), $request->validated('return_notes'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['return_notes' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset returned.']);

        return to_route($this->registryRoute($asset));
    }

    public function updateState(UpdateInventoryAssetStateRequest $request, InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->updateState($asset, [
                'lifecycle_status' => $request->string('lifecycle_status')->toString(),
                'condition_status' => $request->string('condition_status')->toString(),
            ], $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lifecycle_status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset state updated.']);

        return to_route($this->registryRoute($asset));
    }

    private function registryRoute(InventoryAsset $asset): string
    {
        return $asset->accounting_classification === AssetAccountingClassification::SemiExpendable
            ? 'inventory.semi_expendable.index'
            : 'inventory.assets.index';
    }

    /** @return array<string, int|float> */
    private function ppeSummary(User $user): array
    {
        $summary = ['total' => 0, 'acquisition_cost' => 0.0, 'net_book_value' => 0.0, 'available_for_assignment' => 0, 'requiring_action' => 0];
        $asOf = today();

        InventoryAsset::query()
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->when(
                ! $user->canViewAllPropertyAssets(),
                fn ($query) => $user->hris_reference_id === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('current_custodian_reference_id', $user->hris_reference_id),
            )
            ->with(['events', 'activeBorrowing'])
            ->chunkById(200, function ($assets) use (&$summary, $asOf): void {
                foreach ($assets as $asset) {
                    $summary['total']++;
                    $summary['acquisition_cost'] += (float) $asset->acquisition_cost;
                    $summary['net_book_value'] += (float) $asset->valuation($asOf)->carryingAmount;

                    if (
                        $asset->current_custodian_reference_id === null
                        && $asset->activeBorrowing === null
                        && in_array($asset->lifecycle_status, [AssetLifecycleStatus::Active, AssetLifecycleStatus::Idle], true)
                        && in_array($asset->condition_status, [AssetConditionStatus::Good, AssetConditionStatus::Fair], true)
                    ) {
                        $summary['available_for_assignment']++;
                    }

                    if (
                        in_array($asset->lifecycle_status, [AssetLifecycleStatus::Lost, AssetLifecycleStatus::UnderMaintenance, AssetLifecycleStatus::ForDisposal], true)
                        || in_array($asset->condition_status, [AssetConditionStatus::NeedsRepair, AssetConditionStatus::Defective, AssetConditionStatus::NonUsable], true)
                    ) {
                        $summary['requiring_action']++;
                    }
                }
            }, 'inventory_asset_id');

        $summary['acquisition_cost'] = round($summary['acquisition_cost'], 2);
        $summary['net_book_value'] = round($summary['net_book_value'], 2);

        return $summary;
    }

    /** @return array<string, int|float> */
    private function semiExpendableSummary(User $user): array
    {
        $query = InventoryAsset::query()
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->when(
                ! $user->canViewAllPropertyAssets(),
                fn ($query) => $user->hris_reference_id === null
                    ? $query->whereRaw('1 = 0')
                    : $query->where('current_custodian_reference_id', $user->hris_reference_id),
            );

        $exceptions = (clone $query)
            ->where(function ($query): void {
                $query->where('physical_status', 'missing')
                    ->orWhere('relief_status', 'pending')
                    ->orWhereHas('latestIcsDocument', fn ($document) => $document
                        ->whereDate('renewal_due_at', '<', today())
                        ->whereIn('status', ['pending_recipient', 'pending_note', 'active']));
            })
            ->count();

        $summary = (array) $query
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(acquisition_cost), 0) AS acquisition_cost')
            ->selectRaw("SUM(CASE WHEN accounting_status = 'on_hand' THEN 1 ELSE 0 END) AS on_hand")
            ->selectRaw("SUM(CASE WHEN accountability_status IN ('pending_recipient', 'accountable', 'pending_relief') THEN 1 ELSE 0 END) AS issued_or_accountable")
            ->selectRaw('SUM(CASE WHEN available_for_reissue = 1 THEN 1 ELSE 0 END) AS available_for_reissue')
            ->first();

        return collect($summary)
            ->map(fn (mixed $value): int|float => is_numeric($value) ? (float) $value : 0)
            ->put('exceptions', $exceptions)
            ->all();
    }
}
