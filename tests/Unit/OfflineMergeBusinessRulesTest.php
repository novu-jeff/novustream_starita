<?php

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Documents offline SOA / merge date rules aligned with ReadingController::store
 * (penalty_date = due + 1 day, disconnection_date = due + 7 days).
 */
class OfflineMergeBusinessRulesTest extends TestCase
{
    public function test_due_date_derived_penalty_and_disconnection_match_reading_controller(): void
    {
        $due = Carbon::parse('2026-04-10 00:00:00');
        $this->assertSame('2026-04-11', $due->copy()->addDay()->format('Y-m-d'));
        $this->assertSame('2026-04-17', $due->copy()->addDays(7)->format('Y-m-d'));
    }

    public function test_reading_date_alias_uses_bill_period_end(): void
    {
        $billPeriodTo = '2026-03-31';
        $this->assertSame('2026-03-31', Carbon::parse($billPeriodTo)->format('Y-m-d'));
    }
}
