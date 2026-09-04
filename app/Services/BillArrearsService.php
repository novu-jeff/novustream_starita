<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\InstallmentSchedule;
use App\Models\PartialPayment;
use App\Models\Reading;
use Illuminate\Support\Facades\Schema;

class BillArrearsService
{
    /**
     * Remaining balance to carry into the next bill.
     *
     * Uses only the latest unpaid/partial bill and credits both
     * `bill.partial_payment` and the `partial_payments` table.
     *
     * @return array{remaining_unpaid: float, credited_partial: float, source_bill: ?Bill}
     */
    public function carriedArrearsForAccount(string $accountNo, array $options = []): array
    {
        if (!empty($options['force_zero_arrears'])) {
            return $this->emptyResult();
        }

        $readingIds = Reading::where('account_no', trim($accountNo))
            ->where('isReRead', 0)
            ->pluck('id');

        if ($readingIds->isEmpty()) {
            return $this->emptyResult();
        }

        $mostRecentBill = Bill::whereIn('reading_id', $readingIds)
            ->orderByDesc('bill_period_to')
            ->first();

        if ($mostRecentBill && (bool) $mostRecentBill->isPaid && !(bool) $mostRecentBill->isPartial) {
            return $this->emptyResult();
        }

        $latestUnpaidBill = Bill::whereIn('reading_id', $readingIds)
            ->where('isInstallment', 0)
            ->where(function ($q) {
                $q->where('isPaid', 0)
                    ->orWhere('isPartial', 1);
            })
            ->orderByDesc('bill_period_to')
            ->first();

        $credited = 0.0;
        $remaining = 0.0;
        if ($latestUnpaidBill) {
            $credited = $this->creditedPartial($latestUnpaidBill);
            $remaining = $this->remainingUnpaid($latestUnpaidBill, $credited);
        }

        $installmentSchedule = InstallmentSchedule::where('is_paid', 0)
            ->whereHas('installment.bill.reading', function ($q) use ($accountNo) {
                $q->where('account_no', $accountNo);
            })
            ->orderBy('month_no')
            ->first();

        if ($installmentSchedule) {
            $remaining = (float) $installmentSchedule->amount;
        }

        return [
            'remaining_unpaid' => round($remaining, 2),
            'credited_partial' => round($credited, 2),
            'source_bill' => $latestUnpaidBill,
        ];
    }

    public function creditedPartial(Bill $bill): float
    {
        $fromColumns = $bill->creditedPartialAmount();
        $fromTable = 0.0;
        if ($bill->reading_id && Schema::hasTable('partial_payments')) {
            $fromTable = (float) PartialPayment::where('reading_id', $bill->reading_id)->sum('partial_payment');
        }

        return max($fromColumns, $fromTable, 0);
    }

    public function remainingUnpaid(Bill $bill, ?float $credited = null): float
    {
        if (filter_var($bill->isPaid, FILTER_VALIDATE_BOOLEAN)
            && !filter_var($bill->isPartial, FILTER_VALIDATE_BOOLEAN)
        ) {
            return 0.0;
        }

        $credited ??= $this->creditedPartial($bill);

        return max(round((float) ($bill->amount ?? 0) - $credited, 2), 0);
    }

    private function emptyResult(): array
    {
        return [
            'remaining_unpaid' => 0.0,
            'credited_partial' => 0.0,
            'source_bill' => null,
        ];
    }
}
