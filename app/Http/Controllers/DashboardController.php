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

        $total_transactions = $readings->sum(fn($reading) => $reading['bill']['amount'] ?? 0);
        $total_unpaid = $readings->where('bill.isPaid', false)->sum('bill.amount');
        $total_paid = $readings->where('bill.isPaid', true)->sum('bill.amount');
        $total_payments = $readings->sum('bill.amount');

        $data = [
            'admins' => $users['admins'],
            'concessionaires' => $users['concessionaires'],
            'technicians' => $users['technicians'],
            'total_readings' => $readings->count(),
            'total_transactions' => $total_transactions,
            'total_unpaid' => $total_unpaid,
            'total_paid' => $total_paid,
            'total_payments' => $total_payments,
        ];

        return view('dashboard', compact('data'));
    }
}
