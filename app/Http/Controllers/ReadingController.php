<?php

namespace App\Http\Controllers;

use App\Models\PaymentBreakdown;
use App\Models\PaymentServiceFee;
use App\Models\User;
use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Models\Reading;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class ReadingController extends Controller
{
    
    public $meterService;
    public $paymentBreakdownService;
    public $paymentServiceFee;
    public $generateService;

    public function __construct(MeterService $meterService, 
        PaymentBreakdown $paymentBreakdownService, 
        PaymentServiceFee $paymentServiceFee,
        GenerateService $generateService)
    {

        $this->middleware(function ($request, $next) {
            $method = $request->route()->getActionMethod(); 
    
            if (!in_array($method, ['show'])) {
                if (!Gate::any(['admin', 'technician'])) {
                    abort(403, 'Unauthorized');
                }
            }
    
            return $next($request);
        });

        $this->meterService = $meterService;
        $this->paymentBreakdownService = $paymentBreakdownService;
        $this->paymentServiceFee = $paymentServiceFee;
        $this->generateService = $generateService;
    }

    public function index(Request $request) {

        if($request->ajax()) {
            $response = $this->meterService->locate($request->all());
            return response()->json($response);
        }

        return view('reading.index');
    }

    public function show(string $reference_no) {

        $data = $this->meterService::getBill($reference_no);

        if(is_null($data)) {
            return redirect()->route('reading.index')->with('alert', [
                'status' => 'error',
                'message' => 'Bill Not Found'
            ]);
        }

        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        $qr_code = $this->generateService::qr_code($url, 80);

        return view('reading.show', compact('data', 'reference_no', 'qr_code'));
    }

    public function report(?string $date = null) {

        $data = $this->meterService::getReport($date);

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('reading.report', compact('data'));

    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'meter_no' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!DB::table('concessioner_accounts')
                        ->where('meter_serial_no', $value)
                        ->orWhere('account_no', $value)
                        ->exists()) {
                        $fail('The meter no. or account no. does not exist.');
                    }
                },
            ],
            'previous_reading' => 'required|integer',
            'present_reading' => 'required|integer|gt:previous_reading',
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            
            $account = $this->meterService->getAccount($payload['meter_no']);

            $meter_no = $account->meter_serial_no;
            $property_type_id = $account->property_type;

            $present_reading = $payload['present_reading'];

            $computed = $this->meterService->create_breakdown([
                'meter_no' => $meter_no,
                'property_type_id' => $property_type_id,
                'present_reading' => $present_reading
            ]);

            if($computed['status'] == 'error') {
                return redirect()->back()->withInput($payload)->with('alert', [
                    'status' => 'error',
                    'message' => $computed['message']
                ]);
            }

            $reading = Reading::create($computed['reading']);

            $computed['bill']['reading_id'] = $reading->id;

            $bill = Bill::create($computed['bill']);

            foreach($computed['deductions'] as $deductions) {
                BillBreakdown::create([
                    'bill_id' => $bill->id,
                    'name' => $deductions['name'],
                    'description' => $deductions['description'],
                    'amount' => $deductions['amount']
                ]);
            }

            $payload = [
                'amount' => (float) $computed['bill']['amount'],
                'reference_no' => $computed['bill']['reference_no'],
                'callback' => route('transaction.callback'),
            ];

            $this->generatePaymentQR($bill['reference_no'], $payload);

            DB::commit();

            return redirect()->route('reading.show', ['reference_no' => $bill->reference_no])->with('alert', [
                'status' => 'success',
                'message' => 'Bill has been created'
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ]);
        }
        

    }

    private function generatePaymentQR(string $reference_no, array $payload) {
        
        // $api = env('NOVUPAY_URL') . '/api/v1/save/transaction';
        $api = 'http://localhost/api/v1/save/transaction';

        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); 
        
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

        if ($httpCode == 200) {
           
            if ($decodedResponse['status'] == 'success' && isset($decodedResponse['reference_no'])) {

                return true;

            } else {
                return [
                    'status' => 'error',
                    'payment_request' => false,
                    'message' => 'Invalid response from the payment gateway.'
                ];
            }

        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to process online payment. Please try again.',
                'details' => $decodedResponse
            ];
        }

    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('created_at', function ($row) {
                return Carbon::parse($row->created_at)->format('F d, Y');
            })
            ->addColumn('actions', function ($row) {
                return 
                    '<div class="d-flex align-items-center gap-2">
                        <a target="_blank" href="' . route('reading.show', $row->bill->reference_no) . '" 
                            class="btn btn-primary text-white text-uppercase fw-bold" 
                            id="show-btn" data-id="' . e($row->id) . '">
                            <i class="bx bx-receipt"></i>
                        </a>
                    </div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

}
