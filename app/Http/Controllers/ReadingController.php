<?php

namespace App\Http\Controllers;

use App\Models\PaymentBreakdown;
use App\Models\PaymentServiceFee;
use App\Models\User;
use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Models\Rates;
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
            $response = $this->meterService::locate($request->all());
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

        $url = route('reading.show', ['reference_no' => $reference_no]);

        $qr_code = $this->generateService::qr_code($url, 60);

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
                    if (!DB::table('users')
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
            
            $user = User::where('meter_serial_no', $payload['meter_no'])
                ->orWhere('account_no', $payload['meter_no'])
                ->first();

            $meter_no = $user->meter_serial_no;
            $property_type_id = $user->property_type;

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

            // READING
            $reading = Reading::create($computed['reading']);

            $computed['bill']['reading_id'] = $reading->id;

            // BILL
            $bill = Bill::create($computed['bill']);

            // BILL BREAKDOWN
            foreach($computed['deductions'] as $deductions) {
                BillBreakdown::create([
                    'bill_id' => $bill->id,
                    'name' => $deductions['name'],
                    'description' => $deductions['description'],
                    'amount' => $deductions['amount']
                ]);
            }

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
