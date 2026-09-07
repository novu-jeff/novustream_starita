<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\User;
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
        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME=15000');
        } catch (\Throwable $e) {
            // Ignore if the session variable is unavailable.
        }

        $users = $this->dashboardService->getAllUsers() ?? [];

        $totals = DB::table('bill')
            ->join('readings', 'bill.reading_id', '=', 'readings.id')
            ->where('readings.isReRead', 0)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN bill.isPaid = 0 THEN
                    CAST(COALESCE(bill.previous_unpaid, 0) AS DECIMAL(15,2))
                    + CAST(COALESCE(bill.amount, 0) AS DECIMAL(15,2))
                    + CAST(COALESCE(bill.penalty, 0) AS DECIMAL(15,2))
                ELSE 0 END), 0) AS total_unpaid,
                COALESCE(SUM(CASE WHEN bill.isPaid = 1 THEN
                    CAST(COALESCE(bill.amount_paid, 0) AS DECIMAL(15,2))
                ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CAST(COALESCE(bill.amount, 0) AS DECIMAL(15,2))), 0) AS total_payments,
                SUM(CASE WHEN bill.isPaid = 1 THEN 1 ELSE 0 END) AS total_transactions_count,
                COUNT(*) AS total_readings
            ")
            ->first();

        $paymentMethodCount = DB::table('bill')
            ->join('readings', 'bill.reading_id', '=', 'readings.id')
            ->where('readings.isReRead', 0)
            ->where('bill.isPaid', 1)
            ->groupBy('bill.payment_method')
            ->select('bill.payment_method', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'payment_method')
            ->toArray();

        $totalUnpaid = (float) ($totals->total_unpaid ?? 0);
        $totalPaid = (float) ($totals->total_paid ?? 0);

        $data = [
            'admins' => $users['admins'] ?? 0,
            'concessionaires' => $users['concessionaires'] ?? 0,
            'technicians' => $users['technicians'] ?? 0,
            'total_readings' => (int) ($totals->total_readings ?? 0),
            'total_transactions' => $totalPaid + $totalUnpaid,
            'total_unpaid' => $totalUnpaid,
            'total_paid' => $totalPaid,
            'total_payments' => (float) ($totals->total_payments ?? 0),
            'total_transactions_count' => (int) ($totals->total_transactions_count ?? 0),
            'payment_method_count' => $paymentMethodCount,
        ];

        if (Gate::any(['superadmin', 'admin', 'cashier'])) {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $monthlyRevenue = DB::table('bill')
                ->join('readings', 'bill.reading_id', '=', 'readings.id')
                ->where('readings.isReRead', 0)
                ->where('bill.isPaid', 1)
                ->whereNotNull('bill.date_paid')
                ->where('bill.date_paid', '!=', '')
                ->where('bill.date_paid', '>=', $startDate)
                ->selectRaw("LEFT(bill.date_paid, 7) as month, COALESCE(SUM(CAST(bill.amount_paid AS DECIMAL(15,2))), 0) as total")
                ->groupByRaw("LEFT(bill.date_paid, 7)")
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

            $now = Carbon::now();
            $monthStart = $now->copy()->startOfMonth()->toDateTimeString();
            $monthEnd = $now->copy()->endOfMonth()->toDateTimeString();
            $readingsByZone = Reading::query()
                ->where('isReRead', false)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->select('zone', DB::raw('COUNT(*) as cnt'))
                ->groupBy('zone')
                ->orderByDesc('cnt')
                ->limit(8)
                ->pluck('cnt', 'zone')
                ->toArray();
            $data['chart_zone_labels'] = array_keys($readingsByZone);
            $data['chart_zone_data'] = array_values(array_map('intval', $readingsByZone));

            $todayStart = Carbon::today()->toDateString();
            $tomorrowStart = Carbon::tomorrow()->toDateString();
            $todayStats = DB::table('bill')
                ->where('isPaid', 1)
                ->whereNotNull('date_paid')
                ->where('date_paid', '>=', $todayStart)
                ->where('date_paid', '<', $tomorrowStart)
                ->selectRaw('COALESCE(SUM(CAST(amount_paid AS DECIMAL(15,2))), 0) as today_paid, COUNT(*) as today_count')
                ->first();
            $data['today_paid'] = (float) ($todayStats->today_paid ?? 0);
            $data['today_count'] = (int) ($todayStats->today_count ?? 0);

            $data['unique_online_payments'] = (int) ($paymentMethodCount['online'] ?? 0);
            $data['concessionaire_accounts'] = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('contact_no')
                ->where('contact_no', '!=', '')
                ->count();
        }

        return view('dashboard', compact('data'));
    }
}
