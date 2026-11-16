<?php

use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\GateController;
use Illuminate\Support\Facades\Route;

// Gate devices authenticate with a Sanctum token scoped to one event.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('gate', [GateController::class, 'show']);
    Route::post('checkins', [CheckInController::class, 'store'])->middleware('throttle:checkins');
});
