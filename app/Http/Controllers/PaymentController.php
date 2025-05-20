<?php

namespace App\Http\Controllers;

use App\Imports\PreviousBillingImport;
use App\Models\Bill;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{

    public $meterService;
    public $generateService;

    public function __construct(MeterService $meterService, 
        GenerateService $generateService) {
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

    public function index() {
        return redirect()->route('payments.show', ['payment' => 'unpaid']);
    }

    public function upload(Request $request) {
        
        if($request->getMethod() == 'POST') {

            $request->validate([
                'file' => 'required|mimes:xlsx,csv|max:5120', 
            ]);
        
            try {
    
                if (!$request->hasFile('file')) {
                    return redirect()->back()->with('alert', [
                        'status' => 'error',
                        'message' => 'No file was uploaded.',
                    ]);
                }
        
                Excel::import(new PreviousBillingImport, $request->file('file'));
    
    
                return response(['status' => 'success', 'message' => 'Clients imported successfully']);
        
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $failures = $e->failures();
                $errors = [];
        
                foreach ($failures as $failure) {
                    $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
                }
        
                return response(['status' => 'error', 'message' => implode('<br>', $errors)]);
    
        
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return response(['status' => 'error', 'message' => $e->getMessage()]);
            }

        } else {
            return view('payments.upload');
        }

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
                    return $this->processCashPayment($reference_no, $payload);    
                case 'online':
                    return $this->processOnlinePayment($reference_no, $payload);
            }

        }

        $data = $this->meterService::getBill($reference_no);


        if(isset($data['status']) && $data['status'] == 'error') {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $data['message']
            ]);
        }

        if(!is_null($data['active_payment'])) {
            return redirect()->route('payments.pay', ['reference_no' => $data['active_payment']->reference_no]);
        }

        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        $qr_code = $this->generateService::qr_code($url, 80);

        return view('payments.pay', compact('data', 'reference_no', 'qr_code'));

    }

    private function getBill(string $reference_no, $payload = null, bool $strictAmount = false)
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
        
        $amount = $data['current_bill']->amount;
        $change = $payload['payment_amount'] - $amount;
    
        $data['current_bill']->update([
            'isPaid' => true,
            'amount_paid' => $payload['payment_amount'],
            'change' => $change,
            'payor_name' => $payload['payor'],
            'date_paid' => $now,
        ]);
    
        if (!empty($data['unpaid_bills'])) {
            foreach ($data['unpaid_bills'] as $unpaid_bill) {
                $unpaid_bill->update([
                    'payor_name' => $payload['payor'],
                    'date_paid' => $now,
                    'isPaid' => true,
                    'amount_paid' => $payload['payment_amount'],
                    'change' => $change,
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
        
        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        return redirect()->route('payments.pay', ['reference_no' => $reference_no])->with('alert', [
            'status' => 'success',
            'payment_request' => true,
            'redirect' => $url,
        ]);

    }

    public function callback(Request $request, string $reference_no) {

        $payload = $request->all();

        $bill = Bill::where('reference_no', $reference_no)
            ->where('isPaid', false)
            ->first();

        if($bill) {

            $bill->update([
                'isPaid' => true,
                'amount_paid' => $payload['amount'],
                'date_paid' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment successful'
            ]);
        } 

        return response()->json([
            'status' => 'error',
            'message' => 'Payment not found'
        ], 404);

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
