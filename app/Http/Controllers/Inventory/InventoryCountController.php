<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordInventoryCountRequest;
use App\Http\Requests\Inventory\StoreInventoryCountRequest;
use App\Http\Requests\Inventory\TransitionInventoryCountRequest;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\InventoryStockControl;
use App\Services\InventoryCountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountController extends Controller
{
    public function __construct(private InventoryCountService $counts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Counts/Index', [
            'sessions' => InventoryCountSession::query()->withCount('lines')->latest()->paginate(15)->withQueryString(),
            'activeSessionId' => InventoryStockControl::query()->find(1)?->active_count_session_id,
        ]);
    }

    public function store(StoreInventoryCountRequest $request): RedirectResponse
    {
        $session = $this->counts->create($request->countPlan(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Physical count planned. Start it to freeze book balances.']);

        return to_route('inventory.counts.show', $session);
    }

    public function show(Request $request, InventoryCountSession $session): Response
    {
        $participants = $session->lines()->join('inventory_count_entries', 'inventory_count_lines.id', '=', 'inventory_count_entries.inventory_count_line_id')
            ->pluck('recorded_by_user_id')->push($session->created_by_user_id);
        $independent = ! $participants->contains($request->user()->id);
        $allowedActions = match ($session->status) {
            'planned' => ['start', 'cancel'],
            'counting' => ['submit', 'cancel'],
            'submitted' => [...($independent ? ['verify'] : []), 'return_for_recount', 'cancel'],
            'verified' => [...($independent && $session->verified_by_user_id !== $request->user()->id ? ['approve'] : []), 'cancel'],
            default => [],
        };

        return Inertia::render('Inventory/Counts/Show', [
            'session' => $session,
            'allowedActions' => $allowedActions,
            'lines' => $session->lines()->with(['latestEntry', 'entries'])->orderBy('stock_number')->paginate(25)->withQueryString(),
            'actions' => $session->actions()->with('actor:id,name')->latest()->limit(50)->get(),
            'summary' => [
                'items' => $session->lines()->count(),
                'counted' => $session->lines()->has('entries')->count(),
            ],
        ]);
    }

    public function record(RecordInventoryCountRequest $request, InventoryCountSession $session, InventoryCountLine $line): RedirectResponse
    {
        abort_unless($line->inventory_count_session_id === $session->id, 404);
        $this->counts->record($session, $line, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Count observation saved; previous observations retained.']);

        return back();
    }

    public function transition(TransitionInventoryCountRequest $request, InventoryCountSession $session): RedirectResponse
    {
        $data = $request->validated();
        if (in_array($data['action'], ['emergency_issue', 'segregated_receipt'], true)) {
            $this->counts->movement($session, $data, $request->user());
        } else {
            $this->counts->transition($session, $data, $request->user());
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Physical count action recorded.']);

        return back();
    }

    public function print(InventoryCountSession $session, string $document): View
    {
        return view('inventory.physical-count', [
            'session' => $session->load(['lines.latestEntry', 'lines.adjustments', 'actions.actor']),
            'document' => $document,
            'signatures' => $session->actions()->orderBy('id')->get()->keyBy('action')->map(fn ($action): string => $action->actor_name),
        ]);
    }
}
