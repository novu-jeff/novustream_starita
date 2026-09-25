<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\BillSettlementService;
use App\Services\GenerateService;
use App\Services\MeterService;
use App\Services\StaritaNovupayBillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HitpayController extends Controller
{
    protected $meterService;
    protected $generateService;

    public function __construct(MeterService $meterService, GenerateService $generateService)
    {
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

    public function redirect(Request $request)
    {
        $status = strtolower($request->get('status'));
        $amount = (float)$request->get('amount', 0);

        // Find your local bill by hitpay_reference
        $hitpay_reference = $request->get('reference')
            ?? $request->get('reference_number'); // fallback

        $payment_id = $request->get('payment_id');

        $bill = Bill::where('hitpay_reference', $hitpay_reference)
            ->orWhere('hitpay_payment_id', $payment_id)
            ->orWhere('reference_no', $hitpay_reference)
            ->first();

        $payor = StaritaNovupayBillService::firstUsablePayor(
            $request->get('name'),
            optional($bill)->payor_name
        );


        \Log::info('HitPay redirect received', $request->all());



        if (!$bill) {
            \Log::warning("HitPay redirect: Bill not found for reference {$hitpay_reference}");
            return redirect()->route('payments.redirect', [
                'reference' => $hitpay_reference,
                'status' => 'failed'
            ]);
        }

        // Process only successful payments
        if (in_array($status, ['completed', 'succeeded'])) {
            try {
                $payload = [
                    'payment_amount' => $amount,
                    'payor' => $payor,
                    'payment_id' => $payment_id,
                    'for_advances' => false,
                ];

                // Call existing service
                $result = $this->meterService->getBill($bill->reference_no, $payload, true);

                if (isset($result['error'])) {
                    return redirect()->route('payments.redirect', [
                        'reference' => $hitpay_reference,
                        'status' => 'failed'
                    ]);
                }

                $data = $result['data'];
                $now = Carbon::now()->format('Y-m-d H:i:s');

                \Log::info('HitPay redirect reached bill update', [
                    'bill_id' => $bill->id,
                    'status' => $status,
                    'amount' => $amount
                ]);
                app(BillSettlementService::class)->settlePaidBillChain(
                    $bill,
                    [
                        'amount_paid' => $amount > 0 ? $amount : null,
                        'payor_name' => $payor,
                        'date_paid' => $now,
                        'payment_method' => 'online',
                        'hitpay_payment_id' => $payment_id,
                    ],
                    [
                        'payor_name' => $payor,
                        'date_paid' => $now,
                        'payment_method' => 'online',
                    ]
                );

                \Log::info('Bill updated', $bill->only(['id', 'isPaid', 'amount_paid', 'payment_method']));

                // ✅ Redirect user to your existing redirect handler
                return redirect()->route('payments.redirect', [
                    'reference' => $hitpay_reference,
                    'status' => 'completed'
                ]);

            } catch (\Exception $e) {
                \Log::error('HitPay redirect error: ' . $e->getMessage());
                return redirect()->route('payments.redirect', [
                    'reference' => $hitpay_reference,
                    'status' => 'failed'
                ]);
            }
        }

        // If not completed, still send user to your redirect page
        return redirect()->route('payments.redirect', [
            'reference' => $hitpay_reference,
            'status' => $status
        ]);
    }

}
