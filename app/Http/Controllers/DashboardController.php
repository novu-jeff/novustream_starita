<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Reading;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            if (!Gate::any(['admin', 'cashier', 'superadmin'])) {
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

        $flatReadings = collect($readings)->flatten(1);
        $total_unpaid = $flatReadings
            ->where('bill.isPaid', false)
            ->sum(fn ($r) =>
                (float) ($r['bill']['previous_unpaid'] ?? 0) +
                (float) ($r['bill']['amount'] ?? 0) +
                (float) ($r['bill']['penalty'] ?? 0)
            );

        $total_paid = $flatReadings
            ->where('bill.isPaid', true)
            ->sum(fn ($r) => (float) ($r['bill']['amount_paid'] ?? 0));

        $total_transactions = $total_paid + $total_unpaid;
        $total_payments = $flatReadings->sum(fn ($r) => (float) ($r['bill']['amount'] ?? 0));
        $total_transactions_count = $flatReadings->where('bill.isPaid', true)->count();

        $payment_method_count = $flatReadings
            ->where('bill.isPaid', true)
            ->groupBy('bill.payment_method')
            ->map(fn ($group) => $group->count());

        $data = [
            'admins' => $users['admins'] ?? 0,
            'concessionaires' => $users['concessionaires'] ?? 0,
            'technicians' => $users['technicians'] ?? 0,
            'total_readings' => $flatReadings->count(),
            'total_transactions' => $total_transactions,
            'total_unpaid' => $total_unpaid,
            'total_paid' => $total_paid,
            'total_payments' => $total_payments,
            'total_transactions_count' => $total_transactions_count,
            'payment_method_count' => $payment_method_count,
        ];

        if (Gate::allows('superadmin')) {
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
