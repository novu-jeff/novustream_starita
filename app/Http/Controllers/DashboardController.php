<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\User;
use App\Models\Zone;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME=15000');
        } catch (\Throwable $e) {
            // Ignore if the session variable is unavailable.
        }

        $filters = $this->dashboardFilters($request);
        $zones = Zone::query()->orderBy('zone')->get(['zone', 'area']);
        $users = $this->dashboardService->getAllUsers() ?? [];
        $hasDate = $filters['date_from'] !== '' || $filters['date_to'] !== '';

        $paidQuery = $this->paidBillQuery($filters, $hasDate);
        $paid = (clone $paidQuery)
            ->selectRaw('COALESCE(SUM(CAST(bill.amount_paid AS DECIMAL(15,2))), 0) as total_paid, COUNT(*) as total_transactions_count')
            ->first();

        $unpaidQuery = $this->baseBillQuery($filters)->where('bill.isPaid', 0);
        if ($hasDate) {
            $this->applyReadingWindow($unpaidQuery, $filters);
        }
        $unpaid = $unpaidQuery
            ->selectRaw("
                COALESCE(SUM(
                    CAST(COALESCE(bill.previous_unpaid, 0) AS DECIMAL(15,2))
                    + CAST(COALESCE(bill.amount, 0) AS DECIMAL(15,2))
                    + CAST(COALESCE(bill.penalty, 0) AS DECIMAL(15,2))
                ), 0) AS total_unpaid
            ")
            ->first();

        $readingsQuery = $this->baseBillQuery($filters);
        if ($hasDate) {
            $this->applyReadingWindow($readingsQuery, $filters);
        }

        $paymentMethodCount = (clone $paidQuery)
            ->groupBy('bill.payment_method')
            ->select('bill.payment_method', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'payment_method')
            ->toArray();

        $totalUnpaid = (float) ($unpaid->total_unpaid ?? 0);
        $totalPaid = (float) ($paid->total_paid ?? 0);
        $scope = $this->scopeLabel($filters);

        $data = [
            'admins' => $users['admins'] ?? 0,
            'concessionaires' => $users['concessionaires'] ?? 0,
            'technicians' => $users['technicians'] ?? 0,
            'total_readings' => (int) (clone $readingsQuery)->count(),
            'total_transactions' => $totalPaid + $totalUnpaid,
            'total_unpaid' => $totalUnpaid,
            'total_paid' => $totalPaid,
            'total_payments' => $totalPaid,
            'total_transactions_count' => (int) ($paid->total_transactions_count ?? 0),
            'payment_method_count' => $paymentMethodCount,
            'paid_sub' => $scope,
            'unpaid_sub' => $hasDate || $filters['zone'] !== ''
                ? trim(($hasDate ? 'By reading date' : 'Open bills').($filters['zone'] !== '' ? ' · Zone '.$filters['zone'] : ''))
                : '',
            'readings_sub' => $hasDate || $filters['zone'] !== ''
                ? trim(($hasDate ? 'By reading date' : 'All readings').($filters['zone'] !== '' ? ' · Zone '.$filters['zone'] : ''))
                : '',
            'collection_label' => $hasDate ? 'Collection' : "Today's Collection",
        ];

        if (Gate::any(['superadmin', 'admin', 'cashier'])) {
            $revenueRange = $this->resolveRange(
                $filters,
                Carbon::now()->subMonths(11)->startOfMonth(),
                Carbon::now()->endOfMonth()
            );
            $revenueDaily = $revenueRange['custom'] && $revenueRange['start']->diffInDays($revenueRange['end']) <= 62;
            $revenueStart = $revenueRange['start']->copy();
            $revenueSubtitle = $this->rangeSubtitle($revenueRange, 'Last 12 months');
            if (!$revenueDaily && $revenueStart->diffInMonths($revenueRange['end']) > 36) {
                $revenueStart = $revenueRange['end']->copy()->subMonths(35)->startOfMonth();
                $revenueSubtitle .= ' (last 36 months shown)';
            }

            $revenueQuery = $this->paidBillQuery($filters, false)
                ->where('bill.date_paid', '>=', $revenueStart->toDateString())
                ->where('bill.date_paid', '<=', $revenueRange['end']->toDateTimeString());
            if ($revenueDaily) {
                $revenueRows = $revenueQuery
                    ->selectRaw("DATE(bill.date_paid) as bucket, COALESCE(SUM(CAST(bill.amount_paid AS DECIMAL(15,2))), 0) as total")
                    ->groupByRaw('DATE(bill.date_paid)')
                    ->pluck('total', 'bucket')
                    ->toArray();
            } else {
                $revenueRows = $revenueQuery
                    ->selectRaw("LEFT(bill.date_paid, 7) as bucket, COALESCE(SUM(CAST(bill.amount_paid AS DECIMAL(15,2))), 0) as total")
                    ->groupByRaw('LEFT(bill.date_paid, 7)')
                    ->pluck('total', 'bucket')
                    ->toArray();
            }
            [$data['chart_monthly_labels'], $data['chart_monthly_data']] = $this->fillBuckets(
                $revenueStart,
                $revenueRange['end'],
                $revenueRows,
                $revenueDaily
            );
            $data['revenue_sub'] = $revenueSubtitle.$this->scopeSuffix($filters, true);

            $zoneRange = $this->resolveRange(
                $filters,
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            );
            $zoneSubtitle = $this->rangeSubtitle($zoneRange, 'This month');
            $zoneQuery = Reading::query()
                ->where('isReRead', false)
                ->where('created_at', '>=', $zoneRange['start']->toDateTimeString())
                ->where('created_at', '<=', $zoneRange['end']->toDateTimeString());
            $this->applyZone($zoneQuery, $filters, 'zone');

            if ($filters['zone'] !== '') {
                $zoneDaily = $zoneRange['start']->diffInDays($zoneRange['end']) <= 62;
                if ($zoneDaily) {
                    $zoneRows = (clone $zoneQuery)
                        ->selectRaw('DATE(created_at) as bucket, COUNT(*) as cnt')
                        ->groupByRaw('DATE(created_at)')
                        ->pluck('cnt', 'bucket')
                        ->toArray();
                } else {
                    $zoneRows = (clone $zoneQuery)
                        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, COUNT(*) as cnt")
                        ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                        ->pluck('cnt', 'bucket')
                        ->toArray();
                }
                [$data['chart_zone_labels'], $data['chart_zone_data']] = $this->fillBuckets(
                    $zoneRange['start'],
                    $zoneRange['end'],
                    $zoneRows,
                    $zoneDaily
                );
                $data['chart_zone_data'] = array_map('intval', $data['chart_zone_data']);
                $data['chart_zone_horizontal'] = false;
                $data['zone_title'] = 'Readings over time';
                $data['zone_sub'] = 'Zone '.$filters['zone'].' · '.$zoneSubtitle;
            } else {
                $readingsByZone = $zoneQuery
                    ->select('zone', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('zone')
                    ->orderByDesc('cnt')
                    ->limit(8)
                    ->pluck('cnt', 'zone')
                    ->toArray();
                $data['chart_zone_labels'] = array_keys($readingsByZone);
                $data['chart_zone_data'] = array_values(array_map('intval', $readingsByZone));
                $data['chart_zone_horizontal'] = true;
                $data['zone_title'] = 'Readings by Zone';
                $data['zone_sub'] = $zoneSubtitle;
            }

            $collectionQuery = $this->paidBillQuery($filters, $hasDate);
            if (!$hasDate) {
                $collectionQuery
                    ->where('bill.date_paid', '>=', Carbon::today()->toDateString())
                    ->where('bill.date_paid', '<', Carbon::tomorrow()->toDateString());
            }
            $collection = $collectionQuery
                ->selectRaw('COALESCE(SUM(CAST(bill.amount_paid AS DECIMAL(15,2))), 0) as today_paid, COUNT(*) as today_count')
                ->first();
            $data['today_paid'] = (float) ($collection->today_paid ?? 0);
            $data['today_count'] = (int) ($collection->today_count ?? 0);
            $data['collection_meta'] = number_format($data['today_count']).' transactions'
                .($scope !== '' ? ' · '.$scope : '');

            $data['method_sub'] = ($hasDate ? $this->rangeSubtitle($revenueRange, 'Cash vs Online') : 'Cash vs Online').$this->scopeSuffix($filters, true);
            $data['unique_online_payments'] = (int) ($paymentMethodCount['online'] ?? 0);
            $data['concessionaire_accounts'] = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('contact_no')
                ->where('contact_no', '!=', '')
                ->count();
        }

        return view('dashboard', compact('data', 'filters', 'zones'));
    }

    private function dashboardFilters(Request $request): array
    {
        $dateFrom = $this->validDate($request->input('date_from'));
        $dateTo = $this->validDate($request->input('date_to'));
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $zone = trim((string) $request->input('zone', ''));
        if ($zone !== '' && !Zone::query()->where('zone', $zone)->exists()) {
            $zone = '';
        }

        $method = strtolower(trim((string) $request->input('payment_method', '')));
        if (!in_array($method, ['cash', 'online'], true)) {
            $method = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'zone' => $zone,
            'payment_method' => $method,
        ];
    }

    private function validDate($value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable $e) {
            return '';
        }

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function baseBillQuery(array $filters)
    {
        $query = DB::table('bill')
            ->join('readings', 'bill.reading_id', '=', 'readings.id')
            ->where('readings.isReRead', 0);

        $this->applyZone($query, $filters, 'readings.zone');

        return $query;
    }

    private function paidBillQuery(array $filters, bool $applyDates)
    {
        $query = $this->baseBillQuery($filters)->where('bill.isPaid', 1);

        if ($applyDates) {
            $query->whereNotNull('bill.date_paid')->where('bill.date_paid', '!=', '');
            $this->applyPaidWindow($query, $filters);
        }

        if ($filters['payment_method'] === 'online') {
            $query->where('bill.payment_method', 'online');
        } elseif ($filters['payment_method'] === 'cash') {
            $query->where(function ($inner) {
                $inner->where('bill.payment_method', '!=', 'online')
                    ->orWhereNull('bill.payment_method');
            });
        }

        return $query;
    }

    private function applyZone($query, array $filters, string $column)
    {
        if ($filters['zone'] === '') {
            return $query;
        }

        $zone = $filters['zone'];

        return $query->where(function ($inner) use ($zone, $column) {
            $inner->where($column, $zone)
                ->orWhere($column, 'BOOK '.$zone);
        });
    }

    private function applyPaidWindow($query, array $filters)
    {
        if ($filters['date_from'] !== '') {
            $query->where('bill.date_paid', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->where('bill.date_paid', '<', Carbon::parse($filters['date_to'])->addDay()->toDateString());
        }

        return $query;
    }

    private function applyReadingWindow($query, array $filters, string $column = 'readings.created_at')
    {
        if ($filters['date_from'] !== '') {
            $query->where($column, '>=', $filters['date_from'].' 00:00:00');
        }
        if ($filters['date_to'] !== '') {
            $query->where($column, '<', Carbon::parse($filters['date_to'])->addDay()->toDateString().' 00:00:00');
        }

        return $query;
    }

    private function resolveRange(array $filters, Carbon $defaultStart, Carbon $defaultEnd): array
    {
        if ($filters['date_from'] === '' && $filters['date_to'] === '') {
            return [
                'start' => $defaultStart,
                'end' => $defaultEnd,
                'custom' => false,
            ];
        }

        $start = $filters['date_from'] !== ''
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : $defaultStart->copy();
        $end = $filters['date_to'] !== ''
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'custom' => true,
        ];
    }

    private function rangeSubtitle(array $range, string $fallback): string
    {
        if (!$range['custom']) {
            return $fallback;
        }

        return $range['start']->format('M d, Y').' – '.$range['end']->format('M d, Y');
    }

    private function scopeLabel(array $filters): string
    {
        $parts = [];
        if ($filters['date_from'] !== '' || $filters['date_to'] !== '') {
            $from = $filters['date_from'] !== '' ? Carbon::parse($filters['date_from'])->format('M d, Y') : 'Start';
            $to = $filters['date_to'] !== '' ? Carbon::parse($filters['date_to'])->format('M d, Y') : 'Today';
            $parts[] = $from.' – '.$to;
        }
        if ($filters['zone'] !== '') {
            $parts[] = 'Zone '.$filters['zone'];
        }
        if ($filters['payment_method'] !== '') {
            $parts[] = ucfirst($filters['payment_method']);
        }

        return implode(' · ', $parts);
    }

    private function scopeSuffix(array $filters, bool $includeMethod): string
    {
        $label = $this->scopeLabel([
            'date_from' => '',
            'date_to' => '',
            'zone' => $filters['zone'],
            'payment_method' => $includeMethod ? $filters['payment_method'] : '',
        ]);

        return $label === '' ? '' : ' · '.$label;
    }

    private function fillBuckets(Carbon $start, Carbon $end, array $values, bool $daily): array
    {
        $labels = [];
        $points = [];
        $normalized = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            $normalized[$daily ? substr($key, 0, 10) : substr($key, 0, 7)] = $value;
        }
        $values = $normalized;

        if ($daily) {
            $cursor = $start->copy()->startOfDay();
            $last = $end->copy()->startOfDay();
            while ($cursor->lte($last)) {
                $labels[] = $cursor->format('M d');
                $points[] = (float) ($values[$cursor->toDateString()] ?? 0);
                $cursor->addDay();
            }

            return [$labels, $points];
        }

        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();
        while ($cursor->lte($last)) {
            $labels[] = $cursor->format('M Y');
            $points[] = (float) ($values[$cursor->format('Y-m')] ?? 0);
            $cursor->addMonth();
        }

        return [$labels, $points];
    }
}
