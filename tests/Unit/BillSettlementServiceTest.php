<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Services\BillSettlementService;
use App\Services\MeterService;
use Tests\TestCase;

class BillSettlementServiceTest extends TestCase
{
    public function test_advance_pays_bill_and_carries_only_the_excess_forward(): void
    {
        $allocation = MeterService::resolveAdvanceAllocation(160, 173.70);

        $this->assertSame(160.0, $allocation['amount_paid']);
        $this->assertTrue($allocation['isPaid']);
        $this->assertSame(13.7, $allocation['change']);
        $this->assertTrue($allocation['isChangeForAdvancePayment']);
    }

    public function test_advance_equal_to_bill_marks_it_paid_without_change(): void
    {
        $allocation = MeterService::resolveAdvanceAllocation(160, 160);

        $this->assertSame(160.0, $allocation['amount_paid']);
        $this->assertTrue($allocation['isPaid']);
        $this->assertSame(0.0, $allocation['change']);
        $this->assertFalse($allocation['isChangeForAdvancePayment']);
    }

    public function test_partial_advance_is_credited_without_creating_change(): void
    {
        $allocation = MeterService::resolveAdvanceAllocation(160, 100);

        $this->assertSame(100.0, $allocation['amount_paid']);
        $this->assertFalse($allocation['isPaid']);
        $this->assertSame(0.0, $allocation['change']);
        $this->assertFalse($allocation['isChangeForAdvancePayment']);
    }

    public function test_pineda_convenience_fee_is_13_70_on_160(): void
    {
        $this->assertSame(13.7, BillSettlementService::convenienceFeeForBillAmount(160, 10));
        $this->assertTrue(BillSettlementService::looksLikeCheckoutTotal(160, 173.7));
    }

    public function test_ong_convenience_fee_is_23_40_on_580_58(): void
    {
        $this->assertSame(23.4, BillSettlementService::convenienceFeeForBillAmount(580.58, 10));
        $this->assertTrue(BillSettlementService::looksLikeCheckoutTotal(580.58, 603.98));
    }

    public function test_online_amount_paid_strips_flat_transaction_fee(): void
    {
        config(['services.novupay.transaction_fee' => 10]);

        $bill = new Bill([
            'total' => 351.50,
            'amount' => 351.50,
            'amount_after_due' => 351.50,
            'penalty' => 0,
            'discount' => 0,
            'due_date' => '2026-10-15',
        ]);

        $paid = (new BillSettlementService())->resolveOnlineAmountPaid($bill, 361.50, '2026-10-02 15:50:00');

        $this->assertTrue(BillSettlementService::looksLikeCheckoutTotal(351.50, 361.50));
        $this->assertSame(351.50, $paid);
    }

    public function test_online_amount_paid_strips_hitpay_checkout_total_before_due(): void
    {
        $bill = new Bill([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => '2026-09-15',
        ]);

        $paid = (new BillSettlementService())->resolveOnlineAmountPaid($bill, 173.7, '2026-09-05 06:55:24');

        $this->assertSame(160.0, $paid);
    }

    public function test_online_amount_paid_before_due_does_not_keep_penalty_qr_amount(): void
    {
        $bill = new Bill([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => '2026-09-15',
        ]);

        $paid = (new BillSettlementService())->resolveOnlineAmountPaid($bill, 176, '2026-09-05 06:55:24');

        $this->assertSame(160.0, $paid);
    }

    public function test_pre_due_bill_amount_is_not_rewritten_to_after_due(): void
    {
        $bill = new Bill([
            'total' => 396.30,
            'amount' => 435.93,
            'amount_after_due' => 435.93,
            'penalty' => 39.63,
            'discount' => 0,
            'due_date' => '2026-09-16',
        ]);

        $paid = (new BillSettlementService())->resolveOnlineAmountPaid($bill, 396.30, '2026-09-01 13:25:06');

        $this->assertSame(396.30, $paid);
    }
}
