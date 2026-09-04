<?php

use App\Http\Controllers\Inventory\InventoryAssetController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\InventoryReportController;
use App\Http\Controllers\Inventory\ProcurementRequestController;
use App\Http\Controllers\Inventory\PropertyAccountabilityController;
use App\Http\Controllers\Inventory\PropertyTagController;
use App\Http\Controllers\Inventory\SupplyRequestController;
use App\Services\InventoryReportService;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('categories', [InventoryCategoryController::class, 'index'])->name('categories.index');
    Route::get('items', [InventoryItemController::class, 'index'])->name('items.index');
    Route::get('items/{item}', [InventoryItemController::class, 'show'])->withTrashed()->name('items.show');
    Route::get('assets', [InventoryAssetController::class, 'index'])->name('assets.index');
    Route::get('reports', [InventoryReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [InventoryReportController::class, 'export'])->name('reports.export');
    Route::get('reports/{document}/print', [InventoryReportController::class, 'print'])
        ->whereIn('document', InventoryReportService::documentKeys())
        ->name('reports.print');
    Route::get('requests', [SupplyRequestController::class, 'index'])->name('requests.index');
    Route::get('accountability', [PropertyAccountabilityController::class, 'index'])->name('accountability.index');
    Route::patch('accountability/{document}/transition', [PropertyAccountabilityController::class, 'transition'])->name('accountability.transition');
    Route::get('accountability/{document}/print', [PropertyAccountabilityController::class, 'print'])->name('accountability.print');
    Route::post('requests', [SupplyRequestController::class, 'store'])->name('requests.store');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::post('assets/{asset}/accountability', [PropertyAccountabilityController::class, 'issue'])->name('accountability.issue');
        Route::patch('requests/{supplyRequest}/transition', [SupplyRequestController::class, 'transition'])->name('requests.transition');
        Route::get('procurement', [ProcurementRequestController::class, 'index'])->name('procurement.index');
        Route::post('procurement', [ProcurementRequestController::class, 'store'])->name('procurement.store');
        Route::patch('procurement/{procurementRequest}/transition', [ProcurementRequestController::class, 'transition'])->name('procurement.transition');
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
        Route::get('assets/{asset}/property-tag', [PropertyTagController::class, 'print'])->name('assets.property_tag');
        Route::post('assets/{asset}/unassign', [InventoryAssetController::class, 'unassign'])->name('assets.unassign');
        Route::post('assets/{asset}/borrow', [InventoryAssetController::class, 'borrow'])->name('assets.borrow');
        Route::patch('assets/{asset}/return', [InventoryAssetController::class, 'returnBorrowed'])->name('assets.return');
        Route::patch('assets/{asset}/state', [InventoryAssetController::class, 'updateState'])->name('assets.update_state');
        Route::patch('assets/{asset}/accounting', [InventoryAssetController::class, 'updateAccounting'])->name('assets.update_accounting');
        Route::patch('assets/{asset}/restore', [InventoryAssetController::class, 'restore'])->withTrashed()->name('assets.restore');
        Route::resource('assets', InventoryAssetController::class)->only(['store', 'update', 'destroy']);
    });
});
