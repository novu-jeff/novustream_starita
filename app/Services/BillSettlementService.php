<?php

namespace App\Services;

use App\Models\Bill;
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
