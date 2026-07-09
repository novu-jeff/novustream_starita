<?php

namespace App\Http\Controllers;

use App\Models\NovupayStaritaBill;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin: list payments ready to sync (paid, not yet synced) and run sync.
 */
class NovupaySyncController extends Controller
{
    /**
     * Settings > Online Payments page.
     */
    public function index()
    {
        return view('settings.online-payments');
    }

    /**
     * Recently synced payments that are paid (synced_to_sta_rita_at set). Optional limit: 10, 20, 50, 100.
     */
    public function recentSyncedPayments(Request $request): JsonResponse
    {
        $filterLog = [
            'endpoint' => 'recent-synced-payments',
            'account_no' => $request->input('account_no'),
            'reference_no' => $request->input('reference_no'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'limit' => $request->input('limit'),
        ];
        Log::channel('single')->info('[OnlinePayments] Filter request', $filterLog);

        $limit = (int) $request->input('limit', 20);
        $limit = in_array($limit, [20, 50, 100, 500], true) ? $limit : 20;

        $payments = $this->queryRecentSyncedPaid($limit, $request);
        $localBillsByRef = $this->getLocalBillsByReferenceNo($payments->pluck('reference_no')->all());
        $list = $payments->map(fn ($b) => $this->billToRow($b, $localBillsByRef->get($b->reference_no)))->values()->all();

        Log::channel('single')->info('[OnlinePayments] Filter result', [
            'endpoint' => 'recent-synced-payments',
            'count' => count($list),
        ]);

        return response()->json([
            'count' => count($list),
            'payments' => $list,
        ]);
    }

    /**
     * Payments that are paid (or paid_at set) and not yet synced to Sta. Rita.
     */
    public function paymentsReadyToSync(Request $request): JsonResponse
    {
        $filterLog = [
            'endpoint' => 'payments-ready-to-sync',
            'account_no' => $request->input('account_no'),
            'reference_no' => $request->input('reference_no'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'limit' => $request->input('limit'),
        ];
        Log::channel('single')->info('[OnlinePayments] Filter request', $filterLog);

        $payments = $this->queryPaymentsReadyToSync($request);
        $localBillsByRef = $this->getLocalBillsByReferenceNo($payments->pluck('reference_no')->all());
        $list = $payments->map(fn ($b) => $this->billToRow($b, $localBillsByRef->get($b->reference_no)))->values()->all();

        Log::channel('single')->info('[OnlinePayments] Filter result', [
            'endpoint' => 'payments-ready-to-sync',
            'count' => count($list),
        ]);

        return response()->json([
            'count' => $list ? count($list) : 0,
            'payments' => $list,
        ]);
    }

    /**
     * Run sync + merge, then return the list of payments that were ready (so UI can show "synced" table).
     * Logs to storage/logs/online-payments-sync.log (same file as the cron job).
     */
    public function syncOnlinePayments(Request $request): JsonResponse
    {
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return response()->json([
                'status' => 'error',
                'message' => 'starita_bills table not found.',
                'count' => 0,
                'payments' => [],
            ], 400);
        }

        $payments = $this->queryPaymentsReadyToSync($request);
        $localBillsByRef = $this->getLocalBillsByReferenceNo($payments->pluck('reference_no')->all());
        $listBefore = $payments->map(fn ($b) => $this->billToRow($b, $localBillsByRef->get($b->reference_no)))->values()->all();

        $logChannel = 'online_payments_sync';
        $startedAt = now()->format('Y-m-d H:i:s');
        Log::channel($logChannel)->info("[{$startedAt}] Manual sync started (limit=500)");

        try {
            Artisan::call('novupay:sync-readings', ['--limit' => 500]);
            $syncOutput = trim(Artisan::output());
            if ($syncOutput !== '') {
                Log::channel($logChannel)->info('novupay:sync-readings output', ['output' => $syncOutput]);
            }

            Artisan::call('readings:merge', ['--limit' => 500]);
            $mergeOutput = trim(Artisan::output());
            if ($mergeOutput !== '') {
                Log::channel($logChannel)->info('readings:merge output', ['output' => $mergeOutput]);
            }

            Log::channel($logChannel)->info('[' . now()->format('Y-m-d H:i:s') . '] Manual sync completed. Synced ' . count($listBefore) . ' payment(s).');

            return response()->json([
                'status' => 'success',
                'message' => 'Sync completed.',
                'count' => count($listBefore),
                'payments' => $listBefore,
                'sync_output' => $syncOutput,
                'merge_output' => $mergeOutput,
            ]);
        } catch (\Throwable $e) {
            Log::channel($logChannel)->error('[' . now()->format('Y-m-d H:i:s') . '] Manual sync failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
                'count' => 0,
                'payments' => [],
            ], 500);
        }
    }

