<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NovuPayCheckoutService
{
    public const MIN_AMOUNT = 100.0;

    public function isConfigured(): bool
    {
        return $this->apiKey() !== ''
            && $this->clientId() !== ''
            && $this->apiUrl() !== '';
    }

    public function publicBaseUrl(): string
    {
        $override = trim((string) config('services.novupay.public_url', ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function notificationUrl(): string
    {
        return $this->publicBaseUrl() . '/webhooks/novupay';
    }

    public function redirectUrl(int|string $orderId): string
    {
        return $this->publicBaseUrl() . '/orders/' . $orderId . '/complete';
    }

    public function checkoutStartUrl(string $referenceNo): string
    {
        return $this->publicBaseUrl() . '/payments/' . rawurlencode($referenceNo) . '/checkout';
    }

    public function completeUrlForBill(Bill $bill): string
    {
        return $this->redirectUrl($bill->id);
    }

    public function hostedCheckoutUrl(string $uid): string
    {
        $uid = trim($uid, '/');

        return $this->checkoutBase() . '/checkout/' . $uid . '/';
    }

    /**
     * Force hosted checkout onto novu-pay.com. Never use merchant subdomains.
     */
    public function normalizeCheckoutUrl(?string $url, ?string $uid = null): ?string
    {
        $uid = $uid ? trim($uid, '/') : null;

        if (is_string($url) && $url !== '') {
            if (preg_match('#/checkout/([^/]+)/?#', $url, $matches) === 1) {
                $uid = $uid ?: $matches[1];
            }
        }

        if ($uid) {
            return $this->hostedCheckoutUrl($uid);
        }

        return null;
    }

    public function formatContact(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '63') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return $digits;
        }

        return null;
    }

    public function signatureFor(string $reqId, string $status): string
    {
        return sha1($reqId . $status . '{' . $this->clientSecret() . '}');
    }

    public function signatureIsValid(string $reqId, string $status, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->clientSecret() === '') {
            return false;
        }

        return hash_equals($this->signatureFor($reqId, $status), $signature);
    }

    public function minAmount(): float
    {
        $configured = (float) config('services.novupay.min_amount', self::MIN_AMOUNT);

        return $configured > 0 ? $configured : self::MIN_AMOUNT;
    }

    /**
     * Create a QR Ph hosted checkout for a bill and persist the NovuPay uid.
     *
     * @param  array{payor?: string, name?: string, email?: string, contact?: string, account_no?: string}  $customer
     * @return array{ok: bool, checkout_url?: string, uid?: string, req_id?: string, amount?: float, already_paid?: bool, complete_url?: string, message?: string, status?: int}
     */
    public function startForReference(string $referenceNo, array $customer = []): array
    {
        if (!$this->isConfigured()) {
            Log::error('NovuPay checkout skipped: missing server credentials');

            return [
                'ok' => false,
                'message' => 'Online payment is not configured.',
                'status' => 503,
            ];
        }

        $bill = Bill::with('reading.concessionaire.user')
            ->where('reference_no', $referenceNo)
            ->first();

        if (!$bill) {
            return [
                'ok' => false,
                'message' => 'Bill not found.',
                'status' => 404,
            ];
        }

        if ($bill->isPaid) {
            return [
                'ok' => true,
                'already_paid' => true,
                'complete_url' => $this->completeUrlForBill($bill),
                'message' => 'This bill has already been paid.',
            ];
        }

        $billData = $this->billDataForAmount($bill, $referenceNo);
        if (PaymentController::isSoaQrVoided($billData)) {
            return [
                'ok' => false,
                'message' => 'Online payment is no longer available for this bill because it is past the due date. Please visit the district office or request an updated SOA.',
                'status' => 422,
            ];
        }

        $amount = PaymentController::resolveOnlinePayableAmount($billData);
        $minAmount = $this->minAmount();
        if ($amount + 0.001 < $minAmount) {
            return [
                'ok' => false,
                'message' => 'QR Ph payments require a minimum of ₱' . number_format($minAmount, 2) . '.',
                'status' => 422,
            ];
        }

        $user = optional(optional($bill->reading)->concessionaire)->user;
        $name = trim((string) ($customer['name'] ?? $customer['payor'] ?? $user->name ?? $bill->payor_name ?? 'Sta. Rita Customer'));
        $email = trim((string) ($customer['email'] ?? $user->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'srwdsystem2023@gmail.com';
        }
        $contact = $this->formatContact($customer['contact'] ?? $user->contact_no ?? null);

        $reqId = (string) $bill->reference_no;
        $billData['account_no'] = $this->accountNumber(
            $billData,
            $customer['account_no'] ?? optional($bill->reading)->account_no
        );
        $details = $this->checkoutDetails($billData, $reqId, $amount);
        $payload = [
            'req_id' => $reqId,
            'client_id' => $this->clientId(),
            'amount' => round($amount, 2),
            'description' => $details['description'],
            'email' => $email,
            'name' => $name,
            'notification_url' => $this->notificationUrl(),
            'redirect_url' => $this->redirectUrl($bill->id),
            'enabled_channels' => config('services.novupay.enabled_channels', ['qrph']),
            'cart' => $details['cart'],
        ];

        if ($details['param1'] !== '') {
            $payload['param1'] = $details['param1'];
        }
        if ($details['param2'] !== '') {
            $payload['param2'] = $details['param2'];
        }

        if ($contact) {
            $payload['contact'] = $contact;
        }

        $created = $this->createCheckout($payload);
        if (!$created['ok']) {
            return $created;
        }

        $bill->update([
            'payment_method' => 'online',
            'initiated_at' => now(),
            'novupay_req_id' => $reqId,
            'novupay_uid' => $created['uid'] ?? null,
        ]);

        return [
            'ok' => true,
            'checkout_url' => $created['checkout_url'],
            'uid' => $created['uid'] ?? null,
            'req_id' => $reqId,
            'amount' => round($amount, 2),
        ];
    }

    /**
     * POST /api/open/checkout/ — never send client_secret.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, checkout_url?: string, uid?: string, message?: string, status?: int}
     */
    public function createCheckout(array $payload): array
    {
        unset($payload['client_secret'], $payload['api_key'], $payload['secret']);

        try {
            $response = $this->novuPayHttp([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-key' => $this->apiKey(),
            ], 30)->post($this->apiUrl() . '/api/open/checkout/', $payload);
        } catch (Throwable $e) {
            Log::error('NovuPay checkout request failed', [
                'error' => $e->getMessage(),
                'api_url' => $this->apiUrl(),
            ]);

            return [
                'ok' => false,
                'message' => 'Unable to reach NovuPay at ' . $this->apiUrl() . '.',
                'status' => 502,
            ];
        }

        if ($response->failed()) {
            Log::error('NovuPay checkout API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'req_id' => $payload['req_id'] ?? null,
            ]);

            $message = $response->json('message') ?: 'Failed to create NovuPay checkout.';

            return [
                'ok' => false,
                'message' => is_string($message) ? $message : 'Failed to create NovuPay checkout.',
                'status' => $response->status() >= 400 ? $response->status() : 502,
            ];
        }

        $data = $response->json();
        $uid = is_array($data) ? ($data['uid'] ?? null) : null;
        $checkoutUrl = $this->normalizeCheckoutUrl(
            is_array($data) ? ($data['checkout_url'] ?? null) : null,
            is_string($uid) ? $uid : null
        );

        if (!$checkoutUrl) {
            Log::error('NovuPay checkout missing checkout_url', ['body' => $response->body()]);

            return [
                'ok' => false,
                'message' => 'NovuPay did not return a checkout URL.',
                'status' => 502,
            ];
        }

        return [
            'ok' => true,
            'checkout_url' => $checkoutUrl,
            'uid' => is_string($uid) ? $uid : null,
        ];
    }

    /**
     * GET /api/check_code/?client_id=&req_id=&mode=API
     *
     * @return array<string, mixed>|null
     */
    public function checkCode(string $reqId): ?array
    {
        if (!$this->isConfigured() || $reqId === '') {
            return null;
        }

        try {
            $response = $this->novuPayHttp([
                'Accept' => 'application/json',
                'x-api-key' => $this->apiKey(),
            ], 15)->get($this->apiUrl() . '/api/check_code/', [
                'client_id' => $this->clientId(),
                'req_id' => $reqId,
                'mode' => 'API',
            ]);
        } catch (Throwable $e) {
            Log::warning('NovuPay check_code request failed', [
                'req_id' => $reqId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            return $response->json() ?: null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, http: int, message: string}
     */
    public function markPaidFromWebhook(array $payload): array
    {
        $reqId = (string) ($payload['req_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $signature = isset($payload['signature']) ? (string) $payload['signature'] : null;

        if ($reqId === '' || $status === '') {
            return ['ok' => false, 'http' => 400, 'message' => 'Invalid payload'];
        }

        if (!$this->signatureIsValid($reqId, $status, $signature)) {
            Log::warning('NovuPay webhook signature mismatch', ['req_id' => $reqId]);

            return ['ok' => false, 'http' => 400, 'message' => 'Invalid signature'];
        }

        if (strtolower($status) !== 'paid') {
            Log::info('NovuPay webhook ignored; status not paid', [
                'req_id' => $reqId,
                'status' => $status,
            ]);

            return ['ok' => true, 'http' => 200, 'message' => 'Ignored'];
        }

        $bill = $this->findBillByReqId($reqId);
        if (!$bill) {
            Log::warning('NovuPay webhook: bill not found', ['req_id' => $reqId]);

            return ['ok' => false, 'http' => 404, 'message' => 'Bill not found'];
        }

        if ($bill->isPaid) {
            return ['ok' => true, 'http' => 200, 'message' => 'Already paid'];
        }

        $this->settleBill($bill, $payload);

        return ['ok' => true, 'http' => 200, 'message' => 'Bill updated'];
    }

    /**
     * @return array{bill: Bill, status: string, amount: string, paid: bool}
     */
    public function completeOrder(int $billId): array
    {
        $bill = Bill::with('reading.concessionaire.user')->findOrFail($billId);

        if (!$bill->isPaid) {
            $reqId = (string) ($bill->novupay_req_id ?: $bill->reference_no);
            $remote = $this->checkCode($reqId);
            $remoteStatus = strtolower((string) ($remote['status'] ?? ''));
            if ($remoteStatus === 'paid') {
                $this->settleBill($bill, [
                    'req_id' => $reqId,
                    'amount' => $remote['amount'] ?? null,
                    'name' => optional(optional(optional($bill->reading)->concessionaire)->user)->name
                        ?? $bill->payor_name,
                ]);
                $bill->refresh();
            }
        }

        $paid = (bool) $bill->isPaid;

        return [
            'bill' => $bill,
            'status' => $paid ? 'paid' : 'pending',
            'amount' => number_format((float) ($bill->amount_paid ?? $bill->total ?? 0), 2),
            'paid' => $paid,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function settleBill(Bill $bill, array $payload = []): void
    {
        $reported = isset($payload['amount']) && is_numeric($payload['amount'])
            ? (float) $payload['amount']
            : null;

        $payor = StaritaNovupayBillService::firstUsablePayor(
            $payload['name'] ?? null,
            $payload['buyer_name'] ?? null,
            data_get($payload, 'extras.name'),
            $bill->payor_name,
            optional(optional(optional($bill->reading)->concessionaire)->user)->name
        );

        app(BillSettlementService::class)->settlePaidBillChain(
            $bill,
            [
                'amount_paid' => $reported,
                'payor_name' => $payor,
                'date_paid' => now(),
                'payment_method' => 'online',
            ],
            [
                'payor_name' => $payor,
                'date_paid' => now(),
                'payment_method' => 'online',
            ]
        );

        $bill->refresh();
        $bill->update([
            'novupay_req_id' => $bill->novupay_req_id ?: ($payload['req_id'] ?? $bill->reference_no),
        ]);

        app(StaritaNovupayBillService::class)->upsertFromLocalBill($bill);

        Log::info('NovuPay: bill marked as paid', [
            'bill_id' => $bill->id,
            'reference_no' => $bill->reference_no,
            'req_id' => $payload['req_id'] ?? $bill->novupay_req_id,
        ]);
    }

    public function findBillByReqId(string $reqId): ?Bill
    {
        return Bill::query()
            ->with('reading.concessionaire.user')
            ->where(function ($q) use ($reqId) {
                $q->where('novupay_req_id', $reqId)
                    ->orWhere('reference_no', $reqId);
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function billDataForAmount(Bill $bill, string $referenceNo): array
    {
        $fromMeter = app(MeterService::class)::getBill($referenceNo);
        if (is_array($fromMeter) && isset($fromMeter['current_bill']) && is_array($fromMeter['current_bill'])) {
            return $fromMeter['current_bill'];
        }

        return [
            'id' => $bill->id,
            'total' => $bill->total,
            'amount' => $bill->amount,
            'amount_after_due' => $bill->amount_after_due,
            'penalty' => $bill->penalty,
            'discount' => $bill->discount,
            'due_date' => $bill->due_date,
            'isPaid' => $bill->isPaid,
            'reference_no' => $bill->reference_no,
            'bill_period_from' => $bill->bill_period_from,
            'bill_period_to' => $bill->bill_period_to,
            'account_no' => optional($bill->reading)->account_no,
        ];
    }

    /**
     * Description, cart line items, and custom params shown on novu-pay.com checkout.
     *
     * @return array{description: string, cart: array<int, array{name: string, amount: float, quantity: int}>, param1: string, param2: string}
     */
    public function checkoutDetails(array $billData, string $referenceNo, float $payable): array
    {
        $payable = round($payable, 2);
        $penalty = PaymentController::resolveAppliedPenaltyAmount($billData);
        $current = round(max($payable - $penalty, 0), 2);
        $month = $this->billingMonthLabel($billData);
        $accountNo = $this->accountNumber($billData);
        $prefix = trim((string) config('services.novupay.description', 'Sta-Rita Water District bill'));
        if ($prefix === '') {
            $prefix = 'Sta-Rita Water District bill';
        }

        $parts = [$prefix];
        if ($referenceNo !== '') {
            $parts[] = 'Ref: ' . $referenceNo;
        }
        if ($accountNo !== '') {
            $parts[] = 'Acct: ' . $accountNo;
        }
        if ($month !== '') {
            $parts[] = $month;
        }
        $parts[] = 'Amount PHP ' . number_format($current, 2, '.', '');
        if ($penalty > 0.001) {
            $parts[] = 'Penalty PHP ' . number_format($penalty, 2, '.', '');
        }

        $description = implode(' | ', $parts);
        if (mb_strlen($description) > 250) {
            $description = rtrim(mb_substr($description, 0, 247)) . '...';
        }

        $billName = $month !== '' ? 'Water bill — ' . $month : 'Water bill';
        if ($accountNo !== '') {
            $billName .= ' (Acct: ' . $accountNo . ')';
        }
        $cart = [];
        if ($current > 0.001) {
            $cart[] = [
                'name' => $billName,
                'amount' => $current,
                'quantity' => 1,
            ];
        }
        if ($penalty > 0.001) {
            $cart[] = [
                'name' => 'Penalty fee',
                'amount' => $penalty,
                'quantity' => 1,
            ];
        }
        if ($cart === []) {
            $cart[] = [
                'name' => $billName,
                'amount' => $payable,
                'quantity' => 1,
            ];
        }

        $param2Parts = [];
        if ($month !== '') {
            $param2Parts[] = $month;
        }
        if ($accountNo !== '') {
            $param2Parts[] = 'Acct: ' . $accountNo;
        }

        return [
            'description' => $description,
            'cart' => $cart,
            'param1' => $referenceNo,
            'param2' => implode(' | ', $param2Parts),
        ];
    }

    public function accountNumber(array $billData, $fallback = null): string
    {
        $candidates = [
            $billData['account_no'] ?? null,
            data_get($billData, 'reading.account_no'),
            data_get($billData, 'client.account_no'),
            $fallback,
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '';
    }

    public function billingMonthLabel(array $billData): string
    {
        $raw = $billData['bill_period_to'] ?? $billData['bill_period_from'] ?? null;
        if ($raw === null || $raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->timezone('Asia/Manila')->format('F Y');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('services.novupay.api_url'), '/');
    }

    /**
     * Keep Host/SNI as api.novu-pay.com. Optionally pin the TCP IP when LAN
     * clients cannot hairpin the public VIP (not a DNS change).
     *
     * @param  array<string, string>  $headers
     */
    private function novuPayHttp(array $headers, int $timeout)
    {
        $pending = Http::timeout($timeout)->withHeaders($headers);
        $ip = trim((string) config('services.novupay.api_resolve', ''));
        $host = parse_url($this->apiUrl(), PHP_URL_HOST);

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && is_string($host) && $host !== '') {
            $pending = $pending->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => [
                        sprintf('%s:443:%s', $host, $ip),
                        sprintf('%s:80:%s', $host, $ip),
                    ],
                ],
            ]);
        }

        return $pending;
    }

    private function checkoutBase(): string
    {
        $default = 'https://novu-pay.com';
        $base = rtrim((string) config('services.novupay.checkout_base', $default), '/');
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));

        if (in_array($host, ['novu-pay.com', 'www.novu-pay.com'], true)) {
            return $host === 'www.novu-pay.com' ? $default : $base;
        }

        return $default;
    }

    private function apiKey(): string
    {
        return trim((string) config('services.novupay.api_key'));
    }

    private function clientId(): string
    {
        return trim((string) config('services.novupay.client_id'));
    }

    private function clientSecret(): string
    {
        return trim((string) config('services.novupay.client_secret'));
    }
}
