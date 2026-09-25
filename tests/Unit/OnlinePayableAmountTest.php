<?php

namespace Tests\Unit;

use App\Http\Controllers\PaymentController;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class OnlinePayableAmountTest extends TestCase
{
    public function test_before_due_uses_total_not_amount_after_due(): void
    {
        $amount = PaymentController::resolveOnlinePayableAmount([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => Carbon::today('Asia/Manila')->addDays(10)->toDateString(),
        ]);

        $this->assertSame(160.0, $amount);
    }

    public function test_past_due_uses_amount_after_due(): void
    {
        $amount = PaymentController::resolveOnlinePayableAmount([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => '2020-01-01',
        ]);

        $this->assertSame(176.0, $amount);
    }

    public function test_before_due_unwraps_penalty_when_total_missing(): void
    {
        $amount = PaymentController::resolveOnlinePayableAmount([
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => Carbon::today('Asia/Manila')->addDays(10)->toDateString(),
        ]);

        $this->assertSame(160.0, $amount);
    }

    public function test_past_due_penalty_is_the_difference_from_current(): void
    {
        $billData = [
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => '2020-01-01',
        ];

        $this->assertSame(160.0, PaymentController::resolveCurrentAmount($billData));
        $this->assertSame(16.0, PaymentController::resolveAppliedPenaltyAmount($billData));
    }

    public function test_before_due_penalty_is_zero(): void
    {
        $billData = [
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
            'penalty' => 16,
            'discount' => 0,
            'due_date' => Carbon::today('Asia/Manila')->addDays(10)->toDateString(),
        ];

        $this->assertSame(0.0, PaymentController::resolveAppliedPenaltyAmount($billData));
    }
}
