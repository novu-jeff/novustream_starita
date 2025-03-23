<?php

use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\ReprintController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('inspection')->group(function () {
        Route::post('search', [InspectionController::class, 'search']);
        Route::post('update', [InspectionController::class, 'update']);
    });

    Route::prefix('meter')->group(function () {
        Route::post('search', [MeterController::class, 'search']);
        Route::post('reading', [MeterController::class, 'reading']);
    });

    Route::prefix('reprint')->group(function () {
        Route::post('search', [ReprintController::class, 'search']);
        Route::post('search/{reference_no}', [ReprintController::class, 'view']);
    });
});