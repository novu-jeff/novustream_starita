<?php

namespace Tests\Unit;

use App\Models\Bill;
use PHPUnit\Framework\TestCase;

class BillNetUnpaidTest extends TestCase
{
    public function test_partial_column_reduces_unpaid_balance(): void
    {
        $this->assertSame(5848.52, Bill::netUnpaidFromValues(0, 14848.52, 9000, 1, 9000));
        $this->assertSame(9000.0, Bill::creditedPartialFromValues(9000, 1, 9000));
    }

    public function test_amount_paid_is_used_when_partial_column_is_empty(): void
    {
        $this->assertSame(5848.52, Bill::netUnpaidFromValues(0, 14848.52, 0, 1, 9000));
        $this->assertSame(9000.0, Bill::creditedPartialFromValues(0, 1, 9000));
    }

    public function test_fully_paid_bill_has_zero_unpaid(): void
    {
        $this->assertSame(0.0, Bill::netUnpaidFromValues(1, 14848.52, 0, 0, 14848.52));
    }

    public function test_unpaid_bill_without_partial_keeps_full_amount(): void
    {
        $this->assertSame(14848.52, Bill::netUnpaidFromValues(0, 14848.52, 0, 0, 0));
    }
}
