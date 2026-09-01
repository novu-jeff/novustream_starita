<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Models\NovupayStaritaBill;
use App\Models\ReadingDate;
use App\Models\Zone;
use App\Models\Zones;
use App\Models\UserAccounts;
use App\Models\Rates;
use App\Models\PropertyTypes;
use App\Models\PaymentDiscount;
use App\Models\PaymentBreakdownPenalty;
use App\Models\Installment;
use App\Models\InstallmentSchedule;
use App\Services\BillSettlementService;
use App\Services\MergeBillReadingDatesService;
use App\Services\MeterService;
use App\Services\OfflineMergeGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OfflineSyncController extends Controller
{
    public function __construct(
        protected MeterService $meterService,
        protected BillSettlementService $billSettlementService,
        protected MergeBillReadingDatesService $mergeBillReadingDatesService,
        protected OfflineMergeGuard $offlineMergeGuard
    ) {
    }

    /**
     * Store single reading from offline mobile app.
     * All offline data is saved to readings_offline first; merge to readings (and bill) via cron or manual merge.
     */
    public function store(Request $request)
    {
        Log::channel('single')->info('Novustream offline API: readings/store', [
            'admin_id' => $request->user()?->id,
            'account_no' => $request->input('account_no'),
            'reference_no' => $request->input('reference_no'),
        ]);
        try {
            $data = $request->validate([
                'account_no'        => 'required|string',
                'previous_reading'  => 'nullable|numeric',
                'present_reading'   => 'nullable|numeric',
                'consumption'       => 'nullable|numeric',
                'reader_name'       => 'nullable|string',
                'zone'              => 'nullable|string',
                'reference_no'      => 'required|string|unique:readings_offline,reference_no',
            ]);

            $data['previous_reading'] = $this->wholeNumberOrNull($data['previous_reading'] ?? null);
            $data['present_reading'] = $this->wholeNumberOrNull($data['present_reading'] ?? null);
            $data['consumption'] = $this->wholeNumberOrNull($data['consumption'] ?? null);
            $data['source'] = 'mobile_app';
            $data['status'] = 'pending';
            $data['payload'] = $request->except(array_keys($data));

            $row = ReadingOffline::create($data);

            Log::channel('single')->info('Novustream offline API: readings/store success', ['reading_offline_id' => $row->id, 'reference_no' => $row->reference_no]);
            return response()->json(['status' => 'stored', 'reading_offline_id' => $row->id, 'reference_no' => $row->reference_no]);
        } catch (\Throwable $e) {
            Log::error('OfflineSyncController::store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['password']),
            ]);
            return response()->json(['status' => 'error', 'message' => 'Failed to store offline reading.'], 500);
        }
    }

    /**
     * Bulk sync from mobile app when back online.
     * Saves all to readings_offline (reference_no = idempotent key); no conflict with main readings table.
     * Accepts POST with JSON body { "readings": [...] } or GET with query ?readings=[...] (JSON array string).
     */
    public function sync(Request $request)
    {
        if (strtoupper($request->method()) === 'GET' && $request->has('readings')) {
            $raw = $request->query('readings');
            $readings = is_string($raw) ? (json_decode($raw, true) ?? []) : (is_array($raw) ? $raw : []);
        } else {
            $readings = $request->input('readings', []);
        }
        if (!is_array($readings)) {
            $readings = [];
        }
        $count = count($readings);

        Log::info('Novustream offline API: sync called', [
            'path'       => $request->path(),
            'method'     => $request->method(),
            'admin_id'   => $request->user()?->id,
            'count'      => $count,
            'has_key'    => $request->has('readings'),
        ]);
        Log::channel('single')->info('Novustream offline API: readings/sync', [
            'admin_id' => $request->user()?->id,
            'count'    => $count,
        ]);

        try {
            $stored = 0;
            $skippedNoRef = 0;
            foreach ($readings as $r) {
                $ref = $r['reference_no'] ?? null;
                if (empty($ref)) {
                    $skippedNoRef++;
                    continue;
                }
                ReadingOffline::updateOrCreate(
                    ['reference_no' => $ref],
                    [
                        'account_no'        => $r['account_no'] ?? '',
                        'previous_reading'  => $this->wholeNumberOrNull($r['previous_reading'] ?? null),
                        'present_reading'   => $this->wholeNumberOrNull($r['present_reading'] ?? null),
                        'consumption'       => $this->wholeNumberOrNull($r['consumption'] ?? null),
                        'reader_name'       => $r['reader_name'] ?? 'OfflineReader',
                        'zone'              => $r['zone'] ?? null,
                        'source'            => 'mobile_app',
                        'status'            => 'pending',
                        'payload'           => $r,
                    ]
                );
                $stored++;
            }

            if ($count > 0 && $stored === 0) {
                Log::channel('single')->warning('Novustream offline API: sync stored 0 – all rows missing reference_no', [
                    'count' => $count,
                    'skipped_no_ref' => $skippedNoRef,
                    'hint' => 'Each reading must have a non-empty reference_no.',
                ]);
            } elseif ($count === 0) {
                Log::channel('single')->info('Novustream offline API: sync received empty readings array', [
                    'method' => $request->method(),
                    'hint' => 'POST: send body {"readings": [...]}. GET: use query ?readings=<JSON array>.',
                ]);
            }

            Log::channel('single')->info('Novustream offline API: readings/sync success', ['stored' => $stored, 'skipped_no_ref' => $skippedNoRef]);
            $response = ['status' => 'synced', 'count' => $stored];
            if ($count > 0 && $stored === 0) {
                $response['message'] = 'No rows stored. Each reading must have a non-empty reference_no.';
            } elseif ($count === 0) {
                $response['message'] = 'Received 0 readings. Send POST body {"readings": [...]} or GET ?readings=<JSON array>.';
            }
            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('OfflineSyncController::sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'count' => $count,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Failed to sync offline readings.'], 500);
        }
    }

    /**
     * GET /offline/download — mobile app pulls accounts and/or current-period readings from merged tables.
     * Query: merchant=starita, include=accounts|readings (omit for all).
     */
    public function download(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            $label = config('app.offline_app_label', 'sta-rita');
            $hasBearer = $request->bearerToken() !== null;
            Log::channel('single')->warning('Novustream offline API: offline/download unauthenticated', [
                'has_bearer_token' => $hasBearer,
            ]);
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => $hasBearer
                    ? 'Token not recognized. Log in to this app (' . $label . ') via POST /api/login or POST /api/auth/login and use that token.'
                    : 'Send Authorization: Bearer <token> in request header (get token from POST /api/login or POST /api/auth/login). Technician and admin are allowed.',
            ], 401);
        }
        if ($user->user_type !== 'technician' && $user->user_type !== 'admin') {
            Log::channel('single')->warning('Novustream offline API: offline/download unauthorized', [
                'admin_id' => $user->id,
                'user_type' => $user->user_type,
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Log::channel('single')->info('Novustream offline API: offline/download', [
            'admin_id' => $user->id,
            'merchant' => $request->query('merchant'),
            'include' => $request->query('include'),
        ]);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $includeParam = $request->query('include', '');
        $include = array_map('trim', array_filter(explode(',', strtolower($includeParam))));
        $wantAccounts = empty($include) || in_array('accounts', $include, true);
        $wantReadings = empty($include) || in_array('readings', $include, true);

        $limit = (int) $request->query('limit', 0);
        $offset = (int) $request->query('offset', 0);
        if ($limit <= 0) {
            $limit = 0;
        }

        $year = (int) now()->year;
        $month = (int) now()->month;

        $zoneIds = $user->zone_assigned ? array_map('trim', explode(',', $user->zone_assigned)) : [];
        $zoneNames = $zoneIds ? Zones::whereIn('id', $zoneIds)->pluck('zone') : collect();

        $zoneAccountNos = null;
        if ($zoneNames->isNotEmpty()) {
            $zoneAccountNos = UserAccounts::whereIn('zone', $zoneNames->toArray())->pluck('account_no');
        }

        $currentPeriodReadings = $this->fetchCurrentPeriodReadings($year, $month, $zoneAccountNos);
        $readingsList = $wantReadings
            ? $this->buildDownloadReadingsList($currentPeriodReadings)
            : [];

        $data = [];
        if ($wantAccounts) {
            $accountsQuery = UserAccounts::with(['user', 'property_types_by_name', 'discount'])
                ->when($zoneNames->isNotEmpty(), function ($query) use ($zoneNames) {
                    $query->whereIn('zone', $zoneNames->toArray());
                });

            $totalAccounts = $accountsQuery->count();
            if ($limit > 0) {
                $accountsQuery->skip($offset)->take($limit);
            }
            $accounts = $accountsQuery->get();
            $accountNos = $accounts->pluck('account_no')->all();
            $priorPresentByAccount = $this->fetchPriorPresentReadingByAccount($accountNos, $year, $month);

            $accountsPayload = $accounts->map(function ($acc) use ($currentPeriodReadings, $priorPresentByAccount) {
                $currentReading = $currentPeriodReadings->get($acc->account_no);
                $prior = $priorPresentByAccount[$acc->account_no] ?? null;

                if ($currentReading) {
                    $presentForPrevious = $currentReading->present_reading ?? 0;
                    $readingCreatedAt = $currentReading->created_at;
                    $bill = $currentReading->bill;
                } else {
                    $presentForPrevious = $prior['present_reading'] ?? 0;
                    $readingCreatedAt = $prior['created_at'] ?? null;
                    $bill = null;
                }

                $unpaidAmount = 0.0;
                if ($bill && !$bill->isPaid) {
                    $unpaidAmount = (float) ($bill->amount ?? 0);
                } elseif ($prior && !empty($prior['unpaid_amount'])) {
                    $unpaidAmount = (float) $prior['unpaid_amount'];
                }

                return [
                    'account_no'       => $acc->account_no,
                    'name'             => $acc->user->name ?? 'N/A',
                    'address'          => $acc->address,
                    'meter_serial_no'  => $acc->meter_serial_no,
                    'zone'             => $acc->zone,
                    'status'           => $acc->status ?? null,
                    'property_type_id' => $acc->property_types_by_name->id ?? null,
                    'discount_type'    => $acc->discount->discount_type_id ?? 0,
                    'previous_reading' => (float) $presentForPrevious,
                    'unpaid_amount'    => $unpaidAmount,
                    'created_at'       => $readingCreatedAt,
                    'sequence_no'      => $acc->sequence_no ?? null,
                ];
            })->sortBy(fn ($account) => $account['sequence_no'] ?? PHP_INT_MAX)->values();

            $data['accounts'] = $accountsPayload;
            $data['rates'] = Rates::select('property_types_id', 'cu_m', 'amount')->get();
            $data['property_types'] = PropertyTypes::select('id', 'name')->get();
            $data['discounts'] = PaymentDiscount::select('eligible', 'type', 'amount', 'percentage_of')->get();
            $data['penalties'] = PaymentBreakdownPenalty::select('due_from', 'due_to', 'amount_type', 'amount')->get();

            if ($limit > 0) {
                $data['_meta'] = [
                    'total_accounts' => $totalAccounts,
                    'limit'          => $limit,
                    'offset'         => $offset,
                ];
            }
        }

        if ($wantReadings) {
            $data['readings'] = $readingsList;
        }

        Log::channel('single')->info('Novustream offline API: offline/download success', [
            'admin_id' => $user->id,
            'zone_assigned' => $user->zone_assigned,
            'zone_names_count' => $zoneNames->count(),
            'include' => $includeParam ?: 'all',
            'readings_count' => count($readingsList),
            'billing_period' => sprintf('%04d-%02d', $year, $month),
        ]);

        return response()->json($data);
    }

    /**
     * Current billing period readings from merged readings + bill (all devices/sources).
     *
     * @param  \Illuminate\Support\Collection<int, string>|null  $zoneAccountNos
     */
    private function fetchCurrentPeriodReadings(int $year, int $month, $zoneAccountNos)
    {
        $query = Reading::with(['bill.breakdown'])
            ->whereHas('bill', function ($q) use ($year, $month) {
                $q->whereYear('bill_period_to', $year)
                    ->whereMonth('bill_period_to', $month);
            });

        if ($zoneAccountNos !== null) {
            $query->whereIn('account_no', $zoneAccountNos);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->unique('account_no')
            ->keyBy('account_no');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Reading>  $currentPeriodReadings
     */
    private function buildDownloadReadingsList($currentPeriodReadings): array
    {
        $readingsList = [];
        foreach ($currentPeriodReadings as $reading) {
            $bill = $reading->bill;
            if (!$bill) {
                continue;
            }
            $refNo = $bill->reference_no ?? $reading->reference_no ?? null;
            if (!$refNo) {
                continue;
            }
            $soaData = OfflineDataController::minimalSoaFromModels($refNo, $reading, $bill);
            $readingsList[] = [
                'reference_no'          => $refNo,
                'account_no'            => $reading->account_no,
                'previous_reading'      => (float) ($reading->previous_reading ?? 0),
                'present_reading'       => (float) ($reading->present_reading ?? 0),
                'consumption'           => (float) ($reading->consumption ?? 0),
                'is_high_consumption'   => isset($bill->isHighConsumption) ? (int) $bill->isHighConsumption : 0,
                'high_consumption_note' => (string) ($bill->high_consumption_note ?? ''),
                'amount'                => (float) ($bill->amount ?? 0),
                'amount_after_due'      => (float) ($bill->amount_after_due ?? $bill->amount ?? 0),
                'timestamp'             => $this->readingTimestampIso($reading->created_at),
                'soa_json'              => json_encode($soaData),
            ];
        }

        return $readingsList;
    }

    /**
     * @param  array<int, string>  $accountNos
     * @return array<string, array{present_reading: float, created_at: mixed, unpaid_amount: float}>
     */
    private function fetchPriorPresentReadingByAccount(array $accountNos, int $year, int $month): array
    {
        if (empty($accountNos)) {
            return [];
        }

        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $rows = Reading::query()
            ->join('bill', 'bill.reading_id', '=', 'readings.id')
            ->whereIn('readings.account_no', $accountNos)
            ->where('bill.bill_period_to', '<', $periodStart)
            ->select(
                'readings.account_no',
                'readings.present_reading',
                'readings.created_at',
                'bill.amount',
                'bill.isPaid'
            )
            ->orderByDesc('bill.bill_period_to')
            ->orderByDesc('readings.created_at')
            ->get()
            ->unique('account_no');

        $result = [];
        foreach ($rows as $row) {
            $unpaid = (!$row->isPaid && $row->amount !== null) ? (float) $row->amount : 0.0;
            $result[$row->account_no] = [
                'present_reading' => (float) ($row->present_reading ?? 0),
                'created_at'      => $row->created_at,
                'unpaid_amount'   => $unpaid,
            ];
        }

        return $result;
    }

    private function readingTimestampIso($createdAt): string
    {
        if (!$createdAt) {
            return gmdate('Y-m-d\TH:i:s.000\Z');
        }

        return Carbon::parse($createdAt)->utc()->format('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * Merge pending readings_offline into readings and create/update bill (same logic as ReadingController::store).
     * reference_no -> create real reading from offline -> create bill.
     * Mirrored with morong.
     */
    public function merge(Request $request)
    {
        ini_set('memory_limit', '256M');

        $limitRaw = $request->input('limit');
        $limit = ($limitRaw !== null && $limitRaw !== '') ? (int) $limitRaw : 500;
        if ($limit < 1) {
            $limit = 500;
        }
        Log::channel('single')->info('Novustream offline API: readings/merge', [
            'admin_id' => $request->user()?->id,
            'limit' => $limit,
        ]);
        $query = ReadingOffline::eligibleForMerge()->orderBy('id');
        $query->limit($limit);
        $pending = $query->get();

        $count = 0;
        $errors = [];
        $accountsPaid = [];

        $winnerIds = [];
        foreach ($pending->groupBy('account_no') as $rows) {
            $winnerIds[] = (int) $this->offlineMergeGuard->pickWinner($rows)->id;
        }

        foreach ($pending as $off) {
            try {
                if (!in_array((int) $off->id, $winnerIds, true)) {
                    $this->markOfflineSkippedDuplicate($off, null);
                    continue;
                }

                $account = $this->meterService->getAccount($off->account_no);
                $mergeBillingDate = $this->offlineMergeGuard->resolveMergeBillingDate($off, $account);
                $existingReading = $this->offlineMergeGuard->findConflictingReading($off, $mergeBillingDate);
                if ($existingReading) {
                    $this->markOfflineSkippedDuplicate($off, $existingReading);
                    $this->updateAccountPreviousReading($off->account_no, $existingReading->present_reading);
                    $count++;
                    continue;
                }

                if (!$account) {
                    Log::warning('Merge: account not found', ['reference_no' => $off->reference_no, 'account_no' => $off->account_no]);
                    $off->update(['status' => 'rejected']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Account not found'];
                    continue;
                }

                DB::beginTransaction();

                $propertyTypeId = DB::table('property_types')
                    ->whereRaw("LOWER(REPLACE(REPLACE(name, '''', ''), '\"', '')) = ?", [
                        strtolower(str_replace(['"', "'"], '', $account->property_type ?? '')),
                    ])
                    ->value('id');

                if (!$propertyTypeId) {
                    DB::rollBack();
                    Log::warning('Merge: property type not found', ['reference_no' => $off->reference_no, 'account_no' => $off->account_no]);
                    $off->update(['status' => 'rejected']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Property type not found'];
                    continue;
                }

                $arrearsCorrectedAccounts = config('merge.arrears_corrected_accounts', []);
                $forceZeroArrears = in_array(trim($off->account_no), $arrearsCorrectedAccounts, true);

                $payload = [
                    'account_no'         => $off->account_no,
                    'previous_reading'   => $off->previous_reading,
                    'present_reading'    => $off->present_reading,
                    'consumption'        => $off->consumption ?? (($off->present_reading && $off->previous_reading) ? (float) $off->present_reading - (float) $off->previous_reading : null),
                    'reference_no'       => $off->reference_no,
                    'date'               => $mergeBillingDate,
                    'is_high_consumption'=> $off->payload['is_high_consumption'] ?? 'no',
                    'isReRead'           => filter_var($off->payload['isReRead'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'property_types_id'  => $propertyTypeId,
                    'force_zero_arrears' => $forceZeroArrears,
                ];

                if ($forceZeroArrears) {
                    Log::channel('single')->info('Merge: using zero arrears for corrected account', ['account_no' => $off->account_no, 'reference_no' => $off->reference_no]);
                }

                $computed = $this->meterService->create_breakdown($payload);

                if (($computed['status'] ?? '') !== 'success') {
                    DB::rollBack();
                    $msg = $computed['message'] ?? 'create_breakdown failed';
                    Log::warning('Merge: create_breakdown failed', ['reference_no' => $off->reference_no, 'message' => $msg]);
                    $off->update(['status' => 'rejected']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => $msg];
                    continue;
                }

                $referenceNo = $computed['reference_no'] ?? $computed['bill']['reference_no'] ?? $off->reference_no;
                $billData = $computed['bill'] ?? [];
                $basicCharge = $computed['basic_charge'] ?? 0;
                $consumption = (float) ($off->consumption ?? ($off->present_reading && $off->previous_reading ? (float) $off->present_reading - (float) $off->previous_reading : 0));

                $ok = $this->meterService->applyStorePostProcessingToBill(
                    $referenceNo,
                    $billData,
                    $basicCharge,
                    $consumption,
                    $off->account_no,
                    $off->payload ?? [],
                    true
                );

                if (!$ok) {
                    DB::rollBack();
                    Log::warning('Merge: applyStorePostProcessingToBill failed', ['reference_no' => $off->reference_no]);
                    $off->update(['status' => 'rejected']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Post-processing failed'];
                    continue;
                }

                $this->mergeBillReadingDatesService->applyZoneReadingDatesToMergedBill($account, $referenceNo);

                $reading = Reading::where('reference_no', $referenceNo)->first();
                $localBill = Bill::where('reference_no', $referenceNo)->first();

                $novupayConnection = (new NovupayStaritaBill())->getConnectionName();
                if (
                    $off->source === 'novupay'
                    && $localBill
                    && \Schema::connection($novupayConnection)->hasTable('starita_bills')
                ) {
                    $novupayBill = NovupayStaritaBill::where('reference_no', $referenceNo)->first();
                    if ($novupayBill && strtolower($novupayBill->status ?? '') === 'paid') {
                        $localBill->refresh();
                        if ($this->shouldSkipNovupayAutoSettlement($localBill)) {
                            Log::channel('single')->info('Novustream offline API: merge skipping Novupay auto-settlement (installment)', [
                                'reference_no' => $referenceNo,
                                'account_no' => $off->account_no,
                            ]);
                        } else {
                            $paidAt = $novupayBill->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
                            $update = [
                                'payment_method' => 'online',
                            ];
                            if (empty($localBill->payor_name)) {
                                $payor = $this->resolvePayorFromNovupayBill($novupayBill, $localBill);
                                $update['payor_name'] = $payor;
                            }
                            $this->billSettlementService->settlePaidBillChain(
                                $localBill,
                                [
                                    'amount_paid' => $novupayBill->amount ?: null,
                                    'date_paid' => $paidAt,
                                    'payment_method' => 'online',
                                    'payor_name' => $update['payor_name'] ?? $localBill->payor_name,
                                ],
                                [
                                    'date_paid' => $paidAt,
                                    'payment_method' => 'online',
                                    'payor_name' => $update['payor_name'] ?? $localBill->payor_name,
                                ]
                            );
                            $accountsPaid[] = [
                                'account_no' => $off->account_no,
                                'payor_name' => $update['payor_name'] ?? $localBill->payor_name ?? null,
                            ];
                        }
                    }
                }

                $off->update([
                    'synced_at' => now(),
                    'merged_into_reading_id' => $reading ? $reading->id : null,
                    'status' => 'merged',
                ]);

                $this->updateAccountPreviousReading($off->account_no, $off->present_reading);

                DB::commit();
                $count++;
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                Log::error('Merge: exception for reference_no', [
                    'reference_no' => $off->reference_no,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $off->update(['status' => 'rejected']);
                $errors[] = ['reference_no' => $off->reference_no, 'error' => $e->getMessage()];
            }
        }

        $accountsPaidCount = count($accountsPaid);
        Log::channel('single')->info('Novustream offline API: readings/merge success', [
            'count' => $count,
            'errors_count' => count($errors),
            'accounts_paid_count' => $accountsPaidCount,
            'accounts_paid' => $accountsPaid,
        ]);
        $response = [
            'status' => 'merged',
            'count' => $count,
            'accounts_paid_count' => $accountsPaidCount,
            'accounts_paid' => $accountsPaid,
        ];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        return response()->json($response);
    }

    /**
     * Resolve payor name from Novupay starita_bills (payload, payor column, or account).
     * Same fallbacks as SyncNovupayReadingsCommand so merge path also gets payor when webhook overwrote payload.
     */
    /**
     * Do not auto-mark bills paid from Novupay during merge when an installment plan is still active.
     */
    private function shouldSkipNovupayAutoSettlement(Bill $bill): bool
    {
        if ($bill->isInstallment) {
            return true;
        }
        $installment = Installment::where('bill_id', $bill->id)->where('status', 'active')->first();
        if (!$installment) {
            return false;
        }

        return InstallmentSchedule::where('installment_id', $installment->id)->where('is_paid', false)->exists();
    }

    private function resolvePayorFromNovupayBill(NovupayStaritaBill $nb, Bill $localBill): string
    {
        $payload = $nb->payload ?? [];
        $payor = $payload['customer']['name'] ?? $payload['payor'] ?? null;
        if (!empty($payor)) {
            return trim((string) $payor);
        }
        if (!empty($nb->payor)) {
            return trim((string) $nb->payor);
        }
        $payor = $payload['name'] ?? $payload['customer_name'] ?? null;
        if (!empty($payor)) {
            return trim((string) $payor);
        }
        if ($localBill->reading) {
            $payor = optional(optional($localBill->reading->concessionaire)->user)->name ?? null;
            if (!empty($payor)) {
                return trim((string) $payor);
            }
        }
        $account = $this->meterService->getAccount($localBill->reading?->account_no ?? $nb->account_no ?? '');
        $payor = optional(optional($account)->user)->name ?? null;
        return !empty($payor) ? trim((string) $payor) : 'Sta. Rita Customer';
    }

    /** Normalize to whole number for readings/consumption (no decimal); null if empty. */
    private function wholeNumberOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) round((float) $value);
    }

    private function markOfflineSkippedDuplicate(ReadingOffline $off, ?Reading $existingReading): void
    {
        $off->update([
            'synced_at' => now(),
            'merged_into_reading_id' => $existingReading?->id,
            'status' => 'skipped_duplicate',
        ]);
    }

    private function updateAccountPreviousReading(string $accountNo, mixed $presentReading): void
    {
        if (!Schema::hasColumn('concessioner_accounts', 'previous_reading')) {
            return;
        }
        $value = $this->wholeNumberOrNull($presentReading);
        if ($value === null) {
            return;
        }
        UserAccounts::where('account_no', $accountNo)->update(['previous_reading' => $value]);
    }
}
