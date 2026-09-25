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
}
