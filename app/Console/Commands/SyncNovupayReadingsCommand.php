<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use App\Services\BillSettlementService;
use App\Services\MeterService;
use App\Services\StaritaNovupayBillService;
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

    public function __construct(
        private readonly MeterService $meterService,
        private readonly BillSettlementService $billSettlementService,
        private readonly StaritaNovupayBillService $staritaNovupayBillService,
    )
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

            // Sync paid rows not yet applied to Sta-Rita, then repair misapplied payments.
            $unsynced = NovupayStaritaBill::query()
                ->where(function ($q) {
                    $q->where('status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->whereNull('synced_to_sta_rita_at')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $repair = NovupayStaritaBill::query()
                ->where(function ($q) {
                    $q->where('status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->whereNotNull('synced_to_sta_rita_at')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(min($limit, 200))
                ->get()
                ->filter(fn (NovupayStaritaBill $nb) => $this->staritaNovupayBillService->needsMisappliedPaymentRepair($nb));

            $bills = $unsynced->concat($repair)->unique('id')->values();

            $updated = 0;
            $created = 0;
            $loggedOnly = 0;
            $repaired = 0;
            $accountsPaid = [];
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

                    $billByRef = Bill::where('reference_no', $referenceNo)->first();
                    $localBill = $this->staritaNovupayBillService->resolveLocalBillForPayment($nb, $billByRef);

                    if (!$localBill) {
                        $localBill = $this->createLocalBillFromNovupay($nb);
                        if (!$localBill) {
                            $loggedOnly++;
                            continue;
                        }
                        $created++;
                    } elseif ($billByRef && (int) $localBill->id !== (int) $billByRef->id) {
                        $repaired++;
                    } else {
                        $updated++;
                    }

                    $this->syncLocalBillPaymentStatus($localBill, $nb);
                    $payor = $nb->payload['customer']['name'] ?? $nb->payload['payor'] ?? $localBill->payor_name ?? null;
                    $accountsPaid[] = ['account_no' => $accountNo, 'payor_name' => $payor];
                } catch (\Throwable $e) {
                    Log::error('SyncNovupayReadings: failed for reference_no', [
                        'reference_no' => $nb->reference_no ?? null,
                        'account_no' => $nb->account_no ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $accountsPaidCount = count($accountsPaid);
            $summary = [
                'updated' => $updated,
                'created' => $created,
                'repaired' => $repaired,
                'logged_only' => $loggedOnly,
                'processed' => $bills->count(),
                'accounts_paid_count' => $accountsPaidCount,
                'accounts_paid' => $accountsPaid,
            ];

            $this->info("Synced local bill records. Updated: {$updated}, Created: {$created}, Repaired: {$repaired}, Logged-only: {$loggedOnly}. Accounts paid: {$accountsPaidCount}");
            if ($accountsPaidCount > 0) {
                $this->table(['account_no', 'payor_name'], array_map(fn ($a) => [$a['account_no'], $a['payor_name'] ?? '-'], $accountsPaid));
            }
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
            // Set payor_name when empty (from starita_bills: payload, payor column, or account)
            $payor = null;
            if (empty($localBill->payor_name)) {
                $payor = $this->resolvePayorFromNovupayBill($nb, $localBill);
                if (!empty($payor)) {
                    $update['payor_name'] = $payor;
                }
            }

            $paymentMethod = $localBill->payment_method ?: 'online';

            $this->billSettlementService->settlePaidBillChain(
                $localBill,
                [
                    'amount_paid' => $nb->amount ?: null,
                    'date_paid' => $localBill->date_paid ?? $paidAt,
                    'payment_method' => $paymentMethod,
                    'payor_name' => $update['payor_name'] ?? $localBill->payor_name,
                    'hitpay_reference' => $update['hitpay_reference'] ?? $localBill->hitpay_reference,
                    'initiated_at' => $update['initiated_at'] ?? $localBill->initiated_at,
                ],
                [
                    'date_paid' => $localBill->date_paid ?? $paidAt,
                    'payment_method' => $paymentMethod,
                    'payor_name' => $update['payor_name'] ?? $localBill->payor_name,
                ]
            );

            unset($update['payor_name'], $update['hitpay_reference'], $update['initiated_at']);
        } elseif (empty($localBill->payment_method)) {
            $update['payment_method'] = 'online';
        }

        if (!empty($update)) {
            $localBill->update($update);
        }
        $this->markSourceRowAsSynced($nb, $isPaid);
    }

    /**
     * Resolve payor name from Novupay starita_bills (payload, payor column, or local account).
     * Webhook can overwrite payload so we try: payload customer.name, payor, then payor column, then payload name/customer_name, then account.
     */
    private function resolvePayorFromNovupayBill(NovupayStaritaBill $nb, Bill $localBill): ?string
    {
        $payload = $nb->payload ?? [];
        $payor = $payload['customer']['name'] ?? $payload['payor'] ?? null;
        if (!empty($payor)) {
            return trim((string) $payor);
        }
        // Payor column on starita_bills (set at creation; not overwritten by webhook)
        if (!empty($nb->payor)) {
            return trim((string) $nb->payor);
        }
        // HitPay webhook often sends name at top level or customer_name
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
        return 'Sta. Rita Customer';
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
