<?php

use App\Http\Controllers\AccountOverviewController;
use App\Http\Controllers\ConcessionaireController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PaymentBreakdownController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\HitpayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyTypesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BaseRateController;
use App\Http\Controllers\RatesController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\OfflineDataController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\NovupaySyncController;
use App\Http\Controllers\PenaltyExemptionController;
use App\Http\Controllers\ReadingDateController;

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

Route::get('/login', [LoginController::class, 'index']);

Route::any('/logout', [LoginController::class, 'logout'])
    ->name('auth.logout');

// Show register page
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
    ->name('register');

// Handle register form
Route::post('/register', [RegisterController::class, 'register'])
    ->name('auth.register.store');


Route::middleware('auth:admins')->prefix('admin')->group(function () {

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

    Route::get('reading/invoice/{reference_no}', [ReadingController::class, 'invoice'])
        ->name('reading.invoice');

    Route::get('/reading/or-walkin/{reference_no}', [ReadingController::class, 'orWalkinShow'])
    ->name('reading.or.walkin');

    Route::get('/reading/or/{reference_no}', [ReadingController::class, 'orShow'])
        ->name('reading.orshow');

    Route::get('/reports/download', [ReportsController::class, 'downloadSummary'])
    ->name('reports.download');

    Route::get('reading/reports', [ReadingController::class, 'report'])
        ->name('reading.report');

    Route::get('/reports/download', [ReportsController::class, 'filterAndDownload'])
        ->name('reports.download');

    Route::get('/reports/download', [ReportsController::class, 'downloadSummary'])
        ->name('reports.download');

    Route::get('/reports/downloadable-files', [ReportsController::class, 'downloadFilesIndex'])
        ->name('reports.download-index');

    Route::get('/reports/download/ageing/detailed', [ReportsController::class, 'downloadAgeingDetailed'])
        ->name('reports.download.ageing.detailed');

    Route::get('/reports/download/penalty/detailed', [ReportsController::class, 'downloadPenaltyDetailed'])
        ->name('reports.download.penalty.detailed');

    Route::get('/reports/download/franchise-tax/detailed', [ReportsController::class, 'downloadFranchiseTaxDetailed'])
        ->name('reports.download.franchise-tax.detailed');

    Route::get('/reports/download/disconnected-concessionaires', [ReportsController::class, 'downloadDisconnectedConcessionaires'])
        ->name('reports.download.disconnected-concessionaires');

    Route::get('/reports/download/ageing/summary', [ReportsController::class, 'downloadAgeingSummary'])
        ->name('reports.download.ageing.summary');

    Route::get('/reports/download/penalty/summary', [ReportsController::class, 'downloadPenaltySummary'])
        ->name('reports.download.penalty.summary');

    Route::get('/reports/download-options', [ReportsController::class, 'downloadFilesIndex'])->name('reports.download.index');
    Route::post('/reports/download-generate', [ReportsController::class, 'generateFile'])->name('reports.download.generate');

    Route::resource('penalty-exemption', PenaltyExemptionController::class)
    ->except(['create', 'edit', 'show']);

    Route::resource('reading-dates', ReadingDateController::class)
    ->except(['create', 'show']);

    Route::prefix('users')->group(function() {

        Route::resource('roles', RoleController::class)
            ->names('roles')
            ->only('index', 'destroy');

        Route::resource('concessionaires', ConcessionaireController::class)
            ->names('concessionaires')
            ->except('show');

        Route::resource('personnel', AdminController::class)
            ->names('admins');
    });

    Route::any('import', [ImportController::class, 'index'])
        ->name('import');

    Route::prefix('payments')->group(function() {
        Route::get('', [PaymentController::class, 'index'])
            ->name('payments.index');
        Route::any('previous-billing', [PaymentController::class, 'upload'])
            ->name('previous-billing.upload');
        Route::match(['get', 'post'], 'process/{reference_no}', [PaymentController::class, 'pay'])
            ->name('payments.pay');
        Route::post('/account-overview/pay/{reference_no}', [AccountOverviewController::class, 'payOnline'])
            ->name('account-overview.pay-online');


    Route::post('/payments/{reference_no}/apply-discount', [PaymentController::class, 'applyDiscount'])
        ->name('payments.applyDiscount');

    });

    Route::prefix('settings')->group(function() {
        Route::resource('property-types', PropertyTypesController::class)
            ->names('property-types');

        Route::resource('rates', RatesController::class)
            ->names('rates')->only('index', 'create', 'update', 'store');

        Route::put('update-rates', [RatesController::class, 'updateBulkRate'])->name('bulk-rates.update');

        Route::resource('base-rate', BaseRateController::class)
            ->names('base-rate')->only('index', 'store');

        Route::resource('payment-breakdown', PaymentBreakdownController::class)
            ->names('payment-breakdown');

        Route::get('online-payments', [NovupaySyncController::class, 'index'])
            ->name('online-payments.index');
    });


    // web.php
    Route::middleware(['auth'])->group(function () {

        Route::get('/concessionaires/ledger',
            [LedgerController::class, 'index']
        )->name('ledger.index');

        Route::get('/concessionaires/{concessionaire}/ledger',
            [LedgerController::class, 'show']
        )->name('concessionaires.ledger');

    });

    Route::get('/transactions', [ConcessionaireController::class, 'index'])
        ->name('transactions');

    Route::get('/reports', [ConcessionaireController::class, 'index'])
        ->name('reports');

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

    Route::get('/payments-ready-to-sync', [NovupaySyncController::class, 'paymentsReadyToSync'])
        ->name('admin.payments-ready-to-sync');
    Route::get('/recent-synced-payments', [NovupaySyncController::class, 'recentSyncedPayments'])
        ->name('admin.recent-synced-payments');
    Route::post('/sync-online-payments', [NovupaySyncController::class, 'syncOnlinePayments'])
        ->name('admin.sync-online-payments');

    Route::get('/sync-novupay-to-starita', function () {
        try {
            Artisan::call('novupay:sync-readings', ['--limit' => 500]);
            $syncOutput = Artisan::output();
            Artisan::call('readings:merge', ['--limit' => 500]);
            $mergeOutput = Artisan::output();
            return redirect()
                ->back()
                ->with('status', 'Novupay sync to Sta-Rita completed. ' . trim($syncOutput) . ' | Merge: ' . trim($mergeOutput));
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    })->name('admin.sync-novupay-to-starita');

});

Route::middleware('auth')->prefix('concessionaire')->group(function() {
    Route::prefix('my')->group(function() {
        Route::get('overview', [AccountOverviewController::class, 'index'])
            ->name('account-overview.index');
        Route::get('bills', [AccountOverviewController::class, 'bills'])
            ->name('account-overview.bills');
        Route::get('bills/{reference_no?}', [AccountOverviewController::class, 'bills'])
            ->name('account-overview.bills.reference_no');
    });

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

Route::resource('/{user_type}/profile', ProfileController::class)
        ->names('profile');

Route::middleware(['auth', 'check.default.password'])->prefix('concessionaire')->group(function() {
    Route::get('my/overview', [AccountOverviewController::class, 'index'])
        ->name('account-overview.index');
});

Route::delete('reading-dates/destroy-all', [ReadingDateController::class, 'destroyAll'])
->name('reading-dates.destroy-all');

Route::post('/payments/hitpay/create', [PaymentController::class, 'createHitPayPayment'])->name('payments.hitpay.create');
Route::get('/payments/hitpay/callback', [PaymentController::class, 'hitpayCallback'])->name('payments.hitpay.callback');
Route::post('/payments/hitpay/webhook', [PaymentController::class, 'hitpayWebhook'])->name('payments.hitpay.webhook');
Route::get('/payments/redirect', [PaymentController::class, 'handleRedirect'])->name('payments.redirect');


// Route::get('/payments/redirect', [HitpayController::class, 'redirect'])->name('hitpay.redirect');

// Offline Sync Routes

Route::post('/readings', [OfflineSyncController::class, 'store']);
Route::post('/readings/sync', [OfflineSyncController::class, 'sync']);

Route::get('/admin/offline/download', [OfflineDataController::class, 'download'])
    ->name('offline.download')
    ->middleware('auth');


Route::get('/payment/test-status', function () {
    return view('payments.status', [
        'payload' => [
            'title' => 'Test Payment',
            'message' => 'This is a sample payment status screen.',
            'amount' => '123.45',
            'reference_no' => 'NOV-PELCO-TEST',
            'status' => 'paid',
            'date_paid' => now()->format('M d, Y h:i A'),
            'payment_id' => 'PAY-TEST-001'
        ]
    ]);
});


Route::get('/offline/download', [OfflineDataController::class, 'download']);
