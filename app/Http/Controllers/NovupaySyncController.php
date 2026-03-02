<?php

namespace App\Http\Controllers;

use App\Models\NovupayStaritaBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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
        $limit = (int) $request->input('limit', 20);
        $limit = in_array($limit, [10, 20, 50, 100], true) ? $limit : 20;

        $payments = $this->queryRecentSyncedPaid($limit);
        $list = $payments->map(fn ($b) => $this->billToRow($b))->values()->all();
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
        $payments = $this->queryPaymentsReadyToSync();
        $list = $payments->map(fn ($b) => $this->billToRow($b))->values()->all();
        return response()->json([
            'count' => $list ? count($list) : 0,
            'payments' => $list,
        ]);
    }

    /**
     * Run sync + merge, then return the list of payments that were ready (so UI can show "synced" table).
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

        // Get list of payments that are about to be synced (so we can return them as "synced")
        $paymentsBefore = $this->queryPaymentsReadyToSync();
        $listBefore = $paymentsBefore->map(fn ($b) => $this->billToRow($b))->values()->all();

        try {
            Artisan::call('novupay:sync-readings', ['--limit' => 500]);
            $syncOutput = Artisan::output();
            Artisan::call('readings:merge', ['--limit' => 500]);
            $mergeOutput = Artisan::output();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
                'count' => 0,
                'payments' => [],
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sync completed.',
            'count' => count($listBefore),
            'payments' => $listBefore,
            'sync_output' => trim($syncOutput),
            'merge_output' => trim($mergeOutput),
        ]);
    }

    private function queryPaymentsReadyToSync()
    {
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return collect([]);
        }

        return NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->whereNull('synced_to_sta_rita_at')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function queryRecentSyncedPaid(int $limit = 20)
    {
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return collect([]);
        }

        return NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->whereNotNull('synced_to_sta_rita_at')
            ->orderByDesc('synced_to_sta_rita_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function billToRow(NovupayStaritaBill $b): array
    {
        $payload = $b->payload ?? [];
        $payor = $payload['customer']['name'] ?? $payload['payor'] ?? $b->payor ?? $payload['name'] ?? $payload['customer_name'] ?? null;
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
