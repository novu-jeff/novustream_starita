<?php

namespace App\Http\Controllers;

use App\Services\NovuPayCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NovuPayController extends Controller
{
    public function __construct(private NovuPayCheckoutService $novuPay)
    {
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
}
