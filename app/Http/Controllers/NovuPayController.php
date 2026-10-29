<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Reading;
use App\Models\UserAccounts;
use App\Services\MeterService;
use App\Services\NovuPayCheckoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NovuPayController extends Controller
{
    public function __construct(private NovuPayCheckoutService $novuPay)
    {
    }

    /**
     * Partner SOA inquiry for NovuPay /srwd checkout.
     * Body: account_number, account_name. Header: X-API-KEY.
     */
    public function soaInquiry(Request $request)
    {
        $expectedKey = (string) config('services.starita_partner_api_key', '');
        $providedKey = (string) $request->header('X-API-KEY', '');

        if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $accountNumber = trim((string) $request->input('account_number', ''));
        $accountName = trim((string) $request->input('account_name', ''));

        if ($accountNumber === '' || $accountName === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Account number and account name are required.',
            ], 422);
        }

        $resolvedAccountNo = $this->resolveSoaAccountNo($accountNumber);
        if ($resolvedAccountNo === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found.',
            ], 404);
        }

        $account = UserAccounts::with('user')->where('account_no', $resolvedAccountNo)->first();
        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found.',
            ], 404);
        }

        $storedName = (string) ($account->user->name ?? '');
        if (!$this->namesMatch($storedName, $accountName)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The name does not match the account number.',
            ], 404);
        }

        $bills = MeterService::getBills($resolvedAccountNo, true, false);
        if (!is_array($bills) || count($bills) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No unpaid statement of account found.',
            ], 404);
        }

        usort($bills, static function ($a, $b) {
            $aId = (int) ($a['id'] ?? 0);
            $bId = (int) ($b['id'] ?? 0);
            if ($aId !== $bId) {
                return $bId <=> $aId;
            }

            $aPeriod = (string) ($a['bill_period_to'] ?? '');
            $bPeriod = (string) ($b['bill_period_to'] ?? '');

            return strcmp($bPeriod, $aPeriod);
        });

        $latest = $bills[0];
        $dueDateRaw = $latest['due_date'] ?? null;
        $amount = round((float) ($latest['amount'] ?? 0), 2);
        $amountAfterDue = round((float) ($latest['amount_after_due'] ?? $amount), 2);

        $payable = $amount;
        if ($dueDateRaw) {
            try {
                $due = Carbon::parse($dueDateRaw)->startOfDay();
                if (now()->startOfDay()->gt($due)) {
                    $payable = $amountAfterDue > 0 ? $amountAfterDue : $amount;
                }
            } catch (\Throwable $e) {
                // keep billed amount
            }
        }

        if ($payable <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No payable amount on the latest statement.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'account_number' => $resolvedAccountNo,
                'account_name' => $storedName,
                'reference_no' => (string) ($latest['reference_no'] ?? ''),
                'amount' => $payable,
                'due_date' => $dueDateRaw
                    ? Carbon::parse($dueDateRaw)->toDateString()
                    : null,
                'bill_period_from' => isset($latest['bill_period_from'])
                    ? Carbon::parse($latest['bill_period_from'])->toDateString()
                    : null,
                'bill_period_to' => isset($latest['bill_period_to'])
                    ? Carbon::parse($latest['bill_period_to'])->toDateString()
                    : null,
            ],
        ]);
    }

    /**
     * Offline SOA QR. Query: rn, an, am, pr, name, email, dd, aad, penalty.
     * Opens the hosted checkout for that reference, creating the bill from the
     * printed amounts when the reading has not been synced yet.
     */
    public function paymentRequest(Request $request)
    {
        $reference = trim((string) $request->input('rn', ''));
        if ($reference === '') {
            return $this->qrMessage('Payment link is incomplete', 'This QR code has no bill reference. Ask the meter reader for a new statement.', '', 422);
        }

        $printedAccount = trim((string) $request->input('an', ''));
        $accountNo = $this->resolveSoaAccountNo($printedAccount) ?? ($printedAccount !== '' ? $printedAccount : null);
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));

        $bill = Bill::where('reference_no', $reference)->first();
        if (!$bill) {
            if ($accountNo === null) {
                return $this->qrMessage(
                    'Account not found',
                    'This QR code has no account number.',
                    $reference,
                    404
                );
            }

            $bill = $this->createBillFromSoaQr($request, $reference, $accountNo, $name);
            if (!$bill) {
                return $this->qrMessage(
                    'Checkout Unavailable',
                    'This statement could not be opened for payment. The amount or meter reading on the QR code is missing.',
                    $reference,
                    422
                );
            }
        }

        $request->merge(array_filter([
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
            'account_no' => $accountNo ?? optional($bill->reading)->account_no,
        ]));

        return $this->checkout($request, $reference);
    }

    /**
     * Create a NovuPay hosted checkout (QR Ph) and redirect the browser there.
     */
    public function checkout(Request $request, string $reference_no)
    {
        $result = $this->novuPay->startForReference($reference_no, $request->only([
            'payor', 'name', 'email', 'contact', 'account_no',
        ]));

        if (!empty($result['already_paid']) && !empty($result['complete_url'])) {
            return redirect()->away($result['complete_url']);
        }

        if (empty($result['ok']) || empty($result['checkout_url'])) {
            $status = (int) ($result['status'] ?? 502);
            $message = $result['message'] ?? 'Failed to start NovuPay checkout.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], $status >= 400 ? $status : 502);
            }

            if ($request->headers->get('referer')) {
                return redirect()->back()->with('alert', [
                    'status' => 'error',
                    'message' => $message,
                ]);
            }

            return response()->view('payments.qr-voided', [
                'payload' => [
                    'title' => 'Checkout Unavailable',
                    'message' => $message,
                    'reference_no' => $reference_no,
                    'status' => 'error',
                ],
            ], $status >= 400 ? $status : 502);
        }

        return redirect()->away($result['checkout_url']);
    }

    /**
     * NovuPay postback. Verify sha1(req_id + status + "{" + client_secret + "}") before fulfilling.
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('NovuPay webhook received', [
            'req_id' => $payload['req_id'] ?? null,
            'status' => $payload['status'] ?? null,
        ]);

        $result = $this->novuPay->markPaidFromWebhook($payload);

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['http']);
    }

    /**
     * Return URL after hosted checkout. Confirms local paid status, with check_code fallback.
     */
    public function complete(int $id)
    {
        $result = $this->novuPay->completeOrder($id);
        $bill = $result['bill'];

        return view('payments.status', [
            'payload' => [
                'title' => $result['paid'] ? 'Payment Successful' : 'Payment Pending',
                'message' => $result['paid']
                    ? 'Your payment was verified and marked as paid.'
                    : 'If you already paid, this page will update after NovuPay confirms the transaction.',
                'reference_no' => $bill->reference_no,
                'status' => $result['status'],
                'amount' => $result['amount'],
                'date_paid' => $bill->date_paid
                    ? \Carbon\Carbon::parse($bill->date_paid)->format('M d, Y H:i:s')
                    : now()->format('M d, Y H:i:s'),
                'payment_id' => $bill->novupay_uid ?? $bill->novupay_req_id,
                'back_url' => url('/concessionaire/my/bills/' . $bill->reference_no),
            ],
        ]);
    }

    /**
     * The offline app prints the rate code in the account number (071-42-071455).
     * Staging stores the classification code (071-12-071455). Match the zone and
     * the last segment when that pair is unique.
     */
    private function resolveSoaAccountNo(string $accountNo): ?string
    {
        $accountNo = trim($accountNo);
        if ($accountNo === '') {
            return null;
        }

        if (DB::table('concessioner_accounts')->where('account_no', $accountNo)->exists()) {
            return $accountNo;
        }

        if (preg_match('/^(\d{3})-\d{2}-(\d+)$/', $accountNo, $matches) !== 1) {
            return null;
        }

        $found = DB::table('concessioner_accounts')
            ->where('account_no', 'like', $matches[1] . '-%-' . $matches[2])
            ->pluck('account_no');

        return $found->count() === 1 ? (string) $found->first() : null;
    }

    private function createBillFromSoaQr(Request $request, string $reference, string $accountNo, string $name): ?Bill
    {
        $amount = round((float) $request->input('am', 0), 2);
        $afterDue = round((float) $request->input('aad', 0), 2);
        $penalty = round((float) $request->input('penalty', 0), 2);
        $present = $request->input('pr');

        if ($amount <= 0 || $present === null || $present === '') {
            return null;
        }

        if ($afterDue <= 0) {
            $afterDue = round($amount + max($penalty, 0), 2);
        }

        $presentReading = (float) $present;
        $latest = Reading::where('account_no', $accountNo)
            ->where('isReRead', false)
            ->orderByDesc('id')
            ->first();
        $previousReading = $latest ? (float) $latest->present_reading : 0.0;
        if ($presentReading < $previousReading) {
            $previousReading = $presentReading;
        }

        try {
            $due = Carbon::parse((string) $request->input('dd', now()->toDateString()))->startOfDay();
        } catch (\Throwable $e) {
            $due = now()->startOfDay();
        }

        $periodTo = $due->copy()->subDays(14);
        $periodFrom = $periodTo->copy()->subMonth();
        $zone = DB::table('concessioner_accounts')->where('account_no', $accountNo)->value('zone');
        if (!$zone && preg_match('/^(\d{3})-/', $accountNo, $zoneMatch) === 1) {
            $zone = $zoneMatch[1];
        }

        return DB::transaction(function () use (
            $reference,
            $accountNo,
            $name,
            $amount,
            $afterDue,
            $penalty,
            $presentReading,
            $previousReading,
            $due,
            $periodTo,
            $periodFrom,
            $zone,
            $request
        ) {
            $existing = Bill::where('reference_no', $reference)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $reading = Reading::create([
                'zone' => $zone,
                'account_no' => $accountNo,
                'reference_no' => $reference,
                'previous_reading' => $previousReading,
                'present_reading' => $presentReading,
                'consumption' => max($presentReading - $previousReading, 0),
                'reader_name' => 'Offline SOA QR',
                'isReRead' => false,
            ]);

            $bill = Bill::create([
                'reading_id' => $reading->id,
                'reference_no' => $reference,
                'bill_period_from' => $periodFrom->toDateString(),
                'bill_period_to' => $periodTo->toDateString(),
                'previous_unpaid' => 0,
                'total' => $amount,
                'discount' => 0,
                'penalty' => $penalty,
                'amount' => $amount,
                'amount_after_due' => $afterDue,
                'isPaid' => false,
                'hasPenalty' => $penalty > 0,
                'due_date' => $due->toDateString(),
                'penalty_date' => $due->copy()->addDay()->toDateString(),
                'disconnection_date' => $due->copy()->addDays(7)->toDateString(),
                'payor_name' => $name !== '' ? $name : null,
                'isHighConsumption' => in_array(strtolower((string) $request->input('hc', 'no')), ['1', 'yes', 'true'], true),
            ]);

            Log::info('SOA QR created staging bill from offline payment link', [
                'reference_no' => $reference,
                'account_no' => $accountNo,
                'printed_account' => $request->input('an'),
                'amount' => $amount,
            ]);

            return $bill;
        });
    }

    private function qrMessage(string $title, string $message, string $reference, int $status)
    {
        return response()->view('payments.qr-voided', [
            'payload' => [
                'title' => $title,
                'message' => $message,
                'reference_no' => $reference !== '' ? $reference : '-',
                'status' => 'error',
            ],
        ], $status);
    }

    private function namesMatch(string $firstName, string $secondName): bool
    {
        $normalize = static function (string $name): string {
            $normalized = preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim($name)));

            return $normalized ?? '';
        };

        $first = $normalize($firstName);
        $second = $normalize($secondName);

        return $first !== '' && $first === $second;
    }
}
