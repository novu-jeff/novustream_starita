<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Reading;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->middleware(function ($request, $next) {
            if (Gate::allows('technician') || Gate::allows('inspector')) {
                return response()->view('others.restricted');
            }

            if (!Gate::any(['admin', 'cashier', 'superadmin'])) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });

        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $users = $this->dashboardService->getAllUsers() ?? [];

        $billedBills = Bill::query()->whereHas('reading', fn ($q) => $q->where('isReRead', false));

        $totalUnpaid = (float) (clone $billedBills)
            ->where('isPaid', false)
            ->sum(DB::raw(
                'CAST(COALESCE(previous_unpaid, 0) AS DECIMAL(15,2))'
                . ' + CAST(COALESCE(amount, 0) AS DECIMAL(15,2))'
                . ' + CAST(COALESCE(penalty, 0) AS DECIMAL(15,2))'
            ));

        $totalPaid = (float) (clone $billedBills)
            ->where('isPaid', true)
            ->sum(DB::raw('CAST(COALESCE(amount_paid, 0) AS DECIMAL(15,2))'));

        $totalPayments = (float) (clone $billedBills)
            ->sum(DB::raw('CAST(COALESCE(amount, 0) AS DECIMAL(15,2))'));

        $totalTransactionsCount = (int) (clone $billedBills)
            ->where('isPaid', true)
            ->count();

        $paymentMethodCount = (clone $billedBills)
            ->where('isPaid', true)
            ->select('payment_method', DB::raw('COUNT(*) as cnt'))
            ->groupBy('payment_method')
            ->pluck('cnt', 'payment_method')
            ->toArray();

        $data = [
            'admins' => $users['admins'] ?? 0,
            'concessionaires' => $users['concessionaires'] ?? 0,
            'technicians' => $users['technicians'] ?? 0,
            'total_readings' => Reading::query()
                ->where('isReRead', false)
                ->whereHas('bill')
                ->count(),
            'total_transactions' => $totalPaid + $totalUnpaid,
            'total_unpaid' => $totalUnpaid,
            'total_paid' => $totalPaid,
            'total_payments' => $totalPayments,
            'total_transactions_count' => $totalTransactionsCount,
            'payment_method_count' => $paymentMethodCount,
        ];

        if (Gate::any(['superadmin', 'admin', 'cashier'])) {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $monthlyRevenue = Bill::query()
                ->where('isPaid', true)
                ->whereNotNull('date_paid')
                ->where('date_paid', '>=', $startDate)
                ->whereHas('reading', fn ($q) => $q->where('isReRead', false))
                ->select(
                    DB::raw("DATE_FORMAT(date_paid, '%Y-%m') as month"),
                    DB::raw('COALESCE(SUM(CAST(amount_paid AS DECIMAL(15,2))), 0) as total')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->toArray();

            $allMonths = collect();
            for ($i = 11; $i >= 0; $i--) {
                $m = Carbon::now()->subMonths($i);
                $key = $m->format('Y-m');
                $allMonths->put($key, (float) ($monthlyRevenue[$key] ?? 0));
            }
            $data['chart_monthly_labels'] = $allMonths->keys()->map(fn ($m) => Carbon::parse($m . '-01')->format('M Y'))->values()->toArray();
            $data['chart_monthly_data'] = $allMonths->values()->toArray();

            $readingsByZone = Reading::query()
                ->where('isReRead', false)
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->select('zone', DB::raw('COUNT(*) as cnt'))
                ->groupBy('zone')
                ->orderByDesc('cnt')
                ->limit(8)
                ->pluck('cnt', 'zone')
                ->toArray();
            $data['chart_zone_labels'] = array_keys($readingsByZone);
            $data['chart_zone_data'] = array_values(array_map('intval', $readingsByZone));

            $todayPaid = Bill::query()
                ->where('isPaid', true)
                ->whereDate('date_paid', Carbon::today())
                ->sum(DB::raw('CAST(amount_paid AS DECIMAL(15,2))'));
            $todayCount = Bill::query()
                ->where('isPaid', true)
                ->whereDate('date_paid', Carbon::today())
                ->count();
            $data['today_paid'] = (float) $todayPaid;
            $data['today_count'] = (int) $todayCount;

            $data['unique_online_payments'] = Bill::query()
                ->where('isPaid', true)
                ->where('payment_method', 'online')
                ->whereHas('reading', fn ($q) => $q->where('isReRead', false))
                ->count();
            $data['concessionaire_accounts'] = \App\Models\User::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('contact_no')
                ->where('contact_no', '!=', '')
                ->count();
        }

        return view('dashboard', compact('data'));
    }
}
