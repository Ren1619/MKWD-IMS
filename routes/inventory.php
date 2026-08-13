<?php

use App\Http\Controllers\Inventory\InventoryAssetController;
use App\Http\Controllers\Inventory\InventoryCategoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('categories', [InventoryCategoryController::class, 'index'])->name('categories.index');
    Route::get('items', [InventoryItemController::class, 'index'])->name('items.index');
    Route::get('assets', [InventoryAssetController::class, 'index'])->name('assets.index');

    Route::middleware('can:manage-inventory')->group(function () {
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
        Route::resource('items', InventoryItemController::class)->only(['store', 'update', 'destroy']);

        Route::post('assets/{asset}/assign', [InventoryAssetController::class, 'assign'])->name('assets.assign');
        Route::post('assets/{asset}/unassign', [InventoryAssetController::class, 'unassign'])->name('assets.unassign');
        Route::post('assets/{asset}/borrow', [InventoryAssetController::class, 'borrow'])->name('assets.borrow');
        Route::patch('assets/{asset}/return', [InventoryAssetController::class, 'returnBorrowed'])->name('assets.return');
        Route::resource('assets', InventoryAssetController::class)->only(['store', 'update', 'destroy']);
    });
});
