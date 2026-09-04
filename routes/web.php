<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\PropertyTagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('welcome', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
    ]);
})->name('home');

Route::get('property-tags/{asset:property_tag_uuid}', [PropertyTagController::class, 'show'])
    ->name('property-tags.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', InventoryDashboardController::class)->name('dashboard');

    require __DIR__.'/inventory.php';

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update'])
            ->middleware('can:manage-users');
        Route::get('audit-logs', AuditLogController::class)
            ->middleware('can:view-audit-logs')
            ->name('audit-logs.index');
    });
});

require __DIR__.'/settings.php';
