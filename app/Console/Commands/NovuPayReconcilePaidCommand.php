<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\NovuPayCheckoutService;
use App\Services\NovuPayClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Poll NovuPay for checkouts that are paid remotely but still open on the SOA.
 * Webhooks report the bill amount plus a flat ₱10 fee, and some never arrive.
 */
class NovuPayReconcilePaidCommand extends Command
{
    protected $signature = 'novupay:reconcile-paid {--limit=100} {--dry-run}';

    protected $description = 'Mark Sta. Rita bills paid when NovuPay already collected the checkout';

    public function handle(NovuPayClient $client, NovuPayCheckoutService $checkout): int
    {
        if (!Schema::hasColumn('bill', 'novupay_req_id')) {
            $this->warn('bill.novupay_req_id is missing.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $bills = Bill::query()
            ->whereNotNull('novupay_req_id')
            ->where('novupay_req_id', '!=', '')
            ->where('isPaid', false)
            ->where(function ($query) {
                $query->whereNull('novupay_status')
                    ->orWhereIn('novupay_status', ['pending', '']);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $settled = 0;
        $skipped = 0;
        $expired = 0;
        $errors = 0;

        foreach ($bills as $bill) {
            $reqId = (string) $bill->novupay_req_id;

            try {
                $statusResult = $client->checkStatus($reqId);
            } catch (\Throwable $e) {
                $errors++;
                $this->error("{$bill->reference_no}: {$e->getMessage()}");
                Log::error('novupay:reconcile-paid status check failed', [
                    'reference_no' => $bill->reference_no,
                    'req_id' => $reqId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $remoteStatus = strtolower((string) data_get($statusResult, 'data.status', ''));

            if (!$statusResult['ok'] || $remoteStatus !== 'paid') {
                if ($remoteStatus === 'expired' && Schema::hasColumn('bill', 'novupay_status')) {
                    if (!$dryRun) {
                        $bill->update(['novupay_status' => 'expired']);
                    }
                    $expired++;
                    $this->line("{$bill->reference_no}: expired");
                    continue;
                }

                $skipped++;
                $this->line("{$bill->reference_no}: " . ($remoteStatus !== '' ? $remoteStatus : ($statusResult['error'] ?? 'not paid')));
                continue;
            }

            $amount = round((float) data_get($statusResult, 'data.amount', 0), 2);
            $refCode = data_get($statusResult, 'data.ref_code');
            $paidAt = $this->paidAtFromStatus($statusResult['data'] ?? []);

            if ($dryRun) {
                $settled++;
                $this->info("DRY-RUN {$bill->reference_no}: would settle {$amount} ref {$refCode}");
                continue;
            }

            $result = $checkout->fulfillPaidBill($bill, [
                'req_id' => $reqId,
                'amount' => $amount,
                'ref_code' => $refCode,
                'paid_at' => $paidAt,
            ]);

            if (!empty($result['settled'])) {
                $settled++;
                $this->info("Settled {$bill->reference_no} ref {$refCode}");
                continue;
            }

            if (($result['message'] ?? '') === 'already_paid') {
                $skipped++;
                continue;
            }

            if (($result['message'] ?? '') === 'amount_mismatch' && Schema::hasColumn('bill', 'novupay_status')) {
                $bill->update(['novupay_status' => 'review']);
                $skipped++;
                $this->warn("{$bill->reference_no}: paid at NovuPay but checkout amount does not match the bill. Left unpaid for review.");
                continue;
            }

            $errors++;
            $this->warn("{$bill->reference_no}: " . ($result['message'] ?? 'not settled'));
        }

        $this->info("Checked {$bills->count()}. Settled: {$settled}. Expired: {$expired}. Skipped: {$skipped}. Errors: {$errors}.");
        Log::info('novupay:reconcile-paid finished', [
            'checked' => $bills->count(),
            'settled' => $settled,
            'expired' => $expired,
            'skipped' => $skipped,
            'errors' => $errors,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    private function paidAtFromStatus(array $data): Carbon
    {
        $created = $data['created'] ?? null;
        if (is_string($created) && trim($created) !== '') {
            try {
                return Carbon::parse($created, 'Asia/Manila');
            } catch (\Throwable $e) {
                // Fall through to now.
            }
        }

        return now();
    }
}
