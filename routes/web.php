<?php

use App\Http\Controllers\AccountOverviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentBreakdownController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyTypesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaterRatesController;
use App\Http\Controllers\WaterReadingController;
use App\Models\WaterReading;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->to('/login');
});

Auth::routes();

Route::any('/logut', [LoginController::class, 'logout']);

Route::middleware('auth')->group(function() {

    Route::prefix('my')->group(function() {
        Route::get('overview', [AccountOverviewController::class, 'index'])
            ->name('account-overview.index');
        Route::get('bills', [AccountOverviewController::class, 'show'])
            ->name('account-overview.show');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('water-reading', [WaterReadingController::class, 'index'])
        ->name('water-reading.index');

    Route::post('water-reading', [WaterReadingController::class, 'store'])
        ->name('water-reading.store');

    Route::get('water-reading/view/bill/{reference_no}', [WaterReadingController::class, 'view_bill'])
        ->name('water-reading.view-bill');

    Route::get('water-reading/bill/{reference_no}', [WaterReadingController::class, 'show'])
        ->name('water-reading.show');

    Route::get('water-reading/reports/{date?}', [WaterReadingController::class, 'report'])
        ->name('water-reading.report');

    Route::prefix('users')->group(function() {

        Route::resource('roles', RoleController::class)
            ->names('roles')
            ->only('index', 'destroy');

        Route::resource('clients', ClientController::class)
            ->names('clients');

        Route::resource('personnel', UserController::class)
            ->names('users');
    });

    Route::prefix('payments')->group(function() {
        Route::get('', [PaymentController::class, 'index'])
            ->name('payments.index');
        Route::get('{payment}', [PaymentController::class, 'show'])
            ->name('payments.show');
        Route::get('process/{reference_no}', [PaymentController::class, 'pay'])
            ->name('payments.pay');
        Route::post('process/{reference_no}', [PaymentController::class, 'pay'])
            ->name('payments.pay');
    });

    Route::prefix('settings')->group(function() {
        Route::resource('property-types', PropertyTypesController::class)
            ->names('property-types');

        Route::resource('water-rates', WaterRatesController::class)
            ->names('water-rates');

        Route::resource('payment-breakdown', PaymentBreakdownController::class)
            ->names('payment-breakdown');
    });

    Route::resource('profile', ProfileController::class)
        ->names('profile');

    Route::get('/transactions', [ClientController::class, 'index'])
        ->name('transactions');

    Route::get('/reports', [ClientController::class, 'index'])
        ->name('reports');


    // Route::prefix('/support')->group(function() {
    //     Route::prefix('/ticket')->group(function() {
    //         Route::get('/', [SupportTicketController::class, 'index'])
    //             ->name('support-ticket.index');
    //         Route::get('/{ticket}', [SupportTicketController::class, 'show'])
    //             ->name('support-ticket.show');
    //         Route::get('/edit/{ticket}', [SupportTicketController::class, 'edit'])
    //             ->name('support-ticket.edit');
    //         Route::put('/edit/{ticket}', [SupportTicketController::class, 'update'])
    //             ->name('support-ticket.update');
    //     });
    // });

    Route::prefix('/support')->group(function() {
        Route::prefix('/ticket/submit')->group(function() {
            Route::get('/', [SupportTicketController::class, 'create'])
                ->name('support-ticket.create');
            Route::post('/', [SupportTicketController::class, 'store'])
                ->name('support-ticket.store');
            Route::get('/{ticket}', [SupportTicketController::class, 'show'])
                ->name('support-ticket.show');
            Route::delete('/{ticket}', [SupportTicketController::class, 'destroy'])
                ->name('support-ticket.destroy');
            Route::get('/edit/{ticket}', [SupportTicketController::class, 'edit'])
                ->name('support-ticket.edit');
            Route::put('/edit/{ticket}', [SupportTicketController::class, 'update'])
                ->name('support-ticket.update');
        });
    });

});