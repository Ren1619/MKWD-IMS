<?php

use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('vehicles', VehicleController::class)
    ->middleware([
        'throttle:60,1',
        'auth:sanctum',
        'can:access-vehicle-api',
        'abilities:vehicles:read',
    ])
    ->name('api.vehicles.index');
