<?php

namespace Tests\Unit;

use App\Services\NovuPayClient;
use PHPUnit\Framework\TestCase;

class NovuPaySignatureTest extends TestCase
{
    public function test_expected_signature_matches_documented_shape(): void
    {
        $reqId = 'ORDER-1001';
        $status = 'paid';
        $secret = 'YOUR_CLIENT_SECRET';

        $expected = sha1('ORDER-1001paid{YOUR_CLIENT_SECRET}');
        $this->assertSame($expected, NovuPayClient::expectedSignature($reqId, $status, $secret));
    }

    public function test_verify_webhook_signature_accepts_valid_signature(): void
    {
        $client = new class extends NovuPayClient {
            public function clientSecret(): string
            {
                return 'YOUR_CLIENT_SECRET';
            }
        };

        $signature = sha1('ORDER-1001paid{YOUR_CLIENT_SECRET}');
        $this->assertTrue($client->verifyWebhookSignature('ORDER-1001', 'paid', $signature));
    }

    public function test_verify_webhook_signature_rejects_mismatch(): void
    {
        $client = new class extends NovuPayClient {
            public function clientSecret(): string
            {
                return 'YOUR_CLIENT_SECRET';
            }
        };

        $this->assertFalse($client->verifyWebhookSignature('ORDER-1001', 'paid', 'deadbeef'));
    }
}
