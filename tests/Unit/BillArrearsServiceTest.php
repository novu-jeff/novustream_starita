<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Services\BillArrearsService;
use PHPUnit\Framework\TestCase;

class BillArrearsServiceTest extends TestCase
{
    public function test_remaining_unpaid_credits_partial_on_open_bill(): void
    {
        $bill = new Bill([
            'amount' => 4979.98,
            'partial_payment' => 3268.1,
            'isPartial' => 1,
            'isPaid' => 0,
            'amount_paid' => 0,
        ]);

        $remaining = (new BillArrearsService())->remainingUnpaid($bill, 3268.1);

        $this->assertSame(1711.88, $remaining);
    }

    public function test_fully_paid_bill_carries_no_arrears(): void
    {
        $bill = new Bill([
            'amount' => 3268.1,
            'isPaid' => 1,
            'isPartial' => 0,
        ]);

        $this->assertSame(0.0, (new BillArrearsService())->remainingUnpaid($bill, 0));
    }

    public function test_latest_outstanding_skips_newer_zero_balance_bills(): void
    {
        $newerZeroBill = new Bill([
            'total' => 0,
            'amount' => 0,
            'amount_after_due' => 0,
            'previous_unpaid' => 0,
            'isPaid' => 0,
        ]);
        $olderOutstandingBill = new Bill([
            'total' => 336,
            'amount' => 336,
            'amount_after_due' => 336,
            'previous_unpaid' => 176,
            'amount_paid' => 0,
            'partial_payment' => 0,
            'change' => 0,
            'isPaid' => 0,
        ]);

        $outstanding = (new BillArrearsService())->latestOutstandingBill([
            $newerZeroBill,
            $olderOutstandingBill,
        ]);

        $this->assertSame($olderOutstandingBill, $outstanding['bill']);
    $this->assertSame(336.0, $outstanding['balance']);
    }

    public function test_latest_outstanding_uses_bill_amount_when_previous_unpaid_matches_total(): void
    {
        $unpaidBill = new Bill([
            'total' => 6722.66,
            'amount' => 6722.66,
            'amount_after_due' => 6722.66,
            'previous_unpaid' => 6722.66,
            'amount_paid' => 0,
            'change' => 0,
            'isPaid' => 0,
            'isPartial' => 0,
        ]);

        $outstanding = (new BillArrearsService())->latestOutstandingBill([$unpaidBill]);

        $this->assertSame($unpaidBill, $outstanding['bill']);
        $this->assertSame(6722.66, $outstanding['balance']);
    }

    public function test_latest_outstanding_uses_amount_after_due_instead_of_basic_charge_only(): void
    {
        $bill = new Bill([
            'total' => 160,
            'previous_unpaid' => 0,
            'amount' => 1504.86,
            'amount_after_due' => 1520.86,
            'amount_paid' => 0,
            'partial_payment' => 0,
            'change' => 0,
            'isPaid' => 0,
        ]);

        $outstanding = (new BillArrearsService())->latestOutstandingBill([$bill]);

        $this->assertSame($bill, $outstanding['bill']);
        $this->assertSame(1520.86, $outstanding['balance']);
    }

    public function test_latest_outstanding_uses_amount_when_amount_after_due_is_empty(): void
    {
        $bill = new Bill([
            'total' => 160,
            'previous_unpaid' => 0,
            'amount' => 1504.86,
            'amount_after_due' => 0,
            'amount_paid' => 0,
            'isPaid' => 0,
        ]);

        $outstanding = (new BillArrearsService())->latestOutstandingBill([$bill]);

        $this->assertSame(1504.86, $outstanding['balance']);
    }

    public function test_reviewed_arrears_sets_previous_unpaid_total_penalty_and_due_amount(): void
    {
        $bill = new Bill([
            'amount' => 1520.86,
            'amount_after_due' => 1520.86,
            'total' => 1504.86,
            'penalty' => 16,
        ]);

        (new BillArrearsService())->updateReviewedArrears($bill, 1200, 300);

        $this->assertSame(1200.0, (float) $bill->previous_unpaid);
        $this->assertSame(1500.0, (float) $bill->total);
        $this->assertSame(30.0, (float) $bill->penalty);
        $this->assertSame(1530.0, (float) $bill->amount);
        $this->assertSame(1530.0, (float) $bill->amount_after_due);
    }

    public function test_paid_latest_bill_does_not_backtrack_to_older_unpaid_bills(): void
    {
        $latestPaidBill = new Bill([
            'total' => 688,
            'previous_unpaid' => 528,
            'amount_paid' => 688,
            'isPaid' => 1,
        ]);
        $olderUnpaidBill = new Bill([
            'total' => 336,
            'previous_unpaid' => 176,
            'amount_paid' => 0,
            'isPaid' => 0,
        ]);

        $outstanding = (new BillArrearsService())->latestOutstandingBill([
            $latestPaidBill,
            $olderUnpaidBill,
        ]);

        $this->assertNull($outstanding);
    }
}
