<?php

namespace Tests\Unit;

use App\Exports\CashPaymentConsolidationExport;
use App\Services\CashPaymentConsolidationQuery;
use Carbon\Carbon;
use Tests\TestCase;

class CashPaymentConsolidationQueryTest extends TestCase
{
    public function test_query_limits_results_to_paid_cash_and_combines_date_and_search_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'Asia/Manila'));

        try {
            $query = CashPaymentConsolidationQuery::build('week', 'Rita');
            $sql = str_replace('`', '', strtolower($query->toSql()));

            $this->assertStringContainsString('bill.reading_id', $sql);
            $this->assertStringContainsString('readings.account_no', $sql);
            $this->assertStringContainsString('ca.user_id', $sql);
            $this->assertStringContainsString('bill.payment_method', $sql);
            $this->assertStringContainsString('bill.ispaid', $sql);
            $this->assertStringContainsString('bill.ispartial', $sql);
            $this->assertStringContainsString('bill.date_paid between', $sql);
            $this->assertStringContainsString('users.name like', $sql);
            $this->assertStringContainsString('ca.account_no like', $sql);
            $this->assertStringContainsString('bill.reference_no like', $sql);

            $this->assertSame([
                'cash',
                1,
                1,
                '2026-10-05',
                '2026-10-11 23:59:59',
                '%Rita%',
                '%Rita%',
                '%Rita%',
            ], $query->getBindings());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_export_has_requested_columns_and_handles_null_payment_amount(): void
    {
        $export = new CashPaymentConsolidationExport('today', '');

        $this->assertSame([
            'Index No.',
            'Name',
            'Account No.',
            'Reference No.',
            'Payment',
            'Date Paid',
        ], $export->headings());

        $this->assertSame([
            1,
            'Rita Customer',
            'A-100',
            'OR-100',
            '₱ 10.00',
            'Oct 09, 2026 09:30:00',
        ], $export->map((object) [
            'name' => 'Rita Customer',
            'account_no' => 'A-100',
            'reference_no' => 'OR-100',
            'property_type' => 'Residential',
            'amount_paid' => null,
            'date_paid' => '2026-10-09 09:30:00',
        ]));
    }

    public function test_custom_range_uses_both_inclusive_dates(): void
    {
        $query = CashPaymentConsolidationQuery::build('custom', '', '2026-10-05', '2026-10-19');

        $this->assertSame([
            'cash',
            1,
            1,
            '2026-10-05',
            '2026-10-19 23:59:59',
        ], $query->getBindings());
    }
}