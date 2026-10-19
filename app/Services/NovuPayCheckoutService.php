<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Bill;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NovuPayCheckoutService
{
    public function __construct(
        private NovuPayClient $client,
        private BillSettlementService $billSettlementService,
        private StaritaNovupayBillService $staritaNovupayBillService
    ) {
    }

    /**
     * Create or reuse a hosted NovuPay checkout for a Sta. Rita bill.
     *
     * @return array{ok: bool, checkout_url?: string, error?: string, reused?: bool, data?: array}
     */
    public function startCheckout(string $referenceNo, array $billData, array $customer = []): array
    {
        $amount = PaymentController::resolveOnlinePayableAmount($billData);
        if ($amount < $this->client->minAmount()) {
            return [
                'ok' => false,
                'error' => 'Amount must be at least PHP ' . number_format($this->client->minAmount(), 2) . '.',
            ];
        }

        $bill = Bill::where('reference_no', $referenceNo)->first();
        if ($bill && $bill->isPaid) {
            return ['ok' => false, 'error' => 'This bill has already been paid.'];
        }

        $accountNo = (string) ($customer['account_no'] ?? data_get($billData, 'account_no') ?? '');
        if ($accountNo === '' && $bill) {
            $bill->loadMissing('reading');
            $accountNo = (string) ($bill->reading->account_no ?? '');
        }
        $email = $this->chooseCustomerEmail($customer['email'] ?? null, $accountNo);

        if ($bill && !empty($bill->novupay_uid) && !empty($bill->novupay_req_id)) {
            $storedAmount = round((float) ($bill->novupay_amount ?? 0), 2);
            if (abs($storedAmount - $amount) < 0.01) {
                $statusResult = $this->client->checkStatus((string) $bill->novupay_req_id);
                $remoteStatus = strtolower((string) data_get($statusResult, 'data.status', ''));
                if ($statusResult['ok'] && $remoteStatus === 'pending' && $this->checkoutEmailMatches((string) $bill->novupay_req_id, $email)) {
                    return [
                        'ok' => true,
                        'reused' => true,
                        'checkout_url' => $this->client->buildCheckoutUrl((string) $bill->novupay_uid),
                        'data' => [
                            'id' => $bill->novupay_id,
                            'uid' => $bill->novupay_uid,
                            'req_id' => $bill->novupay_req_id,
                            'amount' => $amount,
                        ],
                    ];
                }

                if ($statusResult['ok'] && $remoteStatus === 'paid') {
                    $this->fulfillPaidBill($bill, [
                        'req_id' => $bill->novupay_req_id,
                        'amount' => data_get($statusResult, 'data.amount', $amount),
                        'ref_code' => data_get($statusResult, 'data.ref_code'),
                    ]);

                    return ['ok' => false, 'error' => 'This bill has already been paid.'];
                }
            }
        }

        $payor = $customer['name']
            ?? data_get($billData, 'payor')
            ?? data_get($billData, 'account_name')
            ?? 'Sta. Rita Customer';
        $contact = $customer['contact'] ?? $customer['contact_no'] ?? null;
        $dueDate = $billData['due_date'] ?? null;

        Log::info('NovuPay checkout using customer email', [
            'reference_no' => $referenceNo,
            'account_no' => $accountNo,
            'email' => $email,
        ]);

        $reqId = $this->client->makeReqId($referenceNo);
        $result = $this->client->createCheckout([
            'req_id' => $reqId,
            'amount' => $amount,
            'description' => 'Sta. Rita WD bill ' . $referenceNo . ($accountNo !== '' ? ' / Acct ' . $accountNo : ''),
            'email' => $email,
            'name' => $payor,
            'contact' => $contact,
            'expiry' => $this->client->expiryHours($dueDate),
            'param1' => $referenceNo,
            'param2' => $accountNo !== '' ? (string) $accountNo : null,
            'notification_url' => $this->client->notificationUrl(),
            'redirect_url' => $this->client->redirectUrl() . '?req_id=' . rawurlencode($reqId),
        ]);

        if (!$result['ok']) {
            return [
                'ok' => false,
                'error' => $result['error'] ?? 'Unable to create NovuPay checkout.',
            ];
        }

        $data = $result['data'];
        Cache::put($this->checkoutEmailCacheKey((string) ($data['req_id'] ?? $reqId)), $email, now()->addDays(40));
        $this->persistCheckoutOnBill($bill, $referenceNo, $data, $billData);

        return [
            'ok' => true,
            'reused' => false,
            'checkout_url' => (string) $data['checkout_url'],
            'data' => $data,
        ];
    }

    public function persistCheckoutOnBill(?Bill $bill, string $referenceNo, array $checkout, array $billData = []): ?Bill
    {
        if (!$bill) {
            $bill = Bill::where('reference_no', $referenceNo)->first();
        }

        if (!$bill) {
            Log::info('NovuPay checkout created without local bill row', [
                'reference_no' => $referenceNo,
                'req_id' => $checkout['req_id'] ?? null,
            ]);

            return null;
        }

        $updates = [
            'payment_method' => 'online',
            'initiated_at' => now(),
        ];

        if (Schema::hasColumn('bill', 'novupay_id')) {
            $updates['novupay_id'] = $checkout['id'] ?? null;
        }
        if (Schema::hasColumn('bill', 'novupay_uid')) {
            $updates['novupay_uid'] = $checkout['uid'] ?? null;
        }
        if (Schema::hasColumn('bill', 'novupay_req_id')) {
            $updates['novupay_req_id'] = $checkout['req_id'] ?? null;
        }
        if (Schema::hasColumn('bill', 'novupay_amount')) {
            $updates['novupay_amount'] = $checkout['amount'] ?? null;
        }
        if (Schema::hasColumn('bill', 'novupay_status')) {
            $updates['novupay_status'] = 'pending';
        }

        $bill->update($updates);

        return $bill->fresh();
    }

    /**
     * Fulfill a paid checkout idempotently.
     *
     * @return array{ok: bool, settled?: bool, message?: string}
     */
    public function fulfillPaidBill(Bill $bill, array $payment = []): array
    {
        if ($bill->isPaid) {
            return ['ok' => true, 'settled' => false, 'message' => 'already_paid'];
        }

        $amount = isset($payment['amount']) ? round((float) $payment['amount'], 2) : 0.0;
        $storedAmount = round((float) ($bill->novupay_amount ?? 0), 2);
        if ($storedAmount > 0 && $amount > 0 && !NovuPayClient::reportedAmountMatchesCheckout($storedAmount, $amount)) {
            Log::warning('NovuPay fulfillment skipped: amount mismatch', [
                'reference_no' => $bill->reference_no,
                'stored' => $storedAmount,
                'paid' => $amount,
            ]);

            return ['ok' => false, 'settled' => false, 'message' => 'amount_mismatch'];
        }

        if ($storedAmount > 0 && !$this->checkoutAmountMatchesBill($bill, $storedAmount)) {
            Log::warning('NovuPay fulfillment skipped: checkout amount is not the bill amount due', [
                'reference_no' => $bill->reference_no,
                'stored' => $storedAmount,
                'paid' => $amount,
                'total' => $bill->total,
            ]);

            return ['ok' => false, 'settled' => false, 'message' => 'amount_mismatch'];
        }

        $payor = $payment['name']
            ?? $bill->payor_name
            ?? 'Sta. Rita Customer';
        $paidAt = $payment['paid_at'] ?? now();
        // Record the bill amount. The reported figure includes NovuPay's ₱10 fee.
        $amountPaid = $storedAmount > 0 ? $storedAmount : ($amount > 0 ? $amount : null);

        $this->billSettlementService->settlePaidBillChain(
            $bill,
            [
                'amount_paid' => $amountPaid,
                'payor_name' => $payor,
                'date_paid' => $paidAt,
                'payment_method' => 'online',
            ],
            [
                'payor_name' => $payor,
                'date_paid' => $paidAt,
                'payment_method' => 'online',
            ]
        );

        $bill->refresh();

        $meta = [];
        if (Schema::hasColumn('bill', 'novupay_status')) {
            $meta['novupay_status'] = 'paid';
        }
        if (Schema::hasColumn('bill', 'novupay_ref_code') && !empty($payment['ref_code'])) {
            $meta['novupay_ref_code'] = (string) $payment['ref_code'];
        }
        if (Schema::hasColumn('bill', 'novupay_req_id') && !empty($payment['req_id'])) {
            $meta['novupay_req_id'] = (string) $payment['req_id'];
        }
        if (!empty($meta)) {
            $bill->update($meta);
        }

        $this->staritaNovupayBillService->upsertFromLocalBill($bill);

        Log::info('NovuPay fulfillment: bill marked paid', [
            'bill_id' => $bill->id,
            'reference_no' => $bill->reference_no,
            'req_id' => $payment['req_id'] ?? $bill->novupay_req_id,
        ]);

        return ['ok' => true, 'settled' => true, 'message' => 'settled'];
    }

    public function findBillByReqId(string $reqId): ?Bill
    {
        if ($reqId === '' || !Schema::hasColumn('bill', 'novupay_req_id')) {
            return null;
        }

        return Bill::query()
            ->with('reading')
            ->where('novupay_req_id', $reqId)
            ->orderBy('id')
            ->first();
    }

    public function findBillByReference(string $referenceNo): ?Bill
    {
        if ($referenceNo === '') {
            return null;
        }

        $bill = Bill::query()
            ->with('reading')
            ->where('reference_no', $referenceNo)
            ->first();
        if ($bill) {
            return $bill;
        }

        if (Schema::hasColumn('bill', 'novupay_req_id')) {
            $bill = Bill::query()
                ->with('reading')
                ->where('novupay_req_id', $referenceNo)
                ->orderBy('id')
                ->first();
            if ($bill) {
                return $bill;
            }
        }

        $baseReference = NovuPayClient::referenceFromReqId($referenceNo);
        if ($baseReference === '' || $baseReference === $referenceNo) {
            return null;
        }

        return Bill::query()
            ->with('reading')
            ->where('reference_no', $baseReference)
            ->first();
    }

    public function checkoutStartUrl(string $referenceNo): string
    {
        return rtrim((string) config('app.url'), '/') . '/payments/' . rawurlencode($referenceNo) . '/checkout';
    }

    public function completeUrlForBill(Bill $bill): string
    {
        return rtrim((string) config('app.url'), '/') . '/orders/' . $bill->id . '/complete';
    }

    /**
     * @param  array{payor?: string, name?: string, email?: string, contact?: string, account_no?: string}  $customer
     * @return array{ok: bool, checkout_url?: string, already_paid?: bool, complete_url?: string, message?: string, status?: int}
     */
    public function startForReference(string $referenceNo, array $customer = []): array
    {
        $bill = Bill::with('reading')->where('reference_no', $referenceNo)->first();
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

        $result = $this->startCheckout($referenceNo, $billData, $customer);
        if (empty($result['ok']) || empty($result['checkout_url'])) {
            return [
                'ok' => false,
                'message' => $result['error'] ?? 'Failed to start NovuPay checkout.',
                'status' => 502,
            ];
        }

        return [
            'ok' => true,
            'checkout_url' => $result['checkout_url'],
            'uid' => $result['data']['uid'] ?? null,
            'req_id' => $result['data']['req_id'] ?? null,
            'amount' => $result['data']['amount'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, http: int, message: string}
     */
    public function markPaidFromWebhook(array $payload): array
    {
        $reqId = (string) ($payload['req_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $signature = isset($payload['signature']) ? (string) $payload['signature'] : '';

        if ($reqId === '' || $status === '' || $signature === '') {
            return ['ok' => false, 'http' => 400, 'message' => 'Invalid payload'];
        }

        if (!$this->client->verifyWebhookSignature($reqId, $status, $signature)) {
            Log::warning('NovuPay webhook signature mismatch', ['req_id' => $reqId]);

            return ['ok' => false, 'http' => 400, 'message' => 'Invalid signature'];
        }

        if (strtolower($status) !== 'paid') {
            return ['ok' => true, 'http' => 200, 'message' => 'Ignored'];
        }

        $referenceNo = (string) (data_get($payload, 'extras.param1') ?? data_get($payload, 'param1') ?? '');
        $bill = $this->findBillByReqId($reqId);
        if (!$bill && $referenceNo !== '') {
            $bill = $this->findBillByReference($referenceNo);
        }

        if (!$bill) {
            return ['ok' => false, 'http' => 404, 'message' => 'Bill not found'];
        }

        if ($bill->isPaid) {
            return ['ok' => true, 'http' => 200, 'message' => 'Already paid'];
        }

        $fulfilled = $this->fulfillPaidBill($bill, [
            'req_id' => $reqId,
            'amount' => $payload['amount'] ?? null,
            'ref_code' => $payload['ref_code'] ?? null,
            'name' => data_get($payload, 'name') ?? data_get($payload, 'extras.name') ?? $bill->payor_name,
            'paid_at' => $payload['paid_at'] ?? now(),
        ]);

        if (empty($fulfilled['ok'])) {
            return [
                'ok' => false,
                'http' => 422,
                'message' => $fulfilled['message'] ?? 'Unable to settle bill',
            ];
        }

        return ['ok' => true, 'http' => 200, 'message' => 'Bill updated'];
    }

    /**
     * @return array{bill: Bill, status: string, amount: string, paid: bool}
     */
    public function completeOrder(int $billId): array
    {
        $bill = Bill::with('reading')->findOrFail($billId);

        if (!$bill->isPaid && !empty($bill->novupay_req_id)) {
            $statusResult = $this->client->checkStatus((string) $bill->novupay_req_id);
            $remoteStatus = strtolower((string) data_get($statusResult, 'data.status', ''));
            if (!empty($statusResult['ok']) && $remoteStatus === 'paid') {
                $this->fulfillPaidBill($bill, [
                    'req_id' => $bill->novupay_req_id,
                    'amount' => data_get($statusResult, 'data.amount'),
                    'ref_code' => data_get($statusResult, 'data.ref_code'),
                    'name' => $bill->payor_name,
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
     * The hosted checkout must be for this bill's amount due, not a smaller
     * slice of it. A ₱10 NovuPay fee on a ₱160 checkout must not clear a ₱336 bill.
     */
    private function checkoutAmountMatchesBill(Bill $bill, float $storedAmount): bool
    {
        $candidates = array_unique(array_filter([
            round((float) ($bill->total ?? 0), 2),
            round(max((float) ($bill->total ?? 0) - BillSettlementService::numericBillAttribute($bill, 'discount'), 0), 2),
            round((float) ($bill->amount ?? 0), 2),
            round((float) ($bill->amount_after_due ?? 0), 2),
        ], fn ($value) => $value > 0));

        foreach ($candidates as $candidate) {
            if (abs($candidate - $storedAmount) < 0.06) {
                return true;
            }
        }

        return false;
    }

    private function placeholderCustomerEmail(): string
    {
        return 'srwdsystem2023@gmail.com';
    }

    private function isUsableCustomerEmail(?string $email): bool
    {
        $email = trim((string) $email);
        if ($email === '' || strcasecmp($email, $this->placeholderCustomerEmail()) === 0) {
            return false;
        }

        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Use the concessionaire account email when one is set up.
     * srwdsystem2023@gmail.com is only for accounts that have no email yet.
     */
    private function chooseCustomerEmail(?string $supplied, string $accountNo): string
    {
        $accountEmail = $this->lookupAccountEmail($accountNo);
        if ($accountEmail !== null) {
            return $accountEmail;
        }

        $supplied = trim((string) $supplied);
        if ($this->isUsableCustomerEmail($supplied)) {
            return $supplied;
        }

        return $this->placeholderCustomerEmail();
    }

    private function lookupAccountEmail(string $accountNo): ?string
    {
        if ($accountNo === '') {
            return null;
        }

        try {
            $email = DB::table('concessioner_accounts as ca')
                ->leftJoin('users as u', 'u.id', '=', 'ca.user_id')
                ->where('ca.account_no', $accountNo)
                ->value('u.email');

            $email = trim((string) $email);

            return $this->isUsableCustomerEmail($email) ? $email : null;
        } catch (\Throwable $e) {
            Log::warning('NovuPay checkout: account email lookup failed', [
                'account_no' => $accountNo,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function checkoutEmailCacheKey(string $reqId): string
    {
        return 'novupay.checkout_email.' . $reqId;
    }

    private function checkoutEmailMatches(string $reqId, string $email): bool
    {
        $previous = Cache::get($this->checkoutEmailCacheKey($reqId));

        return is_string($previous) && strcasecmp($previous, $email) === 0;
    }
}
