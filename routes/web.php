<?php

use App\Http\Controllers\AccountOverviewController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PaymentBreakdownController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyTypesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BaseRateController;
use App\Http\Controllers\RatesController;
use App\Http\Controllers\ReadingController;
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

Route::get('/login', [LoginController::class, 'index'])
    ->name('auth.index');

Route::post('/login', [LoginController::class, 'login'])
    ->name('auth.login');

Route::get('/login', [LoginController::class, 'index'])
    ->name('auth.register');

Route::any('/logout', [LoginController::class, 'logout'])
    ->name('auth.logout');

Route::middleware('admin')->prefix('admin')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('reading', [ReadingController::class, 'index'])
        ->name('reading.index');

    Route::post('reading', [ReadingController::class, 'store'])
        ->name('reading.store');

    Route::get('reading/view/bill/{reference_no}', [ReadingController::class, 'view_bill'])
        ->name('reading.view-bill');

    Route::get('reading/bill/{reference_no}', [ReadingController::class, 'show'])
        ->name('reading.show');

    Route::get('reading/reports/{date?}', [ReadingController::class, 'report'])
        ->name('reading.report');

    Route::prefix('users')->group(function() {

        Route::resource('roles', RoleController::class)
            ->names('roles')
            ->only('index', 'destroy');

        Route::resource('clients', ClientController::class)
            ->names('clients')
            ->except('show');

        Route::get('clients/import', [ClientController::class, 'import_view'])
            ->name('clients.import.view');
        Route::post('clients/import', [ClientController::class, 'import_action'])
            ->name('clients.import.action');

        Route::resource('personnel', AdminController::class)
            ->names('admins');
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
            
        Route::resource('rates', RatesController::class)
            ->names('rates')->only('index', 'store');
        
        Route::resource('base-rate', BaseRateController::class)
            ->names('base-rate')->only('index', 'store');

        Route::resource('payment-breakdown', PaymentBreakdownController::class)
            ->names('payment-breakdown');
    });

    Route::resource('profile', ProfileController::class)
        ->names('admin.profile');

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
                ->name('admin.support-ticket.create');
            Route::post('/', [SupportTicketController::class, 'store'])
                ->name('admin.support-ticket.store');
            Route::get('/{ticket}', [SupportTicketController::class, 'show'])
                ->name('admin.support-ticket.show');
            Route::delete('/{ticket}', [SupportTicketController::class, 'destroy'])
                ->name('admin.support-ticket.destroy');
            Route::get('/edit/{ticket}', [SupportTicketController::class, 'edit'])
                ->name('admin.support-ticket.edit');
            Route::put('/edit/{ticket}', [SupportTicketController::class, 'update'])
                ->name('admin.support-ticket.update');
        });
    });

});

Route::middleware('auth')->prefix('client')->group(function() {
    Route::prefix('my')->group(function() {
        Route::get('overview', [AccountOverviewController::class, 'index'])
            ->name('account-overview.index');
        Route::get('bills', [AccountOverviewController::class, 'show'])
            ->name('account-overview.show');
    });

    Route::resource('profile', ProfileController::class)
        ->names('client.profile');

    Route::prefix('/support')->group(function() {
        Route::prefix('/ticket/submit')->group(function() {
            Route::get('/', [SupportTicketController::class, 'create'])
                ->name('client.support-ticket.create');
            Route::post('/', [SupportTicketController::class, 'store'])
                ->name('client.support-ticket.store');
            Route::get('/{ticket}', [SupportTicketController::class, 'show'])
                ->name('client.support-ticket.show');
            Route::delete('/{ticket}', [SupportTicketController::class, 'destroy'])
                ->name('client.support-ticket.destroy');
            Route::get('/edit/{ticket}', [SupportTicketController::class, 'edit'])
                ->name('client.support-ticket.edit');
            Route::put('/edit/{ticket}', [SupportTicketController::class, 'update'])
                ->name('client.support-ticket.update');
        });
    });
});