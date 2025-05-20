<?php

use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\ReprintController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\PaymentController;
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

Route::fallback(function () {
    abort(404);
});

Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout']);

Route::post('transaction/callback', [CallbackController::class, 'save'])
    ->name('transaction.callback');
Route::post('payment/status/{reference_no}', [CallbackController::class, 'status'])
    ->name('transaction.status');

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

    Route::get('sync', [SyncController::class, 'sync']);

});

Route::prefix('v1')->group(function() {
    Route::post('callback/{reference_no}', [PaymentController::class, 'callback']);
});