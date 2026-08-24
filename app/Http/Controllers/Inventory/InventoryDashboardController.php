<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $assets = InventoryAsset::query()->get(['inventory_asset_id', 'acquisition_cost']);
        $today = Carbon::today();
        $startDate = $today->copy()->subDays(6);
        $stockIns = InventoryItemBatch::query()
            ->whereBetween('received_at', [$startDate, $today])
            ->selectRaw('received_at as movement_date, SUM(quantity_in) as units')
            ->groupBy('received_at')
            ->pluck('units', 'movement_date');
        $stockOuts = InventoryItemStockOut::query()
            ->whereBetween('stocked_out_at', [$startDate, $today])
            ->selectRaw('stocked_out_at as movement_date, SUM(quantity) as units')
            ->groupBy('stocked_out_at')
            ->pluck('units', 'movement_date');

        return Inertia::render('Inventory/Dashboard', [
            'metrics' => [
                'item_types' => InventoryItem::query()->count(),
                'stock_on_hand' => (int) InventoryItem::query()->sum('quantity'),
                'low_stock' => InventoryItem::query()->lowStock()->count(),
                'expired_batches' => InventoryItemBatch::query()
                    ->where('quantity_remaining', '>', 0)
                    ->whereDate('expiration_date', '<', $today)
                    ->whereHas('item', fn ($query) => $query->where('status', 'active'))
                    ->count(),
                'expiring_batches' => InventoryItemBatch::query()
                    ->where('quantity_remaining', '>', 0)
                    ->whereBetween('expiration_date', [$today, $today->copy()->addDays(InventoryItem::EXPIRATION_WARNING_DAYS)])
                    ->whereHas('item', fn ($query) => $query->where('status', 'active'))
                    ->count(),
                'assets' => $assets->count(),
                'asset_cost' => round((float) $assets->sum('acquisition_cost'), 2),
                'borrowed_assets' => InventoryAsset::query()->whereHas('activeBorrowing')->count(),
                'unassigned_assets' => InventoryAsset::query()->whereNull('current_custodian_reference_id')->count(),
                'overdue_borrowings' => InventoryAssetBorrowing::query()
                    ->where('status', 'borrowed')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->count(),
                'employee_records' => HrisReference::query()
                    ->where('type', HrisReference::TYPE_EMPLOYEE)
                    ->where('is_active', true)
                    ->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'stock_in_today' => (int) InventoryItemBatch::query()->whereDate('received_at', $today)->sum('quantity_in'),
                'stock_out_today' => (int) InventoryItemStockOut::query()->whereDate('stocked_out_at', $today)->sum('quantity'),
                'activity_today' => AuditLog::query()->whereDate('created_at', $today)->count(),
            ],
            'dailyMovements' => collect(range(0, 6))->map(function (int $offset) use ($startDate, $stockIns, $stockOuts): array {
                $date = $startDate->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'label' => $date->format('D'),
                    'stock_in' => (int) ($stockIns[$key] ?? 0),
                    'stock_out' => (int) ($stockOuts[$key] ?? 0),
                ];
            }),
            'recentStockOuts' => InventoryItemStockOut::query()
                ->with(['item:inventory_item_id,name,unit_of_measure', 'recipientReference:id,name,type', 'allocations'])
                ->latest('stocked_out_at')
                ->limit(8)
                ->get(),
            'lastEmployeeSync' => HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->max('last_synced_at'),
        ]);
    }
}
