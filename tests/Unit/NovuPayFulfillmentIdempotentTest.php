<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Services\BillSettlementService;
use App\Services\NovuPayCheckoutService;
use App\Services\NovuPayClient;
use App\Services\StaritaNovupayBillService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NovuPayFulfillmentIdempotentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fulfill_skips_already_paid_bill(): void
    {
        $bill = Mockery::mock(Bill::class)->makePartial();
        $bill->isPaid = true;
        $bill->reference_no = 'SRWD-TEST-1';
        $bill->novupay_amount = 150.00;

        $settlement = Mockery::mock(BillSettlementService::class);
        $settlement->shouldNotReceive('settlePaidBillChain');

        $sync = Mockery::mock(StaritaNovupayBillService::class);
        $sync->shouldNotReceive('upsertFromLocalBill');

        $service = new NovuPayCheckoutService(
            new NovuPayClient(),
            $settlement,
            $sync
        );

        $result = $service->fulfillPaidBill($bill, [
            'req_id' => 'SRWD-TEST-1-abc',
            'amount' => 150.00,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['settled']);
        $this->assertSame('already_paid', $result['message']);
    }
}
