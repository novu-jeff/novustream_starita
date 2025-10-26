<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\MeterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    protected $dashboardService;
    protected $meterService;

    public function __construct(DashboardService $dashboardService, MeterService $meterService)
    {
        $this->middleware(function ($request, $next) {
            if (Gate::allows('technician') || Gate::allows('inspector')) {
                return response()->view('others.restricted');
            }

            if (!Gate::allows('admin') && !Gate::allows('cashier')) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });

        $this->dashboardService = $dashboardService;
        $this->meterService = $meterService;
    }

    public function index()
    {
        $users = $this->dashboardService->getAllUsers() ?? [];
        $readings = $this->meterService->getReport() ?? collect([]);

        $total_unpaid = $readings
    ->where('bill.isPaid', false)
    ->sum(fn($r) =>
        (float)($r['bill']['previous_unpaid'] ?? 0) +
        (float)($r['bill']['amount'] ?? 0) +
        (float)($r['bill']['penalty'] ?? 0)
    );

    $total_paid = $readings
        ->where('bill.isPaid', true)
        ->sum(fn($r) => (float)($r['bill']['amount_paid'] ?? 0));

    $total_transactions = $total_paid + $total_unpaid;


        $total_payments = $readings->sum(fn($r) => (float) ($r['bill']['amount'] ?? 0));

        $total_transactions_count = $readings
            ->where('bill.isPaid', true)
            ->count();

        $payment_method_count = $readings
            ->groupBy('bill.payment_method')->map(fn ($group) => $group->count());

        $data = [
            'admins' => $users['admins'] ?? [],
            'concessionaires' => $users['concessionaires'] ?? [],
            'technicians' => $users['technicians'] ?? [],
            'total_readings' => $readings->count(),
            'total_transactions' => $total_transactions,
            'total_unpaid' => $total_unpaid,
            'total_paid' => $total_paid,
            'total_payments' => $total_payments,
            'total_transactions_count' => $total_transactions_count,
            'payment_method_count' => $payment_method_count
        ];

        return view('dashboard', compact('data'));
    }

}
