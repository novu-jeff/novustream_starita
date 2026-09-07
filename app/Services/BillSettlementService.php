<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillBreakdown;
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

        if ($total > 0) {
            return round($total, 2);
        }

        if ($amountAfterDue > 0) {
            return round($amountAfterDue, 2);
        }

        return round($amount, 2);
    }

    private function applySettlement(Bill $bill, Carbon $paidAt, ?float $amountPaid, array $attributes = []): void
    {
        $update = [
            'isPaid' => true,
            'amount_paid' => $amountPaid ?? $this->inferSettledAmount($bill, $paidAt),
            'date_paid' => $paidAt->format('Y-m-d H:i:s'),
            'isPartial' => 0,
        ];

        foreach (['payor_name', 'payment_method', 'paid_by_reference_no'] as $field) {
            if (array_key_exists($field, $attributes) && !empty($attributes[$field])) {
                $update[$field] = $attributes[$field];
            }
        }

        foreach (['change', 'isChangeForAdvancePayment', 'hitpay_reference', 'hitpay_payment_id', 'initiated_at'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $update[$field] = $attributes[$field];
            }
        }

        $bill->update($update);
    }

    private function extractAmountPaid(array $attributes): ?float
    {
        if (!array_key_exists('amount_paid', $attributes) || $attributes['amount_paid'] === null || $attributes['amount_paid'] === '') {
            return null;
        }

        return round((float) $attributes['amount_paid'], 2);
    }

    private function normalizePaidAt($paidAt): Carbon
    {
        return $paidAt instanceof Carbon
            ? $paidAt->copy()
            : Carbon::parse($paidAt);
    }
}
