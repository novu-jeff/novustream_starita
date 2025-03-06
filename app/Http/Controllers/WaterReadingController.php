<?php

namespace App\Http\Controllers;

use App\Models\PaymentBreakdown;
use App\Models\PaymentServiceFee;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterBillBreakdown;
use App\Models\WaterRates;
use App\Models\WaterReading;
use App\Services\GenerateService;
use App\Services\WaterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class WaterReadingController extends Controller
{
    
    public $waterService;
    public $paymentBreakdownService;
    public $paymentServiceFee;
    public $generateService;

    public function __construct(WaterService $waterService, 
        PaymentBreakdown $paymentBreakdownService, 
        PaymentServiceFee $paymentServiceFee,
        GenerateService $generateService)
    {

        $this->middleware(function ($request, $next) {
            $method = $request->route()->getActionMethod(); // Use request object
    
            if (!in_array($method, ['show'])) {
                if (!Gate::any(['admin', 'technician'])) {
                    abort(403, 'Unauthorized');
                }
            }
    
            return $next($request);
        });

        $this->waterService = $waterService;
        $this->paymentBreakdownService = $paymentBreakdownService;
        $this->paymentServiceFee = $paymentServiceFee;
        $this->generateService = $generateService;
    }

    public function index(Request $request) {

        if($request->ajax()) {
            $response = $this->waterService::locate($request->all());
            return response()->json($response);
        }

        return view('water-reading.index');
    }

    public function show(string $reference_no) {

        $data = $this->waterService::getBill($reference_no);

        if(is_null($data)) {
            return redirect()->route('water-reading.index')->with('alert', [
                'status' => 'error',
                'message' => 'Bill Not Found'
            ]);
        }

        $url = route('water-reading.show', ['reference_no' => $reference_no]);

        $qr_code = $this->generateService::qr_code($url, 100);

        return view('water-reading.show', compact('data', 'reference_no', 'qr_code'));
    }

    public function report(string $date = null) {

        $data = $this->waterService::getReport($date);

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('water-reading.report', compact('data'));

    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'meter_no' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!DB::table('users')
                        ->where('meter_no', $value)
                        ->orWhere('contract_no', $value)
                        ->exists()) {
                        $fail('The water meter no. or water contract no. does not exist.');
                    }
                },
            ],
            'present_reading' => 'required|integer|gt:previous_reading',
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            
            $user = User::where('meter_no', $payload['meter_no'])
                ->orWhere('contract_no', $payload['meter_no'])
                ->first();

            $meter_no = $user->meter_no;
            $property_type_id = $user->property_type;
            $present_reading = $payload['present_reading'];

            $computed = $this->waterService->create_breakdown([
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

            // WATER READING
            $water = WaterReading::create($computed['reading']);

            $computed['bill']['water_reading_id'] = $water->id;

            // WATER BILL
            $bill = WaterBill::create($computed['bill']);

            // BILL BREAKDOWN
            foreach($computed['deductions'] as $deductions) {
                WaterBillBreakdown::create([
                    'water_bill_id' => $bill->id,
                    'name' => $deductions['name'],
                    'description' => $deductions['description'],
                    'amount' => $deductions['amount']
                ]);
            }

            DB::commit();

            return redirect()->route('water-reading.show', ['reference_no' => $bill->reference_no])->with('alert', [
                'status' => 'success',
                'message' => 'Water Bill Created'
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
                        <a target="_blank" href="' . route('water-reading.show', $row->bill->reference_no) . '" 
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
