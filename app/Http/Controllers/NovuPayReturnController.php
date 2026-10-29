<?php

namespace App\Http\Controllers;

use App\Services\NovuPayCheckoutService;
use App\Services\NovuPayClient;
use Illuminate\Http\Request;

class NovuPayReturnController extends Controller
{
    public function __construct(
        private NovuPayClient $client,
        private NovuPayCheckoutService $checkoutService
    ) {
    }

    /**
     * GET /payments/novupay/return
     * Buyer return is not proof of payment — verify with Check Status.
     */
    public function __invoke(Request $request)
    {
        $reqId = (string) ($request->query('req_id') ?? $request->input('req_id') ?? '');

        if ($reqId === '') {
            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Status Unknown',
                    'message' => 'Missing payment reference. If you completed payment, please wait a moment or contact the district office.',
                    'reference_no' => '-',
                    'status' => 'pending',
                    'amount' => '0.00',
                ],
            ]);
        }

        $bill = $this->checkoutService->findBillByReqId($reqId);
        $statusResult = $this->client->checkStatus($reqId);
        $remoteStatus = strtolower((string) data_get($statusResult, 'data.status', ''));
        $amount = data_get($statusResult, 'data.amount');
        $refCode = data_get($statusResult, 'data.ref_code');
        $referenceNo = $bill?->reference_no
            ?? (string) data_get($statusResult, 'data.param1', $reqId);

        if ($bill && $bill->isPaid) {
            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Successful',
                    'message' => 'Your payment has been recorded.',
                    'reference_no' => $bill->reference_no,
                    'status' => 'paid',
                    'amount' => number_format((float) ($bill->amount_paid ?? $bill->novupay_amount ?? $bill->amount ?? 0), 2),
                    'date_paid' => $bill->date_paid ?? now()->format('M d, Y h:i A'),
                    'payment_id' => $bill->novupay_ref_code ?? $bill->novupay_uid,
                ],
            ]);
        }

        if ($statusResult['ok'] && $remoteStatus === 'paid') {
            if ($bill) {
                $this->checkoutService->fulfillPaidBill($bill, [
                    'req_id' => $reqId,
                    'amount' => $amount,
                    'ref_code' => $refCode,
                ]);
                $bill->refresh();
            }

            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Successful',
                    'message' => 'Your QR Ph payment was confirmed.',
                    'reference_no' => $referenceNo,
                    'status' => 'paid',
                    'amount' => number_format((float) ($amount ?? $bill?->novupay_amount ?? 0), 2),
                    'date_paid' => now()->format('M d, Y h:i A'),
                    'payment_id' => $refCode,
                ],
            ]);
        }

        if ($statusResult['ok'] && in_array($remoteStatus, ['expired', 'cancelled', 'canceled'], true)) {
            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment ' . ucfirst($remoteStatus),
                    'message' => 'This checkout is no longer available. Please start a new online payment from your bill.',
                    'reference_no' => $referenceNo,
                    'status' => $remoteStatus === 'canceled' ? 'cancelled' : $remoteStatus,
                    'amount' => number_format((float) ($amount ?? $bill?->novupay_amount ?? 0), 2),
                ],
            ]);
        }

        // Pending, 404, or unreachable — do not treat as paid.
        return view('payments.status', [
            'payload' => [
                'title' => 'Payment Pending',
                'message' => 'We have not confirmed your payment yet. If you already paid via QR Ph, please wait a moment and refresh this page.',
                'reference_no' => $referenceNo,
                'status' => 'pending',
                'amount' => number_format((float) ($amount ?? $bill?->novupay_amount ?? 0), 2),
            ],
        ]);
    }
}
