<?php

namespace Tests\Unit;

use App\Services\NovuPayClient;
use PHPUnit\Framework\TestCase;

class NovuPayCheckoutMatchTest extends TestCase
{
    public function test_reported_amount_accepts_bill_plus_flat_service_fee(): void
    {
        $this->assertTrue(NovuPayClient::reportedAmountMatchesCheckout(202.00, 212.00));
        $this->assertTrue(NovuPayClient::reportedAmountMatchesCheckout(475.20, 485.20));
        $this->assertTrue(NovuPayClient::reportedAmountMatchesCheckout(1067.00, 1067.00));
        $this->assertFalse(NovuPayClient::reportedAmountMatchesCheckout(202.00, 222.20));
        $this->assertFalse(NovuPayClient::reportedAmountMatchesCheckout(336.00, 170.00));
    }

    public function test_reference_from_req_id_strips_only_the_random_suffix(): void
    {
        $this->assertSame(
            'NST-SRWD-6-1790925418297',
            NovuPayClient::referenceFromReqId('NST-SRWD-6-1790925418297-enb46vgefx')
        );
        $this->assertSame(
            'NST-SRWD-010-1791104606',
            NovuPayClient::referenceFromReqId('NST-SRWD-010-1791104606')
        );
        $this->assertSame(
            'NST-SRWD-6-1790925418297',
            NovuPayClient::referenceFromReqId('NST-SRWD-6-1790925418297')
        );
    }
}
