<?php

namespace Tests\Unit;

use App\Http\Controllers\PaymentController;
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
            'due_date' => '2026-09-15',
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
            'due_date' => '2026-09-15',
        ]);

        $this->assertSame(160.0, $amount);
    }
}
