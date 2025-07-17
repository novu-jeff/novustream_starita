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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;

class ReadingController extends Controller
{
    
    public $meterService;
    public $paymentBreakdownService;
    public $paymentServiceFee;
    public $generateService;
    public $isTesting = false;

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

        $this->isTesting = env('IS_TEST_READING');
    }

    public function index(Request $request) {

        if($request->ajax()) {
            
            $payload = $request->all();

            if(isset($payload['isGetPrevious']) && $payload['isGetPrevious'] == true) {
                $response = $this->meterService->getPreviousReading($payload['account_no']);
                return response()->json($response);
            }

            if(isset($payload['isReRead']) && $payload['isReRead'] == 'true') {
                $response = $this->meterService->getReRead($payload['reference_no']);
                return response()->json($response);
            }

            if(isset($payload['isGetRecentReading']) && $payload['isGetRecentReading'] == true) {
                $response = session('recent_reading') ?? null;
                return response()->json($response);
            }

            if(isset($payload['isGetReadUnread']) && $payload['isGetReadUnread'] == true) {
                $response = $this->meterService->getReadUnread($payload['targetDate']);
                return response()->json($response);
            }

            $response = $this->meterService->filterAccount($request->all());
            return response()->json($response);
        }

        $isReRead = !empty($request->input('re-read')) && !empty($request->input('reference_no')) ? true : false;
        $reference_no = $request->input('reference_no') ?? null;

        if($isReRead) {
            $bill = $this->meterService->getBill($reference_no);
            if(isset($bill['status']) && $bill['status'] == 'error') {
                return redirect()->route('reading.index');
            }
        }

        $zones = $this->meterService->getZones();

        return view('reading.index', compact('isReRead', 'reference_no', 'zones'));
    }

    public function show(string $reference_no) {

        $data = $this->meterService::getBill($reference_no);

        if(isset($data['status']) && $data['status'] == 'error') {

            if(empty($data['client']['account_no'])) {
                return redirect()->back()->with('alert', [
                    'status' => 'error',
                    'message' => 'No concessionaire found'
                ]);
            }

            return redirect()->route('reading.index')->with('alert', [
                'status' => 'error',
                'message' => 'Bill Not Found'
            ]);
        }

        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        $qr_code = $this->generateService::qr_code($url, 80);

        $amount = $data['current_bill']['amount' ?? 0];
        $assumed_penalty = $amount * 0.15;
        $assumed_amount_after_due = $amount + $assumed_penalty;

        $data['current_bill']['assumed_penalty'] = $assumed_penalty;
        $data['current_bill']['assumed_amount_after_due'] = $assumed_amount_after_due;
        
        $isReRead = [
            'status' => $data['current_bill']['reading']['isReRead'] ?? false,
            'reference_no' => $data['current_bill']['reading']['reread_reference_no']
        ];

        return view('reading.show', compact('data', 'isReRead', 'reference_no', 'qr_code'));
    }

    public function report(Request $request) {

        $zones = $this->meterService->getZones();
        $zone = $request->zone ?? 'all';

        $entries = $request->entries ?? 10;
        $toSearch = $request->search ?? '';
        $date = $request->date ?? $this->meterService->getLatestReadingMonth();


        $collection = collect($this->meterService::getReport($zone, $date, $toSearch))
            ->flatten(2); 

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $collection->slice(($currentPage - 1) * $entries, $entries)->values();

        $data = new LengthAwarePaginator(
            $currentItems,
            $collection->count(),
            $entries,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reading.report', compact('data', 'entries', 'zones', 'zone', 'date', 'toSearch'));

    }

    public function store(Request $request)
    {
        $payload = $request->all();

        if(isset($payload['isClearRecent']) && $payload['isClearRecent'] == true) {
            session()->forget('recent_reading');
            return response()->json([
                'status' => 'success',
                'message' => 'recent reading cleared'
            ]);
        }

        $validator = Validator::make($payload, [
            'reading_month' => [
                function ($attribute, $value, $fail) {
                    if ($this->isTesting && empty($value)) {
                        return $fail('Reading month is required.');
                    }
                }
            ],
            'account_no' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!DB::table('concessioner_accounts')
                        ->where('account_no', $value)
                        ->exists()) {
                        $fail('The meter no. or account no. does not exist.');
                    }
                },
            ],
            'previous_reading' => 'required|integer',
            'present_reading' => 'required|integer|gt:previous_reading',
            'is_high_consumption' => 'required|in:yes,no',
            'isReRead' => 'required|in:true,false',
            'reference_no' => 'nullable|exists:bill,reference_no'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($this->isTesting) {
                $date = Carbon::createFromFormat('Y-m-d', $payload['reading_month']);
            } else {
                $date = Carbon::now();
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid reading month format. Use "Month YYYY" (e.g., January 2025).'
            ], 400);
        }

        $month = $date->month;
        $year = $date->year;
        $account_no = $payload['account_no'];

        $isReRead = $payload['isReRead'];

        if(!$isReRead) {
            $exists = Reading::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('account_no', $account_no)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Reading already exists for {$date->format('F Y')}."
                ], 409);
            }
        }

        DB::beginTransaction();

        try {

            $account = $this->meterService->getAccount($payload['account_no']);

            $account_no = $account->account_no;
            $property_type_id = $account->property_type;

            $present_reading = $payload['present_reading'];
            $is_high_consumption = $payload['is_high_consumption'];
            $reference_no = $payload['reference_no'] ?? null;

            $computed = $this->meterService->create_breakdown([
                'account_no' => $account_no,
                'property_type_id' => $property_type_id,
                'present_reading' => $present_reading,
                'date' => $date,
                'is_high_consumption' => $is_high_consumption,
                'isReRead' => $isReRead,
                'reference_no' => $reference_no
            ]);

            if ($computed['status'] == 'error') {
                return response()->json([
                    'status' => 'error',
                    'message' => $computed['message']
                ], 400);
            }

            $amount = (float) $computed['bill']['amount'] + (float) $computed['bill']['penalty'];

            $payload = [
                'amount' => round($this->convertAmount((float) $amount), 2),
                'reference_no' => $computed['bill']['reference_no'],
                'customer' => [
                    'account_number' => $account->account_no ?? '',
                    'address' => $account->address ?? '',
                    'email' => $account->user->email ?? '',
                    'name' => $account->user->name ?? '',
                    'phone_number' => $account->user->contact_no ?? '',
                    'remark' => ''
                ],
            ];

            $reference_no = $computed['bill']['reference_no'];

            $this->generatePaymentQR($reference_no, $payload);

            session(['recent_reading' => [
                'name' => $account->user->name ?? '',
                'address' => $account->address ?? '',
                'account_no' => $account->account_no ?? '',
                'timestamp' => Carbon::now()
            ]]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Bill has been created, redirecting...',
                'redirect_url' => route('reading.show', ['reference_no' => $reference_no]),
                'data' => [
                    'reference_no' => $reference_no,
                    'amount' => $payload['amount'],
                    'customer' => $payload['customer']
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reading Store Error:', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    private function convertAmount(float $amount): string
    {
        $amountFloat = floatval(str_replace(',', '', $amount));

        if (fmod($amountFloat, 1) === 0.0) {
            return number_format($amountFloat, 2, '', ''); 
        } else {
        
            $amountNoDot = str_replace('.', '', number_format($amountFloat, 2, '.', ''));
            return $amountNoDot;
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
        
        if(is_null($decodedResponse)) {
            Log::error('error: ' . $decodedResponse);
            throw new \Exception('Failed to process online payment. Unable to connect to novupay.');
        }

        if ($httpCode == 200) {
           
            if ($decodedResponse['status'] == 'success' && isset($decodedResponse['reference_no'])) {
                return true;
            } else {
                Log::error('error: ' . $decodedResponse);
                throw new \Exception('Failed to process online payment. Unable to connect to novupay.');
            }

        } else {
            Log::error('error: ' . $decodedResponse);
            throw new \Exception('Failed to process online payment. Unable to connect to novupay.');
        }

    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('account_no', function ($row) {
                return $row->account_no;
            })
            ->editColumn('created_at', function ($row) {
                return Carbon::parse($row->created_at)->format('F d, Y');
            })
            ->addColumn('actions', function ($row) {
                return 
                    '<div class="d-flex align-items-center gap-2">
                        <a href="' . route('reading.show', $row->bill->reference_no) . '" 
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
