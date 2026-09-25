<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Services\StaritaNovupayBillService;
use Carbon\Carbon;

class BillSettlementService
{
    public function settlePaidBillChain(Bill $bill, array $currentAttributes = [], array $priorAttributes = []): void
    {
        $bill->loadMissing('reading');

        $paidAt = $this->normalizePaidAt($currentAttributes['date_paid'] ?? $bill->date_paid ?? now());

        $this->applySettlement(
            $bill,
            $paidAt,
            $this->extractAmountPaid($currentAttributes),
            $currentAttributes
        );

        $accountNo = optional($bill->reading)->account_no;

        if (empty($accountNo)) {
            return;
        }

        $priorBills = Bill::query()
            ->with('reading')
            ->where('id', '!=', $bill->id)
            ->whereHas('reading', function ($query) use ($accountNo) {
                $query->where('account_no', $accountNo);
            })
            ->where('bill_period_from', '<', $bill->bill_period_from)
            ->where('isPaid', 0)
            ->orderBy('bill_period_from')
            ->get();

        foreach ($priorBills as $priorBill) {
            $this->applySettlement(
                $priorBill,
                $paidAt,
                $this->extractAmountPaid($priorAttributes),
                array_merge($priorAttributes, [
                    'paid_by_reference_no' => $bill->reference_no,
                ])
            );
        }

        $this->normalizeOlderPaidAmount($bill, $paidAt, $currentAttributes);
        $this->refreshCarriedArrearsOnLaterBills($bill);
    }

    /**
     * After a backdated/online settlement, later unpaid SOAs must drop the
     * arrears that were just paid (so the next bill is not still carrying them).
     */
    public function refreshCarriedArrearsOnLaterBills(Bill $settledBill): void
    {
        $settledBill->loadMissing('reading');
        $accountNo = optional($settledBill->reading)->account_no;
        if (empty($accountNo) || empty($settledBill->bill_period_from)) {
            return;
        }

        $laterBills = Bill::query()
            ->with('reading')
            ->where('id', '!=', $settledBill->id)
            ->whereHas('reading', function ($query) use ($accountNo) {
                $query->where('account_no', $accountNo);
            })
            ->where('bill_period_from', '>=', $settledBill->bill_period_from)
            ->where('isPaid', 0)
            ->orderBy('bill_period_from')
            ->get();

        foreach ($laterBills as $laterBill) {
            $prior = Bill::query()
                ->where('id', '!=', $laterBill->id)
                ->whereHas('reading', function ($query) use ($accountNo) {
                    $query->where('account_no', $accountNo);
                })
                ->where('bill_period_to', '<=', $laterBill->bill_period_from)
                ->orderByDesc('bill_period_to')
                ->orderByDesc('id')
                ->first();

            $newPrevious = 0.0;
            if ($prior && !$prior->isPaid) {
                $newPrevious = $prior->netUnpaidAmount();
            }

            $this->rewritePreviousUnpaid($laterBill, $newPrevious);
        }
    }

    public function rewritePreviousUnpaid(Bill $bill, float $newPreviousUnpaid): void
    {
        $old = round((float) ($bill->previous_unpaid ?? 0), 2);
        $new = max(round($newPreviousUnpaid, 2), 0);
        if (abs($old - $new) < 0.01) {
            return;
        }

        $delta = $new - $old;
        $bill->update([
            'previous_unpaid' => $new,
            'total' => max(round((float) ($bill->total ?? 0) + $delta, 2), 0),
            'amount' => max(round((float) ($bill->amount ?? 0) + $delta, 2), 0),
            'amount_after_due' => max(round((float) ($bill->amount_after_due ?? $bill->amount ?? 0) + $delta, 2), 0),
        ]);

        BillBreakdown::where('bill_id', $bill->id)
            ->where('name', 'Previous Balance')
            ->update(['amount' => $new]);
    }

