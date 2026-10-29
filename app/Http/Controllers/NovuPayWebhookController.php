<?php

namespace App\Http\Controllers;

use App\Services\NovuPayCheckoutService;
use App\Services\NovuPayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NovuPayWebhookController extends Controller
{
    public function __construct(
        private NovuPayClient $client,
        private NovuPayCheckoutService $checkoutService
    ) {
    }

    /**
     * POST /webhooks/novupay
     */
    public function __invoke(Request $request)
    {
        $payload = $request->all();
        $reqId = (string) ($payload['req_id'] ?? '');
        $statusRaw = (string) ($payload['status'] ?? '');
        $status = strtolower($statusRaw);
        $signature = (string) ($payload['signature'] ?? '');
        $clientId = (string) ($payload['client_id'] ?? '');
        $referenceNo = (string) (data_get($payload, 'extras.param1') ?? data_get($payload, 'param1') ?? '');
        $amount = isset($payload['amount']) ? round((float) $payload['amount'], 2) : 0.0;
        $refCode = $payload['ref_code'] ?? null;

        $this->safeLog('info', 'NovuPay webhook received', [
            'req_id' => $reqId !== '' ? $reqId : null,
            'status' => $statusRaw !== '' ? $statusRaw : null,
            'client_id_match' => $clientId !== '' && hash_equals($this->client->clientId(), $clientId),
            'has_signature' => $signature !== '',
            'reference_no' => $referenceNo !== '' ? $referenceNo : null,
        ]);

        if ($reqId === '' || $statusRaw === '' || $signature === '') {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        if ($clientId === '' || !hash_equals($this->client->clientId(), $clientId)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid client'], 401);
        }

        if (!$this->client->verifyWebhookSignature($reqId, $statusRaw, $signature)) {
            $this->safeLog('warning', 'NovuPay webhook signature mismatch', [
                'req_id' => $reqId,
                'status' => $statusRaw,
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $bill = $this->checkoutService->findBillByReqId($reqId);
        if (!$bill && $referenceNo !== '') {
            $bill = $this->checkoutService->findBillByReference($referenceNo);
        }

        if (!$bill) {
            $this->safeLog('warning', 'NovuPay webhook: bill not found', [
                'req_id' => $reqId,
                'reference_no' => $referenceNo !== '' ? $referenceNo : null,
            ]);

            // Acknowledge valid signed payload even if local row is missing.
            return response()->json(['status' => 'ignored', 'message' => 'Bill not found'], 200);
        }

        if ($status === 'expired') {
            if (Schema::hasColumn('bill', 'novupay_status')) {
                $bill->update(['novupay_status' => 'expired']);
            }

            return response()->json(['status' => 'ok', 'message' => 'expired'], 200);
        }

        if ($status !== 'paid') {
            return response()->json(['status' => 'ignored', 'message' => 'Unhandled status'], 200);
        }

        if ($bill->isPaid) {
            return response()->json(['status' => 'ok', 'message' => 'already_paid'], 200);
        }

        $result = $this->checkoutService->fulfillPaidBill($bill, [
            'req_id' => $reqId,
            'amount' => $amount,
            'ref_code' => $refCode,
            'name' => data_get($payload, 'name')
                ?? data_get($payload, 'extras.name')
                ?? $bill->payor_name,
        ]);

        if (!$result['ok']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'fulfillment_failed',
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'message' => $result['message'] ?? 'settled',
        ], 200);
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::{$level}($message, $context);
        } catch (\Throwable $e) {
            // Ignore logging failures.
        }
    }
}