    /**
     * Download recent synced payments as CSV (same filters as Recent synced view).
     */
    public function downloadRecentSyncedPayments(Request $request): StreamedResponse
    {
        $limit = (int) $request->input('limit', 500);
        $limit = in_array($limit, [20, 50, 100, 500], true) ? $limit : 500;

        $payments = $this->queryRecentSyncedPaid($limit, $request);
        $localBillsByRef = $this->getLocalBillsByReferenceNo($payments->pluck('reference_no')->all());
        $list = $payments->map(fn ($b) => $this->billToRow($b, $localBillsByRef->get($b->reference_no)))->values()->all();

        $filename = 'online-payments-recent-synced-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($list) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Account No', 'Reference No', 'Payor', 'Amount', 'Paid At', 'Status']);
            foreach ($list as $p) {
                fputcsv($out, [
                    $p['account_no'] ?? '',
                    $p['reference_no'] ?? '',
                    $p['payor_name'] ?? '',
                    $p['amount'] ?? '',
                    $p['paid_at'] ?? '',
                    $p['status'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function queryPaymentsReadyToSync(?Request $request = null)
    {
        $request = $request ?? request();
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return collect([]);
        }

        $query = NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->whereNull('synced_to_sta_rita_at')
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if ($request->filled('account_no')) {
            $term = $request->input('account_no');
            $query->where(function ($q) use ($term) {
                $q->where('account_no', 'like', '%' . $term . '%')
                    ->orWhere('payor', 'like', '%' . $term . '%');
            });
        }
        if ($request->filled('reference_no')) {
            $query->where('reference_no', 'like', '%' . $request->input('reference_no') . '%');
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $query->whereRaw('DATE(paid_at) >= ?', [$dateFrom])
                ->whereRaw('DATE(paid_at) <= ?', [$dateTo]);
            Log::channel('single')->info('[OnlinePayments] queryPaymentsReadyToSync date filter applied', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'method' => 'DATE(paid_at)',
            ]);
        } else {
            Log::channel('single')->info('[OnlinePayments] queryPaymentsReadyToSync date filter skipped', [
                'date_from_filled' => $request->filled('date_from'),
                'date_to_filled' => $request->filled('date_to'),
            ]);
        }

        $limit = (int) $request->input('limit', 500);
        $limit = in_array($limit, [20, 50, 100, 200, 500], true) ? $limit : 500;
        $query->limit($limit);

        return $query->get();
    }

    private function queryRecentSyncedPaid(int $limit = 20, ?Request $request = null)
    {
        $request = $request ?? request();
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return collect([]);
        }

        $query = NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->whereNotNull('synced_to_sta_rita_at')
            ->orderByDesc('synced_to_sta_rita_at')
            ->orderByDesc('id');

        if ($request && $request->filled('account_no')) {
            $term = $request->input('account_no');
            $query->where(function ($q) use ($term) {
                $q->where('account_no', 'like', '%' . $term . '%')
                    ->orWhere('payor', 'like', '%' . $term . '%');
            });
        }
        if ($request && $request->filled('reference_no')) {
            $query->where('reference_no', 'like', '%' . $request->input('reference_no') . '%');
        }
        if ($request && $request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $query->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereRaw('DATE(paid_at) >= ?', [$dateFrom])
                    ->whereRaw('DATE(paid_at) <= ?', [$dateTo])
                    ->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                        $q2->whereNull('paid_at')
                            ->whereRaw('DATE(synced_to_sta_rita_at) >= ?', [$dateFrom])
                            ->whereRaw('DATE(synced_to_sta_rita_at) <= ?', [$dateTo]);
                    });
            });
            Log::channel('single')->info('[OnlinePayments] queryRecentSyncedPaid date filter applied', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'source' => 'starita_bills.paid_at (fallback synced_to_sta_rita_at)',
            ]);
        } else {
            Log::channel('single')->info('[OnlinePayments] queryRecentSyncedPaid date filter skipped', [
                'date_from_filled' => $request ? $request->filled('date_from') : false,
                'date_to_filled' => $request ? $request->filled('date_to') : false,
            ]);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get local Bill models by reference_no (keyed by reference_no).
     * @param array<string> $referenceNos
     * @return \Illuminate\Support\Collection<string, Bill>
     */
    private function getLocalBillsByReferenceNo(array $referenceNos): \Illuminate\Support\Collection
    {
        if (empty($referenceNos)) {
            return collect();
        }
        return Bill::query()
            ->whereIn('reference_no', $referenceNos)
            ->get()
            ->keyBy('reference_no');
    }

    /**
     * Get reference_no values from sta_rita (default connection) bill table
     * where bill.updated_at date is between date_from and date_to inclusive.
     */
    private function getReferenceNosByBillUpdatedAtRange(string $dateFrom, string $dateTo): array
    {
        if (! Schema::hasTable('bill')) {
            return [];
        }
        return Bill::query()
            ->whereRaw('DATE(updated_at) >= ?', [$dateFrom])
            ->whereRaw('DATE(updated_at) <= ?', [$dateTo])
            ->pluck('reference_no')
            ->all();
    }

    private function billToRow(NovupayStaritaBill $b, ?Bill $localBill = null): array
    {
        $payload = $b->payload ?? [];
        $payor = null;
        if ($localBill && trim((string) ($localBill->payor_name ?? '')) !== '') {
            $payor = trim((string) $localBill->payor_name);
        }
        if ($payor === null) {
            $payor = $payload['customer']['name'] ?? $payload['payor'] ?? $b->payor ?? $payload['name'] ?? $payload['customer_name'] ?? null;
        }
        return [
            'reference_no' => $b->reference_no,
            'account_no' => $b->account_no,
            'amount' => $b->amount,
            'paid_at' => $b->paid_at?->format('Y-m-d H:i'),
            'payor_name' => $payor,
            'status' => $b->status,
        ];
    }
}
