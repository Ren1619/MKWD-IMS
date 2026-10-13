<?php

use App\Http\Controllers\Inventory\DepartmentLiquidationController;
use App\Http\Controllers\Inventory\InventoryAssetController;
use App\Http\Controllers\Inventory\InventoryAssetEventController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryCountController;
use App\Http\Controllers\Inventory\InventoryDisposalController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryMonthlyReportController;
use App\Http\Controllers\Inventory\InventoryPropertyTransferController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\InventoryReturnController;
use App\Http\Controllers\Inventory\InventoryWarehouseController;
use App\Http\Controllers\Inventory\IssuanceLiquidationController;
use App\Http\Controllers\Inventory\ProcurementDeliveryController;
use App\Http\Controllers\Inventory\ProcurementRequestController;
use App\Http\Controllers\Inventory\PropertyAccountabilityController;
use App\Http\Controllers\Inventory\PropertyTagController;
use App\Http\Controllers\Inventory\SemiExpendablePropertyEventController;
use App\Http\Controllers\Inventory\SupplyRequestController;
use App\Services\InventoryMonthlyReportBuilder;
use App\Services\InventoryReportService;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('categories', [InventoryCategoryController::class, 'index'])->name('categories.index');
    Route::get('items', [InventoryItemController::class, 'index'])->name('items.index');
    Route::get('items/{item}', [InventoryItemController::class, 'show'])->withTrashed()->name('items.show');
    Route::get('assets', [InventoryAssetController::class, 'index'])->name('assets.index');
    Route::get('semi-expendable-property', [InventoryAssetController::class, 'semiExpendableIndex'])->name('semi_expendable.index');
    Route::middleware('can:view-property-governance')->group(function () {
        Route::get('reports', [InventoryReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [InventoryReportController::class, 'export'])->name('reports.export');
        Route::get('reports/{document}/print', [InventoryReportController::class, 'print'])
            ->whereIn('document', InventoryReportService::documentKeys())
            ->name('reports.print');
        Route::get('accountability', [PropertyAccountabilityController::class, 'index'])->name('accountability.index');
        Route::patch('accountability/{document}/transition', [PropertyAccountabilityController::class, 'transition'])->name('accountability.transition');
        Route::get('accountability/{document}/print', [PropertyAccountabilityController::class, 'print'])->name('accountability.print');
    });
    Route::get('requests', [SupplyRequestController::class, 'index'])->name('requests.index');
    Route::get('issuance-slips/{slip}/liquidation/print', [IssuanceLiquidationController::class, 'print'])->name('liquidations.print');
    Route::post('requests', [SupplyRequestController::class, 'store'])->name('requests.store');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('disposals', [InventoryDisposalController::class, 'index'])->name('disposals.index');
        Route::post('disposals', [InventoryDisposalController::class, 'store'])->name('disposals.store');
        Route::get('disposals/{document}', [InventoryDisposalController::class, 'show'])->name('disposals.show');
        Route::patch('disposals/{document}/transition', [InventoryDisposalController::class, 'transition'])->name('disposals.transition');
        Route::get('disposals/{document}/print', [InventoryDisposalController::class, 'print'])->name('disposals.print');
        Route::get('property-transfers', [InventoryPropertyTransferController::class, 'index'])->name('transfers.index');
        Route::post('property-transfers', [InventoryPropertyTransferController::class, 'store'])->name('transfers.store');
        Route::get('property-transfers/{transfer}', [InventoryPropertyTransferController::class, 'show'])->name('transfers.show');
        Route::patch('property-transfers/{transfer}/transition', [InventoryPropertyTransferController::class, 'transition'])->name('transfers.transition');
        Route::get('property-transfers/{transfer}/print', [InventoryPropertyTransferController::class, 'print'])->name('transfers.print');
        Route::get('warehouse', [InventoryWarehouseController::class, 'index'])->name('warehouse.index');
        Route::get('warehouse/batches/{batch}', [InventoryWarehouseController::class, 'show'])->name('warehouse.show');
        Route::post('warehouse/batches/{batch}/placements', [InventoryWarehouseController::class, 'store'])->name('warehouse.store');
        Route::get('warehouse/batches/{batch}/label', [InventoryWarehouseController::class, 'label'])->name('warehouse.label');
        Route::get('monthly-reports', [InventoryMonthlyReportController::class, 'index'])->name('monthly_reports.index');
        Route::get('monthly-reports/create', [InventoryMonthlyReportController::class, 'create'])->name('monthly_reports.create');
        Route::post('monthly-reports', [InventoryMonthlyReportController::class, 'store'])->name('monthly_reports.store');
        Route::get('monthly-reports/{report}', [InventoryMonthlyReportController::class, 'show'])->name('monthly_reports.show');
        Route::patch('monthly-reports/{report}/transition', [InventoryMonthlyReportController::class, 'transition'])->name('monthly_reports.transition');
        Route::get('monthly-reports/{report}/print/{document}', [InventoryMonthlyReportController::class, 'print'])->whereIn('document', ['packet', 'transmittal', ...array_keys(InventoryMonthlyReportBuilder::catalog())])->name('monthly_reports.print');
        Route::get('returns', [InventoryReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/create', [InventoryReturnController::class, 'create'])->name('returns.create');
        Route::post('returns', [InventoryReturnController::class, 'store'])->name('returns.store');
        Route::get('returns/{document}', [InventoryReturnController::class, 'show'])->name('returns.show');
        Route::patch('returns/{document}/transition', [InventoryReturnController::class, 'transition'])->name('returns.transition');
        Route::get('returns/{document}/print', [InventoryReturnController::class, 'print'])->name('returns.print');
        Route::get('counts', [InventoryCountController::class, 'index'])->name('counts.index');
        Route::post('counts', [InventoryCountController::class, 'store'])->name('counts.store');
        Route::get('counts/{session}', [InventoryCountController::class, 'show'])->name('counts.show');
        Route::patch('counts/{session}/lines/{line}', [InventoryCountController::class, 'record'])->name('counts.record');
        Route::patch('counts/{session}/transition', [InventoryCountController::class, 'transition'])->name('counts.transition');
        Route::get('counts/{session}/print/{document}', [InventoryCountController::class, 'print'])->whereIn('document', ['worksheet', 'tags', 'reconciliation', 'adjustments'])->name('counts.print');
        Route::post('department-liquidations', [DepartmentLiquidationController::class, 'store'])->name('department_liquidations.store');
        Route::get('department-liquidations/materials', [DepartmentLiquidationController::class, 'allocations'])->name('department_liquidations.allocations');
        Route::patch('department-liquidations/{document}', [DepartmentLiquidationController::class, 'transition'])->name('department_liquidations.transition');
        Route::get('department-liquidations/{document}/print', [DepartmentLiquidationController::class, 'print'])->name('department_liquidations.print');
        Route::patch('issuance-slips/{slip}/liquidation', [IssuanceLiquidationController::class, 'transition'])->name('liquidations.transition');
        Route::post('assets/{asset}/accountability', [PropertyAccountabilityController::class, 'issue'])->name('accountability.issue');
        Route::patch('requests/{supplyRequest}/transition', [SupplyRequestController::class, 'transition'])->name('requests.transition');
        Route::patch('requests/{supplyRequest}/return-slip', [SupplyRequestController::class, 'recordReturnSlip'])->name('requests.return_slip');
        Route::get('procurement', [ProcurementRequestController::class, 'index'])->name('procurement.index');
        Route::get('deliveries', [ProcurementDeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/create', [ProcurementDeliveryController::class, 'create'])->name('deliveries.create');
        Route::post('deliveries', [ProcurementDeliveryController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [ProcurementDeliveryController::class, 'show'])->name('deliveries.show');
        Route::patch('deliveries/{delivery}/transition', [ProcurementDeliveryController::class, 'transition'])->name('deliveries.transition');
        Route::get('deliveries/{delivery}/print/{document}', [ProcurementDeliveryController::class, 'print'])->whereIn('document', ['iar', 'receiving-report', 'supplier-return', 'evaluation'])->name('deliveries.print');
        Route::get('deliveries/{delivery}/postponements/{action}/print', [ProcurementDeliveryController::class, 'printPostponement'])->scopeBindings()->name('deliveries.postponements.print');
        Route::post('procurement', [ProcurementRequestController::class, 'store'])->name('procurement.store');
        Route::patch('procurement/{procurementRequest}', [ProcurementRequestController::class, 'update'])->name('procurement.update');
        Route::patch('procurement/{procurementRequest}/transition', [ProcurementRequestController::class, 'transition'])->name('procurement.transition');
        Route::post('procurement/{procurementRequest}/attachments', [ProcurementRequestController::class, 'uploadAttachment'])->name('procurement.attachments.store');
        Route::get('procurement/{procurementRequest}/attachments/{attachment}', [ProcurementRequestController::class, 'downloadAttachment'])->name('procurement.attachments.download');
        Route::get('procurement/{procurementRequest}/print', [ProcurementRequestController::class, 'printDocument'])->name('procurement.print');
        foreach (['purchase-requests' => ['purchase_requests', 'PR'], 'job-orders' => ['job_orders', 'JO']] as $path => [$name, $kind]) {
            Route::get($path, [ProcurementRequestController::class, 'index'])->defaults('document_kind', $kind)->name($name.'.index');
            Route::post($path, [ProcurementRequestController::class, 'store'])->defaults('document_kind', $kind)->name($name.'.store');
            Route::patch($path.'/{procurementRequest}', [ProcurementRequestController::class, 'update'])->defaults('document_kind', $kind)->name($name.'.update');
            Route::patch($path.'/{procurementRequest}/transition', [ProcurementRequestController::class, 'transition'])->defaults('document_kind', $kind)->name($name.'.transition');
            Route::post($path.'/{procurementRequest}/attachments', [ProcurementRequestController::class, 'uploadAttachment'])->defaults('document_kind', $kind)->name($name.'.attachments.store');
            Route::get($path.'/{procurementRequest}/attachments/{attachment}', [ProcurementRequestController::class, 'downloadAttachment'])->withTrashed()->defaults('document_kind', $kind)->name($name.'.attachments.download');
            Route::get($path.'/{procurementRequest}/print', [ProcurementRequestController::class, 'printDocument'])->withTrashed()->defaults('document_kind', $kind)->name($name.'.print');
            Route::delete($path.'/{procurementRequest}', [ProcurementRequestController::class, 'manageRecord'])->defaults('document_kind', $kind)->defaults('record_action', 'delete')->name($name.'.destroy');
            Route::patch($path.'/{procurementRequest}/archive', [ProcurementRequestController::class, 'manageRecord'])->defaults('document_kind', $kind)->defaults('record_action', 'archive')->name($name.'.archive');
            Route::patch($path.'/{procurementRequest}/restore', [ProcurementRequestController::class, 'manageRecord'])->withTrashed()->defaults('document_kind', $kind)->defaults('record_action', 'restore')->name($name.'.restore');
        }
        Route::post('categories', [InventoryCategoryController::class, 'store'])->name('categories.store');
        Route::patch('categories/{type}/{category}', [InventoryCategoryController::class, 'update'])
            ->whereIn('type', ['major', 'class', 'series', 'asset'])
            ->name('categories.update');
        Route::patch('categories/{type}/{category}/status', [InventoryCategoryController::class, 'updateStatus'])
            ->whereIn('type', ['major', 'class', 'series', 'asset'])
            ->name('categories.status');
        Route::delete('categories/{type}/{category}', [InventoryCategoryController::class, 'destroy'])
            ->whereIn('type', ['major', 'class', 'series', 'asset'])
            ->name('categories.destroy');

        Route::patch('items/{item}/stock-in', [InventoryItemController::class, 'stockIn'])->name('items.stock_in');
        Route::patch('items/{item}/stock-out', [InventoryItemController::class, 'stockOut'])->name('items.stock_out');
        Route::patch('items/{item}/replenishment', [InventoryItemController::class, 'updateReplenishment'])->name('items.update_replenishment');
        Route::patch('items/{item}/restore', [InventoryItemController::class, 'restore'])->withTrashed()->name('items.restore');
        Route::resource('items', InventoryItemController::class)->only(['store', 'update', 'destroy']);

        Route::post('assets/{asset}/assign', [InventoryAssetController::class, 'assign'])->name('assets.assign');
        Route::post('assets/{asset}/events', [InventoryAssetEventController::class, 'store'])->name('assets.events.store');
        Route::post('semi-expendable-property', [InventoryAssetController::class, 'storeSemiExpendable'])->name('semi_expendable.store');
        Route::post('semi-expendable-property/{asset}/events', [SemiExpendablePropertyEventController::class, 'store'])->name('semi_expendable.events.store');
        Route::get('assets/{asset}/property-tag', [PropertyTagController::class, 'print'])->name('assets.property_tag');
        Route::post('assets/{asset}/unassign', [InventoryAssetController::class, 'unassign'])->name('assets.unassign');
        Route::patch('assets/{asset}/return', [InventoryAssetController::class, 'returnBorrowed'])->name('assets.return');
        Route::patch('assets/{asset}/state', [InventoryAssetController::class, 'updateState'])->name('assets.update_state');
        Route::patch('assets/{asset}/accounting', [InventoryAssetController::class, 'updateAccounting'])->name('assets.update_accounting');
        Route::patch('assets/{asset}/restore', [InventoryAssetController::class, 'restore'])->withTrashed()->name('assets.restore');
        Route::resource('assets', InventoryAssetController::class)->only(['store', 'update', 'destroy']);
    });
});
