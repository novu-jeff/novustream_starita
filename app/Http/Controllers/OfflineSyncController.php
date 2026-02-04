<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Models\NovupayStaritaBill;
use App\Services\MeterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncController extends Controller
{
    public function __construct(protected MeterService $meterService)
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

            $data['source'] = 'mobile_app';
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
     */
    public function sync(Request $request)
    {
        $readings = $request->input('readings', []);
        Log::channel('single')->info('Novustream offline API: readings/sync', [
            'admin_id' => $request->user()?->id,
            'count' => count($readings),
        ]);
        try {
            $stored = 0;
            foreach ($readings as $r) {
                $ref = $r['reference_no'] ?? null;
                if (empty($ref)) {
                    continue;
                }
                ReadingOffline::updateOrCreate(
                    ['reference_no' => $ref],
                    [
                        'account_no'        => $r['account_no'] ?? '',
                        'previous_reading'  => $r['previous_reading'] ?? null,
                        'present_reading'   => $r['present_reading'] ?? null,
                        'consumption'       => $r['consumption'] ?? null,
                        'reader_name'       => $r['reader_name'] ?? 'OfflineReader',
                        'zone'              => $r['zone'] ?? null,
                        'source'            => 'mobile_app',
                        'payload'           => $r,
                    ]
                );
                $stored++;
            }

            Log::channel('single')->info('Novustream offline API: readings/sync success', ['stored' => $stored]);
            return response()->json(['status' => 'synced', 'count' => $stored]);
        } catch (\Throwable $e) {
            Log::error('OfflineSyncController::sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'count' => count($request->input('readings', [])),
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
        $limit = (int) ($request->input('limit', 100));
        Log::channel('single')->info('Novustream offline API: readings/merge', [
            'admin_id' => $request->user()?->id,
            'limit' => $limit,
        ]);
        $pending = ReadingOffline::whereNull('synced_at')
            ->whereNull('merged_into_reading_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;
        $errors = [];

        foreach ($pending as $off) {
            try {
                DB::beginTransaction();

                $account = $this->meterService->getAccount($off->account_no);
                if (!$account) {
                    Log::warning('Merge: account not found', ['reference_no' => $off->reference_no, 'account_no' => $off->account_no]);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Account not found'];
                    DB::rollBack();
                    continue;
                }

                $propertyTypeId = DB::table('property_types')
                    ->whereRaw("LOWER(REPLACE(REPLACE(name, '''', ''), '\"', '')) = ?", [
                        strtolower(str_replace(['"', "'"], '', $account->property_type ?? '')),
                    ])
                    ->value('id');

                if (!$propertyTypeId) {
                    Log::warning('Merge: property type not found', ['reference_no' => $off->reference_no, 'account_no' => $off->account_no]);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Property type not found'];
                    DB::rollBack();
                    continue;
                }

                $payload = [
                    'account_no'         => $off->account_no,
                    'previous_reading'   => $off->previous_reading,
                    'present_reading'    => $off->present_reading,
                    'consumption'        => $off->consumption ?? (($off->present_reading && $off->previous_reading) ? (float) $off->present_reading - (float) $off->previous_reading : null),
                    'reference_no'       => $off->reference_no,
                    'date'               => $off->created_at ?? now(),
                    'is_high_consumption'=> $off->payload['is_high_consumption'] ?? 'no',
                    'isReRead'           => false,
                    'property_types_id'  => $propertyTypeId,
                ];

                $computed = $this->meterService->create_breakdown($payload);

                if (($computed['status'] ?? '') !== 'success') {
                    $msg = $computed['message'] ?? 'create_breakdown failed';
                    Log::warning('Merge: create_breakdown failed', ['reference_no' => $off->reference_no, 'message' => $msg]);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => $msg];
                    DB::rollBack();
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
                    Log::warning('Merge: applyStorePostProcessingToBill failed', ['reference_no' => $off->reference_no]);
                    $errors[] = ['reference_no' => $off->reference_no, 'error' => 'Post-processing failed'];
                    DB::rollBack();
                    continue;
                }

                $reading = Reading::where('reference_no', $referenceNo)->first();
                $localBill = Bill::where('reference_no', $referenceNo)->first();

                if ($off->source === 'novupay' && $localBill && \Schema::hasTable('starita_bills')) {
                    $novupayBill = NovupayStaritaBill::where('reference_no', $referenceNo)->first();
                    if ($novupayBill && strtolower($novupayBill->status ?? '') === 'paid') {
                        $localBill->update([
                            'isPaid' => true,
                            'date_paid' => $novupayBill->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                            'payment_method' => 'online',
                        ]);
                    }
                }

                $off->update([
                    'synced_at' => now(),
                    'merged_into_reading_id' => $reading ? $reading->id : null,
                ]);

                DB::commit();
                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Merge: exception for reference_no', [
                    'reference_no' => $off->reference_no,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors[] = ['reference_no' => $off->reference_no, 'error' => $e->getMessage()];
            }
        }

        Log::channel('single')->info('Novustream offline API: readings/merge success', ['count' => $count, 'errors_count' => count($errors)]);
        $response = ['status' => 'merged', 'count' => $count];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        return response()->json($response);
    }
}
