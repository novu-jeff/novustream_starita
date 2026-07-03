<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAccounts;
use App\Models\Zones;
use App\Models\Rates;
use App\Models\PropertyTypes;
use App\Models\PaymentDiscount;
use App\Models\PaymentBreakdownPenalty;
use App\Models\Reading;
use App\Models\Bill;
use App\Models\ReadingDate;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OfflineDataController extends Controller
{
    public function download(Request $request)
    {
        
        // STATIC TOKEN AUTHENTICATION
        // $token = $request->header('X-API-KEY');

        // if ($token !== config('app.offline_api_key')) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $user = $request->user();
        if (!$user) {
            $label = config('app.offline_app_label', 'sta-rita');
            $hasBearer = $request->bearerToken() !== null;
            $hint = $hasBearer
                ? 'Token not recognized. Log in to this app (' . $label . ') via POST /api/login or POST /api/auth/login and use the returned token.'
                : 'Send Authorization: Bearer <token> (get token from POST /api/login or POST /api/auth/login).';
            Log::channel('single')->warning('Novustream offline API: offline/download unauthenticated', [
                'has_bearer_token' => $hasBearer,
                'hint' => $hint,
            ]);
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => $hasBearer
                    ? 'Token not recognized. Log in to this app (' . $label . ') via POST /api/login or POST /api/auth/login and use that token.'
                    : 'Send Authorization: Bearer <token> in request header (get token from POST /api/login or POST /api/auth/login). Technician and admin are allowed.',
            ], 401);
        }
        if ($user->user_type !== 'technician' && $user->user_type !== 'admin') {
            \Illuminate\Support\Facades\Log::channel('single')->warning('Novustream offline API: offline/download unauthorized', ['admin_id' => $user->id, 'user_type' => $user->user_type]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        \Illuminate\Support\Facades\Log::channel('single')->info('Novustream offline API: offline/download', ['admin_id' => $user->id]);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Optional: ?include=accounts,readings — only return requested sections (default: all)
        $includeParam = $request->query('include', '');
        $include = array_map('trim', array_filter(explode(',', strtolower($includeParam))));
        $wantAccounts = empty($include) || in_array('accounts', $include, true);
        $wantReadings = empty($include) || in_array('readings', $include, true);

        $limit = (int) $request->query('limit', 0);
        $offset = (int) $request->query('offset', 0);
        if ($limit <= 0) {
            $limit = 0;
        }

        // if (!$user) {
        //     return response()->json(['error' => 'Unauthenticated'], 401);
        // }

        // if (!in_array($user->user_type, ['technician', 'admin'])) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        // ✅ Determine technician’s assigned zones
        $zoneIds = $user->zone_assigned ? array_map('trim', explode(',', $user->zone_assigned)) : [];
        $zoneNames = $zoneIds ? Zones::whereIn('id', $zoneIds)->pluck('zone') : collect();

        // ✅ Fetch accounts (filter by account.zone like ReadingController)
        $accountsQuery = UserAccounts::with([
                'user',
                'readings' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])
            ->when($zoneNames->isNotEmpty(), function ($query) use ($zoneNames) {
                $query->whereIn('zone', $zoneNames->toArray());
            });

        $totalAccounts = $accountsQuery->count();
        if ($limit > 0) {
            $accountsQuery->skip($offset)->take($limit);
        }
        $accounts = $accountsQuery->get();
        
        // dd($accounts);

        // ✅ Compute from latest reading only (avoid stacking re-reads / over arrears)
        $previousReadings = [];
        $readingsList = [];
        $readingsToExport = [];

        foreach ($accounts as $acc) {
            $latest = Reading::with('bill')
                ->where('account_no', $acc->account_no)
                ->latest('created_at')
                ->first();

            $bill = $latest?->bill;
            $unpaidAmount = ($bill && !$bill->isPaid)
                ? (float) ($bill->amount ?? 0)
                : 0.0;

            $previousReadings[$acc->account_no] = [
                'present_reading' => $latest?->present_reading ?? 0,
                'created_at'      => $latest?->created_at ?? null,
                'unpaid_amount'   => $unpaidAmount,
            ];

            if ($wantReadings && $latest && $bill) {
                $refNo = $bill->reference_no ?? $latest->reference_no ?? null;
                if ($refNo) {
                    $readingsToExport[] = ['refNo' => $refNo, 'latest' => $latest, 'bill' => $bill];
                }
            }
        }

        if ($wantReadings && !empty($readingsToExport)) {
            $refNos = array_column($readingsToExport, 'refNo');
            $billsWithBreakdown = Bill::with('breakdown')->whereIn('reference_no', array_unique($refNos))->get()->keyBy('reference_no');
            foreach ($readingsToExport as $item) {
                $refNo = $item['refNo'];
                $latest = $item['latest'];
                $bill = $billsWithBreakdown->get($refNo) ?? $item['bill'];
                $soaData = self::minimalSoaFromModels($refNo, $latest, $bill);
                $readingsList[] = [
                    'reference_no'         => $refNo,
                    'account_no'           => $latest->account_no,
                    'previous_reading'     => (float) ($latest->previous_reading ?? 0),
                    'present_reading'      => (float) ($latest->present_reading ?? 0),
                    'consumption'          => (float) ($latest->consumption ?? 0),
                    'is_high_consumption'  => isset($bill->isHighConsumption) ? (int) $bill->isHighConsumption : 0,
                    'high_consumption_note'=> (string) ($bill->high_consumption_note ?? ''),
                    'amount'               => (float) ($bill->amount ?? 0),
                    'amount_after_due'     => (float) ($bill->amount_after_due ?? $bill->amount ?? 0),
                    'timestamp'            => $latest->created_at ? $latest->created_at->format('c') : date('c'),
                    'soa_json'             => json_encode($soaData),
                ];
            }
        }

        // ✅ Transform to clean arrays for frontend (only when requested)
        $accountsPayload = $accounts;
        if ($wantAccounts) {
            $accountsPayload = $accounts->map(function ($acc) use ($previousReadings) {
            $unpaid = $previousReadings[$acc->account_no]['unpaid_amount'] ?? 0;

            return [
                'account_no'       => $acc->account_no,
                'name'             => $acc->user->name ?? 'N/A',
                'address'          => $acc->address,
                'meter_serial_no'  => $acc->meter_serial_no,
                'zone'             => $acc->zone,
                'status'           => $acc->status ?? null,
                'property_type_id' => $acc->property_types_by_name->id ?? null,
                'discount_type'    => $acc->discount->discount_type_id ?? 0,
                'previous_reading' => $previousReadings[$acc->account_no]['present_reading'] ?? 0,
                'unpaid_amount'    => $unpaid,
                'created_at'      => $previousReadings[$acc->account_no]['created_at'] ?? null,
                'sequence_no'     => $acc->sequence_no ?? null,
            ];

        })->sortBy(function ($account) {
            // Sort by sequence_no, treating null as last
            return $account['sequence_no'] ?? PHP_INT_MAX;
        })->values();
        }

        // ✅ Rates, Property Types, Discounts, Penalties (only when accounts requested)
        $rates = $wantAccounts ? Rates::select('property_types_id', 'cu_m', 'amount')->get() : [];
        $propertyTypes = $wantAccounts ? PropertyTypes::select('id', 'name')->get() : [];
        $discounts = $wantAccounts ? PaymentDiscount::select('eligible', 'type', 'amount', 'percentage_of')->get() : [];
        $penalties = $wantAccounts ? PaymentBreakdownPenalty::select('due_from', 'due_to', 'amount_type', 'amount')->get() : [];

        // ✅ Build response from requested sections
        $data = [];
        if ($wantAccounts) {
            $data['accounts'] = $accountsPayload;
            $data['rates'] = $rates;
            $data['property_types'] = $propertyTypes;
            $data['discounts'] = $discounts;
            $data['penalties'] = $penalties;
        }
        if ($wantReadings) {
            $data['readings'] = $readingsList;
        }
        if ($limit > 0) {
            $data['_meta'] = [
                'total_accounts' => $totalAccounts,
                'limit'          => $limit,
                'offset'         => $offset,
            ];
        }

        Log::channel('single')->info('Novustream offline API: offline/download success', [
            'admin_id' => $user->id,
            'zone_assigned' => $user->zone_assigned,
            'zone_names_count' => $zoneNames->count(),
            'accounts_count' => $accounts->count(),
            'total_accounts' => $totalAccounts,
            'include' => $includeParam ?: 'all',
            'limit' => $limit ?: null,
            'offset' => $offset ?: null,
        ]);
        return response()->json($data);
    }

    /**
     * Build minimal SOA data for offline download (enough to generate/view/print SOA).
     * Omits client dump, previous_payment, active_payment, unpaid_bills, previousConsumption.
     */
    public static function minimalSoaForDownload($fullBill, string $refNo, $reading, $bill): array
    {
        if (!is_array($fullBill) || ($fullBill['status'] ?? null) === 'error') {
            return self::minimalSoaFromModels($refNo, $reading, $bill);
        }
        $cb = $fullBill['current_bill'] ?? [];
        $client = $fullBill['client'] ?? [];
        $readingArr = $cb['reading'] ?? [];
        $breakdown = $cb['breakdown'] ?? [];
        $dueForEnrich = $cb['due_date'] ?? null;
        $periodTo = $cb['bill_period_to'] ?? null;
        $dateExtras = self::enrichReadingScheduleDates($dueForEnrich, $periodTo, $bill, $reading);
        return [
            'reference_no'         => $cb['reference_no'] ?? $refNo,
            'account_no'           => $cb['bill_account_no'] ?? $client['account_no'] ?? $reading->account_no ?? '',
            'bill_period_from'     => $cb['bill_period_from'] ?? null,
            'bill_period_to'      => $cb['bill_period_to'] ?? null,
            'due_date'             => $cb['due_date'] ?? null,
            'reading_date'         => $dateExtras['reading_date'],
            'penalty_date'         => $dateExtras['penalty_date'],
            'disconnection_date'   => $dateExtras['disconnection_date'],
            'previous_unpaid'      => $cb['previous_unpaid'] ?? 0,
            'total'                => $cb['total'] ?? 0,
            'discount'             => $cb['discount'] ?? 0,
            'penalty'              => $cb['penalty'] ?? 0,
            'amount'               => $cb['amount'] ?? 0,
            'amount_after_due'     => $cb['amount_after_due'] ?? 0,
            'isPaid'               => $cb['isPaid'] ?? false,
            'isInstallment'        => (bool) ($cb['isInstallment'] ?? ($bill->isInstallment ?? false)),
            'date_paid'            => $cb['date_paid'] ?? null,
            'payor_name'           => $cb['payor_name'] ?? $client['name'] ?? '',
            'bill_owner_name'      => $cb['bill_owner_name'] ?? $client['name'] ?? '',
            'bill_account_no'      => $cb['bill_account_no'] ?? $client['account_no'] ?? '',
            'bill_address'         => $cb['bill_address'] ?? $client['address'] ?? '',
            'bill_meter_serial_no' => $cb['bill_meter_serial_no'] ?? $client['meter_serial_no'] ?? '',
            'reading'              => [
                'previous_reading' => $readingArr['previous_reading'] ?? $reading->previous_reading ?? 0,
                'present_reading'  => $readingArr['present_reading'] ?? $reading->present_reading ?? 0,
                'consumption'      => $readingArr['consumption'] ?? $reading->consumption ?? 0,
            ],
            'breakdown'            => array_values(array_map(function ($row) {
                return [
                    'name'        => $row['name'] ?? '',
                    'description' => $row['description'] ?? '',
                    'amount'      => $row['amount'] ?? 0,
                ];
            }, is_array($breakdown) ? $breakdown : [])),
        ];
    }

    public static function minimalSoaFromModels(string $refNo, $reading, $bill): array
    {
        $breakdown = [];
        if ($bill->relationLoaded('breakdown') && $bill->breakdown) {
            $breakdown = $bill->breakdown->map(function ($row) {
                return ['name' => $row->name ?? '', 'description' => $row->description ?? '', 'amount' => $row->amount ?? 0];
            })->values()->toArray();
        }
        $dateExtras = self::enrichReadingScheduleDates($bill->due_date ?? null, $bill->bill_period_to ?? null, $bill, $reading);
        return [
            'reference_no'         => $refNo,
            'account_no'           => $reading->account_no ?? '',
            'bill_period_from'     => $bill->bill_period_from ?? null,
            'bill_period_to'      => $bill->bill_period_to ?? null,
            'due_date'             => $bill->due_date ?? null,
            'reading_date'         => $dateExtras['reading_date'],
            'penalty_date'         => $dateExtras['penalty_date'],
            'disconnection_date'   => $dateExtras['disconnection_date'],
            'previous_unpaid'      => $bill->previous_unpaid ?? 0,
            'total'                => $bill->total ?? 0,
            'discount'             => $bill->discount ?? 0,
            'penalty'              => $bill->penalty ?? 0,
            'amount'               => $bill->amount ?? 0,
            'amount_after_due'     => $bill->amount_after_due ?? $bill->amount ?? 0,
            'isPaid'               => (bool) ($bill->isPaid ?? false),
            'isInstallment'        => (bool) ($bill->isInstallment ?? false),
            'date_paid'            => $bill->date_paid ?? null,
            'payor_name'           => $bill->payor_name ?? '',
            'bill_owner_name'      => $bill->bill_owner_name ?? $bill->payor_name ?? '',
            'bill_account_no'      => $bill->bill_account_no ?? $reading->account_no ?? '',
            'bill_address'         => $bill->bill_address ?? '',
            'bill_meter_serial_no' => $bill->bill_meter_serial_no ?? '',
            'reading'              => [
                'previous_reading' => (float) ($reading->previous_reading ?? 0),
                'present_reading'   => (float) ($reading->present_reading ?? 0),
                'consumption'      => (float) ($reading->consumption ?? 0),
            ],
            'breakdown'            => $breakdown,
        ];
    }

    /**
     * GET /offline/reading-dates — same auth as offline/download.
     * Returns reading_dates table data as JSON for the mobile offline app.
     */
    public function readingDates(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            $label = config('app.offline_app_label', 'sta-rita');
            Log::channel('single')->warning('Novustream offline API: offline/reading-dates unauthenticated');
            return response()->json([
                'error'   => 'Unauthenticated',
                'message' => 'Send Authorization: Bearer <token>. Log in via POST /api/login or POST /api/auth/login.',
            ], 401);
        }
        if ($user->user_type !== 'technician' && $user->user_type !== 'admin') {
            Log::channel('single')->warning('Novustream offline API: offline/reading-dates unauthorized', ['admin_id' => $user->id, 'user_type' => $user->user_type]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rows = ReadingDate::with('zone')->orderBy('zone_id')->get();
        $readingDates = $rows->map(function ($rd) {
            $due = $rd->due_date;
            $penaltyDate = null;
            $disconnectionDate = null;
            $readingDate = null;
            try {
                if ($due) {
                    $d = Carbon::parse($due);
                    $penaltyDate = $d->copy()->addDay()->format('Y-m-d');
                    $disconnectionDate = $d->copy()->addDays(7)->format('Y-m-d');
                }
                if ($rd->bill_period_to) {
                    $readingDate = Carbon::parse($rd->bill_period_to)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
            }

            return [
                'id'                  => $rd->id,
                'zone_id'             => $rd->zone_id,
                'zone'                => $rd->zone ? $rd->zone->zone : null,
                'bill_period_from'    => $rd->bill_period_from,
                'bill_period_to'      => $rd->bill_period_to,
                'due_date'            => $rd->due_date,
                'reading_date'        => $readingDate,
                'penalty_date'        => $penaltyDate,
                'disconnection_date'  => $disconnectionDate,
                'is_active'           => (bool) $rd->is_active,
            ];
        })->values();

        return response()->json(['reading_dates' => $readingDates]);
    }

    /**
     * Reading / schedule dates for offline SOA (matches ReadingController: penalty +1 day, disconnection +7 days from due).
     *
     * @param  mixed  $reading
     * @param  mixed  $bill
     * @return array{reading_date: ?string, penalty_date: ?string, disconnection_date: ?string}
     */
    private static function enrichReadingScheduleDates(?string $dueDate, ?string $billPeriodTo, $bill, $reading): array
    {
        $readingDate = null;
        if ($billPeriodTo) {
            try {
                $readingDate = Carbon::parse($billPeriodTo)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        if ($readingDate === null && $reading && !empty($reading->created_at)) {
            try {
                $readingDate = Carbon::parse($reading->created_at)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        $penaltyDate = null;
        $disconnectionDate = null;
        if ($bill) {
            if (!empty($bill->penalty_date)) {
                try {
                    $penaltyDate = Carbon::parse($bill->penalty_date)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }
            if (!empty($bill->disconnection_date)) {
                try {
                    $disconnectionDate = Carbon::parse($bill->disconnection_date)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }
        }
        if ($dueDate) {
            try {
                $d = Carbon::parse($dueDate);
                if ($penaltyDate === null) {
                    $penaltyDate = $d->copy()->addDay()->format('Y-m-d');
                }
                if ($disconnectionDate === null) {
                    $disconnectionDate = $d->copy()->addDays(7)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
            }
        }

        return [
            'reading_date' => $readingDate,
            'penalty_date' => $penaltyDate,
            'disconnection_date' => $disconnectionDate,
        ];
    }
}