<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{

    public $meterService;
    public $generateService;

    public function __construct(MeterService $meterService, GenerateService $generateService) {
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

    public function index() {
        return redirect()->route('payments.show', ['payment' => 'unpaid']);
    }

    public function show(string $filter) {
        if (!in_array($filter, ['unpaid', 'paid'], true)) {
            return redirect()->route('payments.index');
        }
    
        $fil = $filter === 'paid';
    
        $data = $this->meterService::getPayments(null, $fil);
    
        if (request()->ajax()) {
            return $this->datatable($data);
        }
    
        return view('payments.index', compact('data', 'filter'));
    }

    public function pay(Request $request, string $reference_no) {


        if($request->getMethod() == 'POST') {
            $payload = $request->all();
            
            switch($payload['payment_type']) {
                case 'cash':
                    $this->processCashPayment($reference_no, $payload);    
                    break;
                case 'online':
                    $this->processOnlinePayment($reference_no, $payload);
            }

        }

        $data = $this->meterService::getBill($reference_no);

        if(!$data) {
            return redirect()->route('payments.index');
        }

        if(!is_null($data['active_payment'])) {
            return redirect()->route('payments.pay', ['reference_no' => $data['active_payment']->reference_no]);
        }

        $url = route('reading.show', ['reference_no' => $reference_no]);

        $qr_code = $this->generateService::qr_code($url, 60);

        return view('payments.pay', compact('data', 'reference_no', 'qr_code'));

    }

    private function getBill(string $reference_no, $payload, bool $strictAmount = false)
    {
        $data = $this->meterService::getBill($reference_no);
    
        if (!$data || !isset($data['current_bill'])) {
            return ['error' => 'Bill not found'];
        }
    
        $total = $data['current_bill']->amount;
    
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
    
    public function processCashPayment(string $reference_no, array $payload) {
        
        $result = $this->getBill($reference_no, $payload, true);
    
        if (isset($result['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }
    
        $data = $result['data']; 
    
        $now = Carbon::now()->format('Y-m-d H:i:s');
    
        $data['current_bill']->update([
            'isPaid' => true,
            'payor_name' => $payload['payor'],
            'date_paid' => $now,
        ]);
    
        if (!empty($data['unpaid_bills'])) {
            foreach ($data['unpaid_bills'] as $unpaid_bill) {
                $unpaid_bill->update([
                    'payor_name' => $payload['payor'],
                    'date_paid' => $now,
                    'isPaid' => true,
                    'paid_by_reference_no' => $reference_no
                ]);
            }
        }
    
        return redirect()->back()->with('alert', [
            'status' => 'success',
            'message' => 'Bill has been paid'
        ]);
    }
    
    public function processOnlinePayment(string $reference_no, array $payload) {

        $result = $this->getBill($reference_no, $payload, false);
    
        if (isset($result['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }
    
        $data = $result['data']; 

        $toSaveSession = [
            'amount' => (float) $data['current_bill']->amount,
            'reference_no' => $reference_no,
            'customer' => [
                'account_number' => '',
                'name' => $data['client']->name,
                'email' => $data['client']->email,
                'phone_number' => $data['client']->contact_no,
                'address' => $data['client']->address
            ],
            'callback' => route('transaction.callback')
        ];

        $api = env('NOVUPAY_URL') . '/api/v1/save/transaction';

        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($toSaveSession)); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if ($curlError) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Curl Error: ' . $curlError
            ]);
        }
        
        if (!is_array($decodedResponse)) {
            $decodedResponse = ['error' => 'Invalid API response', 'raw' => $response];
        }

        if ($httpCode === 200) {
           
            if ($decodedResponse['status'] == 'success' && isset($decodedResponse['reference_no'])) {

                $reference_no = $decodedResponse['reference_no'];
                $novupayUrl = env('NOVUPAY_URL');
                
                if (!$novupayUrl) {
                    return redirect()->back()->with('alert', [
                        'status' => 'error',
                        'message' => 'NOVUPAY_URL is not configured in the environment file.'
                    ]);
                }

                $novupay = $novupayUrl . '/payment/merchants/' . $reference_no;

                return redirect()->route('payments.pay', ['reference_no', $reference_no])->with('alert', [
                    'status' => 'success',
                    'payment_request' => true,
                    'redirect' => $novupay,
                ]);

            } else {
                return redirect()->back()->with('alert', [
                    'status' => 'error',
                    'payment_request' => false,
                    'message' => 'Invalid response from the payment gateway.'
                ]);
            }

        } else {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Failed to process online payment. Please try again.',
                'details' => $decodedResponse
            ]);
        }
    }

    public function datatable($query) {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('billing_period', function ($row) {
                return ($row->bill_period_from && $row->bill_period_to) 
                    ? Carbon::parse($row->bill_period_from)->format('M d, Y') . ' TO ' . Carbon::parse($row->bill_period_to)->format('M d, Y')
                    : 'N/A';
            })
            ->editColumn('bill_date', function ($row) {
                return !empty($row->bill_period_to) 
                    ? Carbon::parse($row->bill_period_to)->format('M d, Y') 
                    : 'N/A';
            })
            ->editColumn('amount', function ($row) {
                return '₱' . number_format((float)($row->amount ?? 0), 2);
            })
            ->editColumn('due_date', function ($row) {
                return !empty($row->due_date) 
                    ? Carbon::parse($row->due_date)->format('M d, Y') 
                    : 'N/A';
            })
            ->editColumn('status', function ($row) {
                return $row->isPaid
                    ? '<div class="alert alert-primary mb-0 py-1 px-2 text-center">Paid</div>'
                    : '<div class="alert alert-danger mb-0 py-1 px-2 text-center">Unpaid</div>';
            })
            ->addColumn('actions', function ($row) {
                if(!$row->isPaid) {
                    return '
                    <div class="d-flex align-items-center gap-2">
                        <a href="' . route('payments.pay', ['reference_no' => $row->reference_no]) . '" 
                            class="btn btn-primary text-white text-uppercase fw-bold">
                            <i class="bx bx-credit-card-alt" ></i>
                        </a>
                    </div>';
                } else {
                    return 
                    '<div class="d-flex align-items-center gap-2">
                        <a target="_blank" href="' . route('reading.show', $row->reference_no) . '" 
                            class="btn btn-primary text-white text-uppercase fw-bold" 
                            id="show-btn" data-id="' . e($row->id) . '">
                            <i class="bx bx-receipt"></i>
                        </a>
                    </div>';
                }
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

}
