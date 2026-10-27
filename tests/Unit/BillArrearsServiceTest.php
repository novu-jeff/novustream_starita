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
            'previous_unpaid' => 0,
            'isPaid' => 0,
        ]);
        $olderOutstandingBill = new Bill([
            'total' => 336,
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
        $this->assertSame(160.0, $outstanding['balance']);
    }
}
