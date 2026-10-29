<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use App\Services\BillSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sync paid status from Novupay starita_bills to sta-rita Bill records.
 * Logs to both sta-rita and novupay (when STARITA_SYNC_NOVUPAY_LOG_PATH is set).
 */
class StaritaSyncPaidStatusCommand extends Command
{
    protected $signature = 'starita:sync-paid-status {--limit=500}';
    protected $description = 'Sync paid status from starita_bills to local Bill records; logs to sta-rita and novupay';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $billSettlementService = app(BillSettlementService::class);

        $this->info('starita:sync-paid-status started.');
        $this->logToBoth('starita:sync-paid-status started', ['limit' => $limit]);

        try {
            $sourceConnection = (new NovupayStaritaBill())->getConnectionName();
            if (!Schema::connection($sourceConnection)->hasTable('starita_bills')) {
                $this->warn('Table starita_bills not found.');
                $this->logToBoth('starita:sync-paid-status skipped: starita_bills table not found');
                return self::FAILURE;
            }

            $paidStarita = NovupayStaritaBill::query()
                ->where(function ($q) {
                    $q->where('status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->orderBy('paid_at', 'desc')
                ->limit($limit)
                ->get();

            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($paidStarita as $nb) {
                try {
                    $sourceIsPaid = strtolower((string) ($nb->status ?? '')) === 'paid' || !is_null($nb->paid_at);
                    if (!$sourceIsPaid) {
                        $skipped++;
                        continue;
                    }

                    $localBill = Bill::where('reference_no', $nb->reference_no)->first();
                    if (!$localBill) {
                        $skipped++;
                        continue;
                    }
                    if ($localBill->isPaid) {
                        $skipped++;
                        continue;
                    }
                    $billSettlementService->settlePaidBillChain(
                        $localBill,
                        [
                            'amount_paid' => $nb->amount ?: null,
                            'date_paid' => $nb->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                            'payment_method' => 'online',
                        ],
                        [
                            'date_paid' => $nb->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                            'payment_method' => 'online',
                        ]
                    );

                    if (strtolower((string) ($nb->status ?? '')) !== 'paid') {
                        NovupayStaritaBill::whereKey($nb->id)->update(['status' => 'paid']);
                    }
                    $updated++;
                    $this->logToBoth('starita:sync-paid-status synced', [
                        'reference_no' => $nb->reference_no,
                        'paid_at' => $nb->paid_at?->toIso8601String(),
                    ]);
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('starita:sync-paid-status failed for reference_no', [
                        'reference_no' => $nb->reference_no ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $this->logToBoth('starita:sync-paid-status error', [
                        'reference_no' => $nb->reference_no ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $summary = [
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'processed' => $paidStarita->count(),
            ];
            $this->info("starita:sync-paid-status finished. Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}.");
            $this->logToBoth('starita:sync-paid-status finished', $summary);
            Log::info('starita:sync-paid-status completed', $summary);

            return $errors > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->logToBoth('starita:sync-paid-status failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::error('starita:sync-paid-status failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    private function logToBoth(string $message, array $context = []): void
    {
        Log::info($message, $context);
        $novupayLogPath = env('STARITA_SYNC_NOVUPAY_LOG_PATH');
        if ($novupayLogPath && $novupayLogPath !== storage_path('logs/laravel.log')) {
            try {
                Log::channel('starita_sync_novupay')->info($message, $context);
            } catch (\Throwable $e) {
                Log::warning('starita_sync_novupay channel failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
