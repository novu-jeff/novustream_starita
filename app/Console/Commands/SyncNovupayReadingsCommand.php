<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use App\Services\MeterService;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sync from Novupay starita_bills directly into local bill/readings tables.
 */
class SyncNovupayReadingsCommand extends Command
{
    protected $signature = 'novupay:sync-readings {--limit=100}';
    protected $description = 'Sync starita_bills from Novupay into local bill (reference/account matching + create when missing)';

    public function __construct(private readonly MeterService $meterService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sourceConnection = (new NovupayStaritaBill())->getConnectionName();

        try {
            if (!Schema::connection($sourceConnection)->hasTable('starita_bills')) {
                $this->warn("Table starita_bills not found on '{$sourceConnection}' connection.");
                Log::warning('SyncNovupayReadings: source table not found', [
                    'connection' => $sourceConnection,
                ]);
                return self::FAILURE;
            }

            // create_breakdown uses Auth::user() for reader_name; set a deterministic CLI user context
            auth()->setUser(Admin::first());

            // Only sync rows that are paid: status = 'paid' and/or paid_at set
            $bills = NovupayStaritaBill::query()
                ->where(function ($q) {
                    $q->where('status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $updated = 0;
            $created = 0;
            $loggedOnly = 0;
            foreach ($bills as $nb) {
                try {
                    $referenceNo = (string) $nb->reference_no;
                    $accountNo = (string) ($nb->account_no ?? '');
                    if ($referenceNo === '' || $accountNo === '') {
                        $loggedOnly++;
                        Log::warning('SyncNovupayReadings: skipped source row missing reference/account', [
                            'source_id' => $nb->id ?? null,
                            'reference_no' => $referenceNo ?: null,
                            'account_no' => $accountNo ?: null,
                        ]);
                        continue;
                    }

                    $localBill = Bill::where('reference_no', $referenceNo)->first();
                    if (!$localBill) {
                        $localBill = $this->findAccountPeriodMatch($accountNo, $nb);
                    }

                    if (!$localBill) {
                        $localBill = $this->createLocalBillFromNovupay($nb);
                        if (!$localBill) {
                            $loggedOnly++;
                            continue;
                        }
                        $created++;
                    } else {
                        $updated++;
                    }

                    $this->syncLocalBillPaymentStatus($localBill, $nb);
                } catch (\Throwable $e) {
                    Log::error('SyncNovupayReadings: failed for reference_no', [
                        'reference_no' => $nb->reference_no ?? null,
                        'account_no' => $nb->account_no ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $summary = [
                'updated' => $updated,
                'created' => $created,
                'logged_only' => $loggedOnly,
                'processed' => $bills->count(),
            ];

            $this->info("Synced local bill records. Updated: {$updated}, Created: {$created}, Logged-only: {$loggedOnly}");
            Log::info('SyncNovupayReadings completed', $summary);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('SyncNovupayReadings failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function findAccountPeriodMatch(string $accountNo, NovupayStaritaBill $nb): ?Bill
    {
        $sourceDate = $nb->paid_at
            ?? $nb->initiated_at
            ?? $nb->created_at
            ?? now();

        $candidates = Bill::whereHas('reading', function ($q) use ($accountNo, $sourceDate) {
            $q->where('account_no', $accountNo)
                ->whereYear('created_at', Carbon::parse($sourceDate)->year)
                ->whereMonth('created_at', Carbon::parse($sourceDate)->month);
        })->orderByDesc('id')->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            Log::warning('SyncNovupayReadings: ambiguous account-period match; skipped auto-link', [
                'reference_no' => $nb->reference_no,
                'account_no' => $accountNo,
                'candidate_bill_ids' => $candidates->pluck('id')->all(),
            ]);
        }

        return null;
    }

    private function createLocalBillFromNovupay(NovupayStaritaBill $nb): ?Bill
    {
        $accountNo = (string) $nb->account_no;
        $account = $this->meterService->getAccount($accountNo);
        if (!$account) {
            Log::warning('SyncNovupayReadings: account not found; cannot create local bill', [
                'reference_no' => $nb->reference_no,
                'account_no' => $accountNo,
            ]);
            return null;
        }

        $propertyTypeId = DB::table('property_types')
            ->whereRaw("LOWER(REPLACE(REPLACE(name, '''', ''), '\"', '')) = ?", [
                strtolower(str_replace(['"', "'"], '', (string) ($account->property_type ?? ''))),
            ])
            ->value('id');

        if (!$propertyTypeId) {
            Log::warning('SyncNovupayReadings: property type not found; cannot create local bill', [
                'reference_no' => $nb->reference_no,
                'account_no' => $accountNo,
                'property_type' => $account->property_type ?? null,
            ]);
            return null;
        }

        $present = (int) ($nb->present_reading ?? 0);
        $previous = (int) ($nb->previous_reading ?? 0);
        if ($previous <= 0 && $present > 0) {
            $prev = DB::table('readings')->where('account_no', $accountNo)->max('present_reading');
            $previous = (int) ($prev ?? 0);
        }
        if ($present < $previous) {
            $present = $previous;
        }

        $sourceDate = $nb->initiated_at ?? $nb->created_at ?? now();
        $isHigh = (bool) ($nb->is_high_consumption ?? false);

        $computed = $this->meterService->create_breakdown([
            'account_no' => $accountNo,
            'property_types_id' => $propertyTypeId,
            'present_reading' => $present,
            'previous_reading' => $previous,
            'consumption' => $present - $previous,
            'date' => Carbon::parse($sourceDate),
            'is_high_consumption' => $isHigh ? 'yes' : 'no',
            'isReRead' => false,
            'reference_no' => $nb->reference_no,
        ]);

        if (($computed['status'] ?? '') !== 'success') {
            Log::warning('SyncNovupayReadings: create_breakdown failed', [
                'reference_no' => $nb->reference_no,
                'account_no' => $accountNo,
                'message' => $computed['message'] ?? 'Unknown',
            ]);
            return null;
        }

        $referenceNo = $computed['reference_no'] ?? $nb->reference_no;
        $ok = $this->meterService->applyStorePostProcessingToBill(
            $referenceNo,
            $computed['bill'] ?? [],
            (float) ($computed['basic_charge'] ?? 0),
            (float) ($present - $previous),
            $accountNo,
            ['high_consumption_note' => $nb->high_consumption_note ?? null],
            true
        );
        if (!$ok) {
            Log::warning('SyncNovupayReadings: post-processing failed', [
                'reference_no' => $referenceNo,
                'account_no' => $accountNo,
            ]);
            return null;
        }

        return Bill::where('reference_no', $referenceNo)->first();
    }

    private function syncLocalBillPaymentStatus(Bill $localBill, NovupayStaritaBill $nb): void
    {
        $status = strtolower((string) ($nb->status ?? ''));
        $isPaid = $status === 'paid' || !is_null($nb->paid_at);
        $paidAt = $nb->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');

        $update = [
            'initiated_at' => $localBill->initiated_at ?? $nb->initiated_at ?? now(),
        ];

        if (empty($localBill->hitpay_reference) && !empty($nb->hitpay_reference)) {
            $update['hitpay_reference'] = $nb->hitpay_reference;
        }

        if ($isPaid) {
            // Never downgrade an already-paid local bill or overwrite a confirmed cash payment.
            if (!$localBill->isPaid) {
                $update['isPaid'] = true;
                $update['date_paid'] = $localBill->date_paid ?? $paidAt;
            } elseif (empty($localBill->date_paid)) {
                $update['date_paid'] = $paidAt;
            }
            if (empty($localBill->payment_method)) {
                $update['payment_method'] = 'online';
            }
            // Set payor_name when empty (from starita_bills payload or account)
            if (empty($localBill->payor_name)) {
                $payor = $nb->payload['customer']['name'] ?? $nb->payload['payor'] ?? null;
                if (empty($payor) && $localBill->reading) {
                    $payor = optional(optional($localBill->reading->concessionaire)->user)->name ?? 'Sta. Rita Customer';
                }
                if (!empty($payor)) {
                    $update['payor_name'] = $payor;
                }
            }
        } elseif (empty($localBill->payment_method)) {
            $update['payment_method'] = 'online';
        }

        $localBill->update($update);
        $this->markSourceRowAsSynced($nb, $isPaid);
    }

    private function markSourceRowAsSynced(NovupayStaritaBill $nb, bool $isPaid): void
    {
        $connection = $nb->getConnectionName();
        $table = $nb->getTable();
        $updates = [
            'synced_to_sta_rita_at' => now(),
        ];

        if ($isPaid) {
            // Normalize source rows where paid_at exists but status was left initiated.
            $updates['status'] = 'paid';
            if (is_null($nb->paid_at)) {
                $updates['paid_at'] = now();
            }
        }

        if (!Schema::connection($connection)->hasColumn($table, 'synced_to_sta_rita_at')) {
            unset($updates['synced_to_sta_rita_at']);
        }

        if (empty($updates)) {
            return;
        }

        NovupayStaritaBill::whereKey($nb->id)->update($updates);
    }
}
