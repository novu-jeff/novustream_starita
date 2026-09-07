<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use App\Services\BillSettlementService;
use App\Services\StaritaNovupayBillService;
use Illuminate\Console\Command;

class OnlinePaymentsAuditCommand extends Command
{
    protected $signature = 'online-payments:audit
                            {--execute : Apply safe repairs (unsynced same-ref + copied older payments)}';

    protected $description = 'Find online payments that never posted, or were stamped on the wrong later bill';

    public function handle(
        StaritaNovupayBillService $novupay,
        BillSettlementService $settlement
    ): int {
        $applySameRef = $this->findUnsyncedSameRef($novupay);
        $applyMissingRef = $this->findMissingRefMatches($novupay);
        $unpayCopied = $this->findCopiedOlderPayments();

        $this->info('A. Paid in Novupay, local bill with the same reference still unpaid: '.count($applySameRef));
        $this->table(
            ['account', 'reference', 'amount', 'paid_at', 'bill_total'],
            array_map(fn ($r) => [$r['account'], $r['reference'], $r['amount'], $r['paid_at'], $r['bill_total']], $applySameRef)
        );

        $this->info('B. Paid Novupay QR with no local reference, unpaid bill matches reading/amount: '.count($applyMissingRef));
        $this->table(
            ['account', 'novupay_ref', 'amount', 'paid_at', 'target_ref', 'target_period'],
            array_map(fn ($r) => [$r['account'], $r['nb_ref'], $r['amount'], $r['paid_at'], $r['target_ref'], $r['target_period']], $applyMissingRef)
        );

        $this->info('C. Later bill marked paid with an older payment (date_paid before billing period): '.count($unpayCopied));
        $this->table(
            ['account', 'later_ref', 'period', 'date_paid', 'older_ref'],
            array_map(fn ($r) => [$r['account'], $r['later_ref'], $r['period'], $r['date_paid'], $r['older_ref']], $unpayCopied)
        );

        if (!$this->option('execute')) {
            $this->comment('Dry run only. Re-run with --execute to apply A and C (safe). Review B before applying those by hand.');
            return self::SUCCESS;
        }

        $applied = 0;
        foreach ($applySameRef as $row) {
            $this->applyNovupayPayment($novupay, $settlement, $row['nb'], $row['bill']);
            $applied++;
            $this->line('Applied payment to '.$row['account'].' '.$row['reference']);
        }

        $unpaid = 0;
        foreach ($unpayCopied as $row) {
            $this->unpayCopiedBill($row['later']);
            $unpaid++;
            $this->line('Cleared copied payment from '.$row['account'].' '.$row['later_ref']);
        }

        $this->info("Done. Applied {$applied} missing payment(s), cleared {$unpaid} copied later bill(s). Category B left for review.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findUnsyncedSameRef(StaritaNovupayBillService $novupay): array
    {
        $rows = [];
        $paid = NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->get();

        foreach ($paid as $nb) {
            $ref = trim((string) $nb->reference_no);
            if ($ref === '') {
                continue;
            }
            $bill = Bill::with('reading')->where('reference_no', $ref)->first();
            if (!$bill || $bill->isPaid) {
                continue;
            }
            $rows[] = [
                'account' => (string) optional($bill->reading)->account_no,
                'reference' => $ref,
                'amount' => $novupay->novupayBillAmount($nb),
                'paid_at' => (string) $nb->paid_at,
                'bill_total' => $bill->total,
                'nb' => $nb,
                'bill' => $bill,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findMissingRefMatches(StaritaNovupayBillService $novupay): array
    {
        $rows = [];
        $paid = NovupayStaritaBill::query()
            ->where(function ($q) {
                $q->where('status', 'paid')->orWhereNotNull('paid_at');
            })
            ->get();

        foreach ($paid as $nb) {
            $ref = trim((string) $nb->reference_no);
            $account = trim((string) $nb->account_no);
            if ($account === '') {
                continue;
            }
            if ($ref !== '' && Bill::where('reference_no', $ref)->exists()) {
                continue;
            }
            $matched = $novupay->findUnpaidBillMatchingPayment($account, $nb);
            if (!$matched) {
                continue;
            }
            $rows[] = [
                'account' => $account,
                'nb_ref' => $ref,
                'amount' => $novupay->novupayBillAmount($nb),
                'paid_at' => (string) $nb->paid_at,
                'target_ref' => $matched->reference_no,
                'target_period' => substr((string) $matched->bill_period_to, 0, 10),
                'nb' => $nb,
                'bill' => $matched,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findCopiedOlderPayments(): array
    {
        $rows = [];
        $laterBills = Bill::query()
            ->with('reading')
            ->where('isPaid', 1)
            ->whereNotNull('date_paid')
            ->whereNotNull('bill_period_from')
            ->whereColumn('date_paid', '<', 'bill_period_from')
            ->get();

        foreach ($laterBills as $later) {
            $account = trim((string) optional($later->reading)->account_no);
            if ($account === '') {
                continue;
            }

            $older = Bill::query()
                ->where('id', '!=', $later->id)
                ->whereHas('reading', function ($q) use ($account) {
                    $q->where('account_no', $account);
                })
                ->where('isPaid', 1)
                ->where('bill_period_to', '<=', $later->bill_period_from)
                ->where(function ($q) use ($later) {
                    if (!empty($later->hitpay_reference)) {
                        $q->orWhere('hitpay_reference', $later->hitpay_reference)
                            ->orWhere('hitpay_payment_id', $later->hitpay_reference)
                            ->orWhere('reference_no', $later->hitpay_reference);
                    }
                    if (!empty($later->date_paid)) {
                        $q->orWhere('date_paid', $later->date_paid);
                    }
                })
                ->orderByDesc('bill_period_to')
                ->first();

            if (!$older) {
                continue;
            }

            $rows[] = [
                'account' => $account,
                'later_ref' => $later->reference_no,
                'period' => substr((string) $later->bill_period_from, 0, 10).' .. '.substr((string) $later->bill_period_to, 0, 10),
                'date_paid' => (string) $later->date_paid,
                'older_ref' => $older->reference_no,
                'later' => $later,
            ];
        }

        return $rows;
    }

    private function applyNovupayPayment(
        StaritaNovupayBillService $novupay,
        BillSettlementService $settlement,
        NovupayStaritaBill $nb,
        Bill $bill
    ): void {
        $settlement->settlePaidBillChain($bill, [
            'amount_paid' => $novupay->novupayBillAmount($nb) ?: null,
            'date_paid' => $nb->paid_at,
            'payment_method' => 'online',
            'payor_name' => $nb->payor ?: $bill->payor_name,
            'hitpay_reference' => $nb->hitpay_reference,
            'hitpay_payment_id' => $nb->hitpay_reference,
        ], [
            'date_paid' => $nb->paid_at,
            'payment_method' => 'online',
            'payor_name' => $nb->payor ?: $bill->payor_name,
        ]);

        NovupayStaritaBill::whereKey($nb->id)->update([
            'synced_to_sta_rita_at' => now(),
            'status' => 'paid',
        ]);
    }

    private function unpayCopiedBill(Bill $bill): void
    {
        $bill->update([
            'isPaid' => 0,
            'amount_paid' => null,
            'date_paid' => null,
            'payment_method' => null,
            'hitpay_payment_id' => null,
            'paid_by_reference_no' => null,
            'isPartial' => 0,
            'hitpay_reference' => $bill->reference_no,
        ]);
    }
}