    private function normalizeOlderPaidAmount(Bill $settledBill, Carbon $paidAt, array $currentAttributes): void
    {
        $accountNo = optional($settledBill->reading)->account_no;
        $hitpay = $currentAttributes['hitpay_reference'] ?? $settledBill->hitpay_reference ?? null;
        $paymentAmount = $this->extractAmountPaid($currentAttributes);
        if (empty($accountNo) || $paymentAmount === null) {
            return;
        }

        $olderPaid = Bill::query()
            ->where('id', '!=', $settledBill->id)
            ->whereHas('reading', function ($query) use ($accountNo) {
                $query->where('account_no', $accountNo);
            })
            ->where('bill_period_from', '<', $settledBill->bill_period_from)
            ->where('isPaid', 1)
            ->where(function ($q) use ($hitpay, $paymentAmount) {
                $q->where('amount_paid', $paymentAmount);
                if (!empty($hitpay)) {
                    $q->orWhere('hitpay_reference', $hitpay)
                        ->orWhere('hitpay_payment_id', $hitpay);
                }
            })
            ->get();

        foreach ($olderPaid as $older) {
            $ownAmount = $this->inferSettledAmount($older, $older->date_paid ?? $paidAt);
            if (abs((float) $older->amount_paid - $paymentAmount) < 0.06 && abs($ownAmount - $paymentAmount) > 0.06) {
                $older->update([
                    'amount_paid' => $ownAmount,
                    'paid_by_reference_no' => $settledBill->reference_no,
                ]);
            }
        }
    }

    public function inferSettledAmount(Bill $bill, $paidAt = null): float
    {
        $paidAt = $this->normalizePaidAt($paidAt ?? $bill->date_paid ?? now());
        $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;

        $total = (float) ($bill->total ?? 0);
        $amount = (float) ($bill->amount ?? 0);
        $amountAfterDue = (float) ($bill->amount_after_due ?? 0);

        if ($dueDate && $paidAt->gt($dueDate) && $amountAfterDue > 0) {
            return round($amountAfterDue, 2);
        }

        $discount = self::numericBillAttribute($bill, 'discount');
        if ($total > 0) {
            return round(max($total - $discount, 0), 2);
        }

        $penalty = (float) ($bill->penalty ?? 0);
        if ($amountAfterDue > 0 && $penalty > 0 && abs($amountAfterDue - $amount) < 0.01) {
            return round(max($amountAfterDue - $penalty, 0), 2);
        }

        if ($amountAfterDue > 0) {
            return round($amountAfterDue, 2);
        }

        return round($amount, 2);
    }

    /**
     * HitPay checkout total = bill amount + convenience fee. District amount_paid is the bill only.
     */
    public static function convenienceFeeForBillAmount(float $billAmount, float $novupayFee = 10.0): float
    {
        $billAmount = round($billAmount, 2);
        $qrphFee = $billAmount <= 2000 ? 20.0 : round($billAmount * 0.01, 1);
        $gcashFee = round($billAmount * 0.023, 1);
        $hitpayFee = $billAmount < 800 ? $gcashFee : $qrphFee;

        return round($hitpayFee + $novupayFee, 2);
    }

    public static function looksLikeCheckoutTotal(float $billAmount, float $reportedAmount): bool
    {
        $reported = round($reportedAmount, 2);
        $billAmount = round($billAmount, 2);
        if ($reported <= 0 || $billAmount <= 0) {
            return false;
        }

        foreach ([10.0, 25.0] as $novupayFee) {
            $expected = round($billAmount + self::convenienceFeeForBillAmount($billAmount, $novupayFee), 2);
            if (abs($reported - $expected) < 0.06) {
                return true;
            }
        }

        return false;
    }

