<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Models\NovupayStaritaBill;
use App\Services\BillSettlementService;
use App\Services\MeterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncController extends Controller
{
    public function __construct(
        protected MeterService $meterService,
        protected BillSettlementService $billSettlementService
    )
    {
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
        $query = ReadingOffline::where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending');
            })
            ->whereNull('synced_at')
            ->whereNull('merged_into_reading_id')
            ->orderBy('id');
        $query->limit($limit);
        $pending = $query->get();

        $count = 0;
        $errors = [];
        $accountsPaid = [];

        // Pre-pass: mark duplicate offline readings where account+month already exists in readings (remove from queue)
        foreach ($pending as $off) {
            $year = $off->created_at?->year ?? now()->year;
            $month = $off->created_at?->month ?? now()->month;
            $existingReading = Reading::where('account_no', $off->account_no)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->first();
            if ($existingReading) {
                $off->update([
                    'synced_at' => now(),
                    'merged_into_reading_id' => $existingReading->id,
                    'status' => 'skipped_duplicate',
                ]);
                $count++;
            }
        }
        // Re-fetch pending after pre-pass (excludes now-marked rows from duplicate grouping)
        $pending = $query->get();

        // Duplicate account_no in batch: do not merge multiple pending readings for same account
        $byAccount = $pending->groupBy('account_no');
        $duplicateAccountNos = $byAccount->filter(fn ($rows) => $rows->count() > 1)->keys()->all();

        foreach ($pending as $off) {
            try {
                if (in_array($off->account_no, $duplicateAccountNos, true)) {
                    $off->update(['status' => 'skipped_duplicate']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Duplicate account_no in batch (multiple pending readings for same account)'];
                    continue;
                }

                // Already merged: account + same month/year exists in readings (e.g. cashier did web reading from SOA and customer already paid)
                $year = $off->created_at?->year ?? now()->year;
                $month = $off->created_at?->month ?? now()->month;
                $existingReading = Reading::where('account_no', $off->account_no)
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->first();
                if ($existingReading) {
                    $off->update([
                        'synced_at' => now(),
                        'merged_into_reading_id' => $existingReading->id,
                        'status' => 'skipped_duplicate',
                    ]);
                    $count++;
                    continue;
                }

                DB::beginTransaction();

                $account = $this->meterService->getAccount($off->account_no);
                if (!$account) {
                    DB::rollBack();
                    Log::warning('Merge: account not found', ['reference_no' => $off->reference_no, 'account_no' => $off->account_no]);
                    $off->update(['status' => 'rejected']);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Account not found'];
                    continue;
                }

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
                    'date'               => $off->created_at ?? now(),
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

                $off->update([
                    'synced_at' => now(),
                    'merged_into_reading_id' => $reading ? $reading->id : null,
                    'status' => 'merged',
                ]);

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
}
