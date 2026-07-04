<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParcelController;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/parcels/info', [ParcelController::class, 'info']);
    Route::get('/parcels/in-bounds', [ParcelController::class, 'inBounds']);
});
