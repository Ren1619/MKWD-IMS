<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryMonthlyReportIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryMonthlyReportRequest;
use App\Http\Requests\Inventory\TransitionInventoryMonthlyReportRequest;
use App\Models\InventoryMonthlyReport;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use App\Services\InventoryMonthlyReportBuilder;
use App\Services\InventoryMonthlyReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMonthlyReportController extends Controller
{
    public function __construct(private InventoryMonthlyReportService $monthly, private InventoryMonthlyReportBuilder $builder) {}

    public function index(InventoryMonthlyReportIndexRequest $request): Response
    {
        return Inertia::render('Inventory/MonthlyReports/Index', [
            'documents' => InventoryMonthlyReport::query()->select(['id', 'document_no', 'period_month', 'revision', 'classification_name', 'include_zero', 'fund_cluster', 'status', 'approved_at', 'transmitted_at'])
                ->when($request->filled('period_month'), fn ($query) => $query->whereDate('period_month', $request->validated('period_month').'-01'))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
                ->when($request->filled('search'), fn ($query) => $query->where('document_no', 'like', '%'.$request->validated('search').'%'))->latest('id')->paginate(15)->withQueryString(),
            'filters' => $request->safe()->only(['search', 'status', 'period_month'])]);
    }

    public function create(InventoryMonthlyReportIndexRequest $request): Response
    {
        $month = $request->validated('period_month') ?? now('Asia/Manila')->subMonthNoOverflow()->format('Y-m');
        $category = $request->integer('series_category_id') ?: null;
        $zero = $request->has('include_zero') ? $request->boolean('include_zero') : true;
        $fund = $request->validated('fund_cluster') ?? '01';
        $built = $this->builder->build($month, $category, $zero, $fund);

        return Inertia::render('Inventory/MonthlyReports/Create', [
            'selection' => ['period_month' => $month, 'series_category_id' => $category, 'include_zero' => $zero, 'fund_cluster' => $fund],
            'readiness' => $built['readiness'], 'catalog' => InventoryMonthlyReportBuilder::catalog(),
            'categories' => InventorySeriesCategory::query()->orderBy('name')->get(['inv_series_cat_id', 'name']),
            'signatories' => User::query()->where('is_active', true)->whereIn('role', ['inventory_manager', 'super_admin'])->with('employee')->orderBy('name')->get()->map(fn (User $user): array => [...($user->employee?->toSelectionOption() ?? ['name' => $user->name, 'type' => 'employee', 'code' => null]), 'id' => $user->id]),
            'preparer' => ['id' => $request->user()->id, 'name' => $request->user()->name]]);
    }

    public function store(StoreInventoryMonthlyReportRequest $request): RedirectResponse
    {
        $document = $this->monthly->create($request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monthly package prepared from reconciled source records.']);

        return to_route('inventory.monthly_reports.show', $document);
    }

    public function show(InventoryMonthlyReport $report): Response
    {
        $report->load(['snapshot', 'actions' => fn ($query) => $query->orderBy('id'), 'supersedes:id,document_no']);
        $current = in_array($report->status, ['approved', 'transmitted', 'cancelled'], true) ? null : $this->builder->build($report->period_month->format('Y-m'), $report->series_category_id, $report->include_zero, $report->fund_cluster);

        return Inertia::render('Inventory/MonthlyReports/Show', ['document' => $report, 'allowedActions' => $this->monthly->allowedActions($report, auth()->user()), 'currentReadiness' => $current['readiness'] ?? null, 'isStale' => $current !== null && $current['hash'] !== $report->snapshot->source_hash]);
    }

    public function transition(TransitionInventoryMonthlyReportRequest $request, InventoryMonthlyReport $report): RedirectResponse
    {
        $this->monthly->transition($report, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monthly report decision recorded.']);

        return back();
    }

    public function print(InventoryMonthlyReport $report, string $document): View
    {
        $report->load(['snapshot', 'actions' => fn ($query) => $query->orderBy('id'), 'supersedes:id,document_no']);
        abort_if($document === 'transmittal' && ! in_array($report->status, ['approved', 'transmitted'], true), 404);

        return view('inventory.monthly-report', ['packet' => $report, 'document' => $document, 'tables' => $document === 'packet' ? $report->snapshot->payload['reports'] : [$document => $report->snapshot->payload['reports'][$document] ?? null]]);
    }
}
