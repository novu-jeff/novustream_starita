<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\WaterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{

    public $dashboardService;
    public $waterService;

    public function __construct(DashboardService $dashboardService, WaterService $waterService) {
        
        $this->middleware(function ($request, $next) {
    
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });
        
        $this->dashboardService = $dashboardService;
        $this->waterService = $waterService;
    }

    public function index()
    {

        $users = $this->dashboardService::getAllUsers()->toArray() ?? [];
        $water_readings = $this->waterService::getReport() ?? collect([]); // Ensure it's a collection

        // Sum up all bill amounts safely
        $total_transactions = $water_readings->sum(function ($reading) {
            return $reading['bill']['amount'] ?? 0;
        });

        // Get total unpaid amount (where isPaid is false)
        $total_unpaid = $water_readings->where('bill.isPaid', false)->sum('bill.amount');

        // Get total paid amount (where isPaid is true)
        $total_paid = $water_readings->where('bill.isPaid', true)->sum('bill.amount');

        // Get total payment transactions
        $total_payments = $water_readings->sum('bill.amount');

        $data = [
            'users' => $users,
            'total_readings' => $water_readings->count(),
            'total_transactions' => $total_transactions,
            'total_unpaid' => $total_unpaid,
            'total_paid' => $total_paid,
            'total_payments' => $total_payments
        ];

        return view('dashboard', compact('data'));
    }
}
