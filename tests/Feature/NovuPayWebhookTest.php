<?php

namespace Tests\Feature;

use App\Services\NovuPayCheckoutService;
use Tests\TestCase;

class NovuPayWebhookTest extends TestCase
{
    public function test_webhook_route_is_csrf_exempt_and_rejects_bad_signature(): void
    {
        $response = $this->postJson('/webhooks/novupay', [
            'req_id' => 'ORDER-1',
            'status' => 'paid',
            'signature' => 'invalid',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid signature',
            ]);
    }

    public function test_webhook_accepts_valid_signature_for_unknown_order(): void
    {
        $service = new NovuPayCheckoutService();
        $reqId = 'missing-order-' . uniqid();
        $signature = $service->signatureFor($reqId, 'paid');

        $response = $this->postJson('/webhooks/novupay', [
            'req_id' => $reqId,
            'status' => 'paid',
            'signature' => $signature,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Bill not found',
            ]);
    }

    public function test_checkout_and_complete_routes_are_registered(): void
    {
        $this->get('/payments/does-not-exist/checkout')->assertStatus(404);
        $this->get('/orders/999999999/complete')->assertStatus(404);
    }
}
