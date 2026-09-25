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
                            {--execute : Apply safe repairs (unsynced same-ref, copied later bills, unknown payors, convenience-fee amount_paid)}';

    protected $description = 'Find online payments that never posted, or were stamped on the wrong later bill';

    public function handle(
        StaritaNovupayBillService $novupay,
        BillSettlementService $settlement
    ): int {
        $applySameRef = $this->findUnsyncedSameRef($novupay);
        $applyMissingRef = $this->findMissingRefMatches($novupay);
        $unpayCopied = $this->findCopiedOlderPayments($novupay);
        $unknownPayors = $this->findUnknownPayors();
        $feeInAmountPaid = $this->findConvenienceFeeInAmountPaid($settlement);

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

        $this->info('C. Later bill marked paid with an older payment (foreign HitPay ref / paid before bill existed): '.count($unpayCopied));
        $this->table(
            ['account', 'later_ref', 'period', 'date_paid', 'older_ref', 'reason'],
            array_map(fn ($r) => [$r['account'], $r['later_ref'], $r['period'], $r['date_paid'], $r['older_ref'], $r['reason']], $unpayCopied)
        );

        $this->info('D. Online bills with placeholder payor (Unknown): '.count($unknownPayors));
        $this->table(
            ['account', 'reference', 'current_payor', 'resolved_payor'],
            array_map(fn ($r) => [$r['account'], $r['reference'], $r['current'], $r['resolved']], $unknownPayors)
        );

        $this->info('E. Online amount_paid includes HitPay convenience fee: '.count($feeInAmountPaid));
        $this->table(
            ['account', 'reference', 'amount_paid', 'should_be'],
            array_map(fn ($r) => [$r['account'], $r['reference'], $r['current'], $r['corrected']], $feeInAmountPaid)
        );

        if (!$this->option('execute')) {
            $this->comment('Dry run only. Re-run with --execute to apply A, C, D, and E (safe). Review B before applying those by hand.');
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

        $named = 0;
        foreach ($unknownPayors as $row) {
            $row['bill']->update(['payor_name' => $row['resolved']]);
            $named++;
            $this->line('Restored payor on '.$row['account'].' '.$row['reference'].' → '.$row['resolved']);
        }

        $fees = 0;
        foreach ($feeInAmountPaid as $row) {
            $row['bill']->update(['amount_paid' => $row['corrected']]);
            $fees++;
            $this->line('Stripped convenience fee on '.$row['account'].' '.$row['reference'].' '.$row['current'].' → '.$row['corrected']);
        }

        $this->info("Done. Applied {$applied} missing payment(s), cleared {$unpaid} copied later bill(s), restored {$named} payor name(s), corrected {$fees} amount_paid value(s). Category B left for review.");

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
    private function findCopiedOlderPayments(StaritaNovupayBillService $novupay): array
    {
        $rows = [];
        $laterBills = Bill::query()
            ->with('reading.concessionaire.user')
            ->where('isPaid', 1)
            ->whereNotNull('date_paid')
            ->where(function ($q) {
                $q->where('payment_method', 'online')
                    ->orWhereNotNull('hitpay_reference')
                    ->orWhereNotNull('hitpay_payment_id');
            })
            ->get();

        foreach ($laterBills as $later) {
            $account = trim((string) optional($later->reading)->account_no);
            if ($account === '') {
                continue;
            }

            $reason = null;
            $older = null;

            if (!empty($later->hitpay_reference) && $novupay->isForeignSoaReference((string) $later->hitpay_reference, (string) $later->reference_no)) {
                $older = Bill::query()
                    ->where('id', '!=', $later->id)
                    ->where(function ($q) use ($later) {
                        $q->where('reference_no', $later->hitpay_reference)
                            ->orWhere('hitpay_reference', $later->hitpay_reference)
                            ->orWhere('hitpay_payment_id', $later->hitpay_reference);
                    })
                    ->orderByDesc('bill_period_to')
                    ->first();
                $reason = 'foreign_hitpay_reference';
            }

            if (!$older && !empty($later->date_paid) && !empty($later->created_at)
                && $novupay->paymentDateIsBeforeBillPeriod($later, $later->date_paid)) {
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
                $reason = 'paid_before_bill_existed';
            }

            if (!$older) {
                continue;
            }

            $rows[] = [
                'account' => $account,
                'later_ref' => $later->reference_no,
                'period' => substr((string) $later->bill_period_from, 0, 10).' .. '.substr((string) $later->bill_period_to, 0, 10),
                'date_paid' => (string) $later->date_paid,
                'older_ref' => $older->reference_no,
                'reason' => $reason,
                'later' => $later,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findUnknownPayors(): array
    {
        $rows = [];
        $bills = Bill::query()
            ->with('reading.concessionaire.user')
            ->where('payment_method', 'online')
            ->whereNotNull('payor_name')
            ->get();

        foreach ($bills as $bill) {
            if (!StaritaNovupayBillService::isPlaceholderPayor($bill->payor_name)) {
                continue;
            }

            $nb = NovupayStaritaBill::where('reference_no', $bill->reference_no)->first();
            $payload = $nb->payload ?? [];
            $resolved = StaritaNovupayBillService::firstUsablePayor(
                $payload['customer']['name'] ?? null,
                $payload['payor'] ?? null,
                $nb->payor ?? null,
                optional(optional(optional($bill->reading)->concessionaire)->user)->name,
                'Sta. Rita Customer'
            );
            if (!$resolved || StaritaNovupayBillService::isPlaceholderPayor($resolved)) {
                continue;
            }

            $rows[] = [
                'account' => (string) optional($bill->reading)->account_no,
                'reference' => $bill->reference_no,
                'current' => $bill->payor_name,
                'resolved' => $resolved,
                'bill' => $bill,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findConvenienceFeeInAmountPaid(BillSettlementService $settlement): array
    {
        $rows = [];
        $bills = Bill::query()
            ->with('reading')
            ->where('isPaid', 1)
            ->whereIn('payment_method', ['online', 'hitpay'])
            ->whereNotNull('amount_paid')
            ->get();

        foreach ($bills as $bill) {
            $current = round((float) $bill->amount_paid, 2);
            $bases = [
                $settlement->inferSettledAmount($bill, $bill->date_paid),
                round((float) ($bill->total ?? 0), 2),
                round(max((float) ($bill->total ?? 0) - BillSettlementService::numericBillAttribute($bill, 'discount'), 0), 2),
            ];

            $corrected = null;
            foreach ($bases as $base) {
                if ($base > 0 && BillSettlementService::looksLikeCheckoutTotal($base, $current)) {
                    $corrected = round($base, 2);
                    break;
                }
            }

            if ($corrected === null || abs($current - $corrected) < 0.06) {
                continue;
            }

            $rows[] = [
                'account' => (string) optional($bill->reading)->account_no,
                'reference' => $bill->reference_no,
                'current' => number_format($current, 2, '.', ''),
                'corrected' => number_format($corrected, 2, '.', ''),
                'bill' => $bill,
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
            'initiated_at' => null,
            'hitpay_reference' => $bill->reference_no,
        ]);
    }
}
