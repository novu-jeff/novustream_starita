<?php

namespace Tests\Unit;

use App\Services\NovuPayCheckoutService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NovuPayCheckoutServiceTest extends TestCase
{
    private NovuPayCheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NovuPayCheckoutService();
    }

    public function test_signature_matches_novupay_formula(): void
    {
        $reqId = 'NST-STR-100';
        $status = 'paid';
        $expected = sha1($reqId . $status . '{test-novupay-secret}');

        $this->assertSame($expected, $this->service->signatureFor($reqId, $status));
        $this->assertTrue($this->service->signatureIsValid($reqId, $status, $expected));
        $this->assertFalse($this->service->signatureIsValid($reqId, $status, 'deadbeef'));
        $this->assertFalse($this->service->signatureIsValid($reqId, $status, null));
    }

    public function test_contact_is_normalized_to_9xxxxxxxxx(): void
    {
        $this->assertSame('9123456789', $this->service->formatContact('09123456789'));
        $this->assertSame('9123456789', $this->service->formatContact('+63 912 345 6789'));
        $this->assertSame('9123456789', $this->service->formatContact('9123456789'));
        $this->assertNull($this->service->formatContact('not-a-phone'));
        $this->assertNull($this->service->formatContact(''));
    }

    public function test_checkout_url_never_uses_merchant_subdomain(): void
    {
        $this->assertSame(
            'https://novu-pay.com/checkout/abc123/',
            $this->service->hostedCheckoutUrl('abc123')
        );
        $this->assertSame(
            'https://novu-pay.com/checkout/abc123/',
            $this->service->normalizeCheckoutUrl('https://srwd.novu-pay.com/checkout/abc123/', 'abc123')
        );
        $this->assertSame(
            'https://novu-pay.com/checkout/abc123/',
            $this->service->normalizeCheckoutUrl('https://pelco.novu-pay.com/checkout/abc123/')
        );
    }

    public function test_staging_checkout_host_is_used_when_configured(): void
    {
        config([
            'services.novupay.checkout_base' => 'https://novupay-staging-app-fe.novulutions.com',
        ]);

        $this->assertSame(
            'https://novupay-staging-app-fe.novulutions.com/checkout/abc123/',
            $this->service->hostedCheckoutUrl('abc123')
        );
        $this->assertSame(
            'https://novupay-staging-app-fe.novulutions.com/checkout/abc123/',
            $this->service->normalizeCheckoutUrl(
                'https://novupay-staging-app-fe.novulutions.com/checkout/abc123/'
            )
        );
        $this->assertSame(
            'https://novupay-staging-app-fe.novulutions.com/checkout/abc123/',
            $this->service->normalizeCheckoutUrl('https://srwd.novu-pay.com/checkout/abc123/')
        );
    }

    public function test_create_checkout_posts_qrph_only_and_omits_client_secret(): void
    {
        Http::fake([
            'https://api.novu-pay.com/api/open/checkout/' => Http::response([
                'status' => 'success',
                'uid' => 'uid-from-api',
                'checkout_url' => 'https://novu-pay.com/checkout/uid-from-api/',
            ], 200),
        ]);

        $result = $this->service->createCheckout([
            'req_id' => 'ORDER-1',
            'client_id' => '0000000006',
            'amount' => 150.00,
            'description' => 'Sta-Rita Water District bill',
            'email' => 'customer@example.com',
            'name' => 'Jane Customer',
            'contact' => '9123456789',
            'notification_url' => 'https://staging.example.test/webhooks/novupay',
            'redirect_url' => 'https://staging.example.test/orders/12/complete',
            'enabled_channels' => ['qrph'],
            'client_secret' => 'should-not-be-sent',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('https://novu-pay.com/checkout/uid-from-api/', $result['checkout_url']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.novu-pay.com/api/open/checkout/'
                && $request->hasHeader('x-api-key', 'test-novupay-api-key')
                && ($body['enabled_channels'] ?? null) === ['qrph']
                && ($body['client_id'] ?? null) === '0000000006'
                && !array_key_exists('client_secret', $body)
                && !array_key_exists('api_key', $body)
                && (float) ($body['amount'] ?? 0) === 150.0;
        });
    }

    public function test_api_key_label_prefix_is_not_sent(): void
    {
        config(['services.novupay.api_key' => 'key-novupay-test-prefix-fixture']);

        Http::fake([
            'https://api.novu-pay.com/api/open/checkout/' => Http::response([
                'status' => 'success',
                'uid' => 'uid-from-api',
                'checkout_url' => 'https://novu-pay.com/checkout/uid-from-api/',
            ], 200),
        ]);

        $result = $this->service->createCheckout([
            'req_id' => 'ORDER-PREFIX',
            'client_id' => '0000000002',
            'amount' => 50.00,
            'description' => 'Prefix check',
            'notification_url' => 'https://staging.example.test/webhooks/novupay',
            'enabled_channels' => ['qrph'],
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'novupay-test-prefix-fixture');
        });
    }

    public function test_webhook_rejects_invalid_signature_without_fulfilling(): void
    {
        $result = $this->service->markPaidFromWebhook([
            'req_id' => 'ORDER-1',
            'status' => 'paid',
            'signature' => 'not-valid',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame(400, $result['http']);
        $this->assertSame('Invalid signature', $result['message']);
    }

    public function test_notification_and_redirect_urls_use_public_host(): void
    {
        $this->assertSame('https://staging.example.test/webhooks/novupay', $this->service->notificationUrl());
        $this->assertSame('https://staging.example.test/orders/42/complete', $this->service->redirectUrl(42));
        $this->assertSame(
            'https://staging.example.test/payments/NST-STR-1/checkout',
            $this->service->checkoutStartUrl('NST-STR-1')
        );
    }

    public function test_checkout_details_include_reference_month_and_penalty(): void
    {
        $billData = [
            'total' => 100,
            'amount' => 110,
            'amount_after_due' => 110,
            'penalty' => 10,
            'discount' => 0,
            'due_date' => now('Asia/Manila')->subDays(3)->toDateString(),
            'bill_period_to' => '2026-08-31',
            'account_no' => '06-001-123',
        ];

        $details = $this->service->checkoutDetails($billData, 'NST-SRWD-6-1788313360156', 110.00);

        $this->assertSame(
            'Sta-Rita Water District bill | Ref: NST-SRWD-6-1788313360156 | Acct: 06-001-123 | August 2026 | Amount PHP 100.00 | Penalty PHP 10.00',
            $details['description']
        );
        $this->assertSame('NST-SRWD-6-1788313360156', $details['param1']);
        $this->assertSame('August 2026 | Acct: 06-001-123', $details['param2']);
        $this->assertSame([
            ['name' => 'Water bill — August 2026 (Acct: 06-001-123)', 'amount' => 100.0, 'quantity' => 1],
            ['name' => 'Penalty fee', 'amount' => 10.0, 'quantity' => 1],
        ], $details['cart']);
    }

    public function test_checkout_details_omit_penalty_before_due(): void
    {
        $billData = [
            'total' => 100,
            'amount' => 110,
            'amount_after_due' => 110,
            'penalty' => 10,
            'discount' => 0,
            'due_date' => now('Asia/Manila')->addDays(10)->toDateString(),
            'bill_period_to' => '2026-08-31',
            'reading' => ['account_no' => '06-001-123'],
        ];

        $details = $this->service->checkoutDetails($billData, 'NST-SRWD-6-1788313360156', 100.00);

        $this->assertSame(
            'Sta-Rita Water District bill | Ref: NST-SRWD-6-1788313360156 | Acct: 06-001-123 | August 2026 | Amount PHP 100.00',
            $details['description']
        );
        $this->assertSame([
            ['name' => 'Water bill — August 2026 (Acct: 06-001-123)', 'amount' => 100.0, 'quantity' => 1],
        ], $details['cart']);
        $this->assertSame('August 2026 | Acct: 06-001-123', $details['param2']);
    }

    public function test_checkout_details_add_flat_transaction_fee(): void
    {
        $billData = [
            'total' => 351.50,
            'amount' => 351.50,
            'amount_after_due' => 351.50,
            'penalty' => 0,
            'discount' => 0,
            'due_date' => now('Asia/Manila')->addDays(10)->toDateString(),
            'bill_period_to' => '2026-09-30',
            'account_no' => '06-001-123',
        ];

        $details = $this->service->checkoutDetails($billData, 'NST-SRWD-6-1', 351.50, 10);

        $this->assertStringContainsString('Transaction fee PHP 10.00', $details['description']);
        $this->assertSame([
            ['name' => 'Water bill — September 2026 (Acct: 06-001-123)', 'amount' => 351.5, 'quantity' => 1],
            ['name' => 'Transaction fee', 'amount' => 10.0, 'quantity' => 1],
        ], $details['cart']);
    }
}
