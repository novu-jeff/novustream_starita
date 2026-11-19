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

    public function test_past_due_does_not_charge_penalty_twice(): void
    {
        $amount = PaymentController::resolveOnlinePayableAmount([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 192,
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

    public function test_period_end_before_today_does_not_apply_penalty_until_after_due_date(): void
    {
        $bill = [
            'total' => 527.80,
            'amount' => 580.58,
            'amount_after_due' => 580.58,
            'penalty' => 52.78,
            'discount' => 0,
            'due_date' => Carbon::today('Asia/Manila')->toDateString(),
            'bill_period_to' => Carbon::today('Asia/Manila')->subDays(2)->toDateString(),
        ];

        $this->assertSame(527.8, PaymentController::resolveOnlinePayableAmount($bill));

        $bill['due_date'] = Carbon::today('Asia/Manila')->subDay()->toDateString();

        $this->assertSame(580.58, PaymentController::resolveOnlinePayableAmount($bill));
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
