<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Services\StaritaNovupayBillService;
use Tests\TestCase;

class StaritaNovupayBillServiceTest extends TestCase
{
    private StaritaNovupayBillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StaritaNovupayBillService();
    }

    public function test_placeholder_payor_detects_unknown(): void
    {
        $this->assertTrue(StaritaNovupayBillService::isPlaceholderPayor('Unknown'));
        $this->assertTrue(StaritaNovupayBillService::isPlaceholderPayor('UNKNOWN'));
        $this->assertTrue(StaritaNovupayBillService::isPlaceholderPayor(''));
        $this->assertTrue(StaritaNovupayBillService::isPlaceholderPayor(null));
        $this->assertFalse(StaritaNovupayBillService::isPlaceholderPayor('ONG, LOURDES'));
    }

    public function test_first_usable_payor_skips_unknown(): void
    {
        $this->assertSame(
            'ONG, LOURDES',
            StaritaNovupayBillService::firstUsablePayor('Unknown', null, 'ONG, LOURDES')
        );
    }

    public function test_foreign_soa_reference_is_detected(): void
    {
        $this->assertTrue($this->service->isForeignSoaReference(
            'NST-SRWD-5-1785814352483',
            'NST-SRWD-5-1788418170494'
        ));
        $this->assertFalse($this->service->isForeignSoaReference(
            'NST-SRWD-5-1788418170494',
            'NST-SRWD-5-1788418170494'
        ));
        $this->assertFalse($this->service->isForeignSoaReference(
            'a1b2c3d4-uuid',
            'NST-SRWD-5-1788418170494'
        ));
    }

    public function test_payment_before_bill_created_is_detected(): void
    {
        $later = new Bill([
            'reference_no' => 'NST-SRWD-5-1788418170494',
            'bill_period_from' => '2026-08-04 00:00:00',
            'total' => 396.30,
            'amount' => 435.93,
            'amount_after_due' => 435.93,
        ]);
        $later->setAttribute('created_at', '2026-09-02 00:00:00');

        $this->assertTrue($this->service->paymentDateIsBeforeBillPeriod($later, '2026-09-01 13:25:06'));
        $this->assertTrue($this->service->isForeignSoaReference(
            'NST-SRWD-5-1785814352483',
            'NST-SRWD-5-1788418170494'
        ));
        $this->assertFalse($this->service->billAmountMatchesPayment($later, 580.58));
    }

    public function test_amount_match_uses_total_or_after_due(): void
    {
        $bill = new Bill([
            'total' => 160,
            'amount' => 176,
            'amount_after_due' => 176,
        ]);

        $this->assertTrue($this->service->billAmountMatchesPayment($bill, 160));
        $this->assertTrue($this->service->billAmountMatchesPayment($bill, 176));
        $this->assertTrue($this->service->billAmountMatchesPayment($bill, 173.7));
        $this->assertFalse($this->service->billAmountMatchesPayment($bill, 580.58));
    }
}
