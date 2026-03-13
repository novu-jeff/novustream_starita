<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Services\BillSettlementService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallbackController extends Controller {

    public $meterService;
    public $billSettlementService;

    public function __construct(MeterService $meterService, BillSettlementService $billSettlementService) {
        $this->meterService = $meterService;
        $this->billSettlementService = $billSettlementService;
    }

    private function getBill(string $reference_no, $payload = null, bool $strictAmount = false)
    {
        $data = $this->meterService::getBill($reference_no);
    
        if (!$data || !isset($data['current_bill'])) {
            return ['error' => 'Bill not found'];
        }
    
        $total = (float) ($data['current_bill']['amount'] ?? 0);
    
        if($strictAmount) {
            $validator = Validator::make($payload, [
                'payment_amount' => 'required|gte:' . $total
            ], [
                'payment_amount.gte' => 'Cash payment is insufficient'
            ]);
        
            if ($validator->fails()) {
                return ['error' => $validator->errors()->first()];
            }
        }
    
        return ['data' => $data]; 
    }

    public function save(Request $request) {

        $payload = $request->all();

        $reference_no = $payload['reference_no'] ?? null;

        if(!isset($reference_no) || !isset($payload['payment_id'])) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'reference_no and payment_id is required'
                ]);
        }
        
        $records = $this->getBill($reference_no, $payload, false);

        if(empty($records)) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'reference_no ' . $reference_no . ' does not exists'
                ]);
        }

        if (isset($records['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $records['error']
            ]);
        }


        $data = $records['data']; 
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $currentBill = Bill::find($data['current_bill']['id']);

        if ($currentBill) {
            $this->billSettlementService->settlePaidBillChain(
                $currentBill,
                [
                    'amount_paid' => $payload['payment_amount'] ?? null,
                    'payor_name' => $payload['payor'] ?? null,
                    'date_paid' => $now,
                    'payment_method' => 'online',
                ],
                [
                    'payor_name' => $payload['payor'] ?? null,
                    'date_paid' => $now,
                    'payment_method' => 'online',
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bill has been paid'
        ]);

    }

    public function status(string $reference_no) {

        $record = Bill::where('reference_no', $reference_no)
            ->where('isPaid', true)
            ->first();

        if($record) {
            return response()->json([
                'status' => 'paid',
            ]);
        }

        return response()->json([
            'status' => 'unpaid',
        ]); 

    }

}