    /**
     * Online amount_paid is the SOA amount due, never HitPay gross (bill + convenience fee).
     */
    public function resolveOnlineAmountPaid(Bill $bill, ?float $reportedAmount, $paidAt = null): float
    {
        $billAmount = $this->inferSettledAmount($bill, $paidAt);
        $reported = $reportedAmount !== null && $reportedAmount !== ''
            ? round((float) $reportedAmount, 2)
            : 0.0;

        if ($reported <= 0 || abs($reported - $billAmount) < 0.06) {
            return $billAmount;
        }

        if (self::looksLikeCheckoutTotal($billAmount, $reported)) {
            return $billAmount;
        }

        $afterDue = round((float) ($bill->amount_after_due ?? $bill->amount ?? 0), 2);
        $paidAtNorm = $this->normalizePaidAt($paidAt ?? $bill->date_paid ?? now());
        $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;
        $beforeDue = !$dueDate || !$paidAtNorm->gt($dueDate);

        if ($beforeDue && $afterDue > $billAmount + 0.001) {
            if (abs($reported - $afterDue) < 0.06 || self::looksLikeCheckoutTotal($afterDue, $reported)) {
                return $billAmount;
            }
        }

        if (!$beforeDue && $afterDue > 0 && self::looksLikeCheckoutTotal($afterDue, $reported)) {
            return $afterDue;
        }

        return $reported;
    }

    private function applySettlement(Bill $bill, Carbon $paidAt, ?float $amountPaid, array $attributes = []): void
    {
        $paymentMethod = $attributes['payment_method'] ?? $bill->payment_method ?? null;
        if (in_array($paymentMethod, ['online', 'hitpay'], true)) {
            $amountPaid = $this->resolveOnlineAmountPaid($bill, $amountPaid, $paidAt);
        }

        $update = [
            'isPaid' => true,
            'amount_paid' => $amountPaid ?? $this->inferSettledAmount($bill, $paidAt),
            'date_paid' => $paidAt->format('Y-m-d H:i:s'),
            'isPartial' => 0,
        ];

        foreach (['payor_name', 'payment_method', 'paid_by_reference_no'] as $field) {
            if (!array_key_exists($field, $attributes) || $attributes[$field] === null || $attributes[$field] === '') {
                continue;
            }
            if ($field === 'payor_name' && StaritaNovupayBillService::isPlaceholderPayor((string) $attributes[$field])) {
                continue;
            }
            $update[$field] = $attributes[$field];
        }

        foreach (['change', 'isChangeForAdvancePayment', 'hitpay_reference', 'hitpay_payment_id', 'initiated_at'] as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }

            if ($field === 'hitpay_reference' && $this->isForeignSoaHitpayReference($bill, $attributes[$field])) {
                continue;
            }

            $update[$field] = $attributes[$field];
        }

        $bill->update($update);
    }

    /**
     * Read a numeric column even when the model also has a same-named relation
     * (e.g. bill.discount vs discount()).
     */
    public static function numericBillAttribute(Bill $bill, string $key): float
    {
        $raw = $bill->getAttributes()[$key] ?? null;
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return 0.0;
        }

        return round((float) $raw, 2);
    }

    private function extractAmountPaid(array $attributes): ?float
    {
        if (!array_key_exists('amount_paid', $attributes) || $attributes['amount_paid'] === null || $attributes['amount_paid'] === '') {
            return null;
        }

        return round((float) $attributes['amount_paid'], 2);
    }

    private function isForeignSoaHitpayReference(Bill $bill, $hitpayReference): bool
    {
        $hitpay = trim((string) $hitpayReference);
        $billRef = trim((string) ($bill->reference_no ?? ''));
        if ($hitpay === '' || $billRef === '' || strcasecmp($hitpay, $billRef) === 0) {
            return false;
        }

        return (bool) preg_match('/NST-SRWD-/i', $hitpay);
    }

    private function normalizePaidAt($paidAt): Carbon
    {
        return $paidAt instanceof Carbon
            ? $paidAt->copy()
            : Carbon::parse($paidAt);
    }
}
