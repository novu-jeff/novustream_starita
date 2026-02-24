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
use App\Http\Controllers\OfflineDataController;
use App\Http\Controllers\OfflineSyncController;

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
Route::post('auth/login', [LoginController::class, 'login']); // Flutter app: POST api/auth/login
Route::post('api/login', [LoginController::class, 'login']); // allow /api/api/login when app base URL ends with /api
Route::post('logout', [LoginController::class, 'logout']);
Route::post('api/logout', [LoginController::class, 'logout']);

// Flutter app: GET /api/app-version (set baseUrl / appVersionUrl in lib/config/merchant_config.dart)
Route::get('app-version', function () {
    return response()->json([
        'version'      => env('APP_VERSION', '1.0.0'),
        'build_number' => (int) env('APP_BUILD_NUMBER', 1),
        'apk_url'      => env('APK_URL', ''),
    ]);
});

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

// Mobile app (novustream_offline_starita): auth via local token only. Single route for sync accepts GET and POST.
Route::middleware('auth.offline')->group(function () {
    Route::post('/readings/sync', [OfflineSyncController::class, 'sync']);
    Route::post('/readings', [OfflineSyncController::class, 'store']);
    Route::post('/readings/merge', [OfflineSyncController::class, 'merge']);
    // One route for both GET (?readings=...) and POST (body { "readings": [...] }) so cache/deploy always has it
    Route::match(['get', 'post'], '/offline/sync', [OfflineSyncController::class, 'sync']);
    Route::post('/offline/store', [OfflineSyncController::class, 'store']);
    Route::post('/offline/merge', [OfflineSyncController::class, 'merge']);
    Route::get('/offline/download', [OfflineDataController::class, 'download']);
});

// Fallback last so it does not catch POST /offline/sync, /readings/sync, etc.
Route::fallback(function () {
    abort(404);
});