<?php

namespace App\Http\Controllers;

use App\Services\PaymentBreakdownService;
use App\Services\PropertyTypesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class PaymentBreakdownController extends Controller
{

    public $paymentBreakdownService;
    public $propertyService;

    public function __construct(PaymentBreakdownService $paymentBreakdownService, PropertyTypesService $propertyService) {

        $this->middleware(function ($request, $next) {
    
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });

        
        $this->paymentBreakdownService = $paymentBreakdownService;
        $this->propertyService = $propertyService;
    }

    public function index(Request $request) {

        $regular = $this->paymentBreakdownService::getData();
        $penalty = $this->paymentBreakdownService->getPenalty() ?? [];
        $service_fee = $this->paymentBreakdownService->getServiceFee() ?? [];

        if(request()->ajax()) {
            if($request->action == 'regular') {
                return $this->datatable('regular', $regular);
            }

            if($request->action == 'penalty') {
                return $this->datatable('penalty', $penalty);
            }
            if($request->action == 'service-fee') {
                return $this->datatable('service_fee', $service_fee);
            }
        }

        return view('payment-breakdown.index');
    }

    public function create(Request $request) {

        $action = $request->action;

        if(!in_array($action, ['regular', 'penalty', 'service-fee'])) {
            return redirect()->route('payment-breakdown.index');
        }

        $penalty = $this->paymentBreakdownService->getPenalty() ?? [];
        $service_fee = $this->paymentBreakdownService->getServiceFee() ?? [];
        $property_types = $this->propertyService->getData() ?? [];

        return view('payment-breakdown.form', compact('action', 'penalty', 'service_fee', 'property_types'));
    }

    public function store(Request $request) {

        $payload = $request->all();

        $action = $payload['action'];

        if(!in_array($action, ['regular', 'penalty', 'service-fee'])) {
            return;
        }

        if($action == 'regular') {

            $validator = Validator::make($payload, [
                'name' => 'required|unique:payment_breakdowns',
                'type' => 'required|in:percentage,fixed',
                'percentage_of' => 'nullable|required_if:type,percentage|in:basic_charge,total_amount',
                'amount' => 'required|numeric'
            ]);
            
            if($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }
    
            $response = $this->paymentBreakdownService::create($payload);
        } 

        if($action == 'penalty') {

            $validator = Validator::make($payload, [
                'penalty.from.*' => 'required|integer|min:1',
                'penalty.to.*' => 'required|integer',
                'penalty.amount.*' => 'required|numeric|min:1',
            ], [
                'penalty.required' => '*at least one penalty entry is required',
                
                'penalty.from.*.required' => '*required',
                'penalty.from.*.integer' => '*must be number',
                'penalty.from.*.min' => '*must be at least 1',
            
                'penalty.to.*.required' => '*required',
                'penalty.to.*.integer' => '*must be number',
            
                'penalty.amount.*.required' => '*required',
                'penalty.amount.*.numeric' => '*must be a valid amount',
                'penalty.amount.*.min' => '*must be at least 1',
            ]);
            
            if($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $response = $this->paymentBreakdownService::create($payload);

        }

        if($action == 'service-fee') {

            $validator = Validator::make($payload, [
                'service_fee.property_type.*' => [
                    'required',
                    'exists:property_types,id',
                    function ($attribute, $value, $fail) use ($payload) {
                        // Extract the index from the attribute (e.g., service_fee.property_type.1)
                        preg_match('/\d+/', $attribute, $matches);
                        $index = isset($matches[0]) ? (int) $matches[0] : null;
            
                        // Apply unique validation only to the second item (index 1)
                        if ($index === 1) {
                            $exists = DB::table('payment_service_fees')
                                ->where('property_id', $value)
                                ->exists();
            
                            if ($exists) {
                                $fail('* already exists');
                            }
                        }
                    },
                ],
                'service_fee.amount.*' => 'required|numeric',
            ], [
                'service_fee.property_type.*.required' => '* required',
                'service_fee.property_type.*.exists' => '* invalid property types',
                'service_fee.amount.*.required' => '* required',
                'service_fee.amount.*.numeric' => '* must be a number',
            ]);
            
            if($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $response = $this->paymentBreakdownService::create($payload);

        }

        if ($response['status'] === 'success') {
            return redirect()->back()->with('alert', [
                'status' => 'success',
                'message' => $response['message']
            ]);
        } else {
            return redirect()->back()->withInput()->with('alert', [
                'status' => 'error',
                'message' => $response['message']
            ]);
        }
    }

    public function edit(int $id, Request $request) {

        $action = $request->action;

        if(!in_array($action, ['regular', 'penalty', 'service-fee'])) {
            return redirect()->route('payment-breakdown.index');
        }

        $data = $this->paymentBreakdownService::getData($id);
        $property_types = $this->propertyService->getData() ?? [];

        return view('payment-breakdown.form', compact('action', 'property_types', 'data'));
    }

    public function update(int $id, Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'name' => [
                'required',
                Rule::unique('payment_breakdowns')->ignore($id)
            ],
            'type' => 'required|in:percentage,fixed',
            'percentage_of' => 'nullable|required_if:type,percentage|in:basic_charge,total_amount',
            'amount' => 'required|numeric'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->paymentBreakdownService::update($id, $payload);

        if ($response['status'] === 'success') {
            return redirect()->back()->with('alert', [
                'status' => 'success',
                'message' => $response['message']
            ]);
        } else {
            return redirect()->back()->withInput()->with('alert', [
                'status' => 'error',
                'message' => $response['message']
            ]);
        }
        
    }


    public function destroy(int $id) {

        $response = $this->paymentBreakdownService::delete($id);

        if ($response['status'] === 'success') {
            
            return response()->json([
                'status' => 'success',
                'message' => $response['message']
            ]);
            
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $response['message']
            ]);
        }

    }

    public function datatable($action, $query) {
        if($action == 'regular') {
            return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('amount', function($row) {
                if($row->type == 'percentage') {
                    return $row->amount . '%';
                } else {
                    return '₱' .  number_format($row->amount ?? 0, 2);
                }
            })
            ->addColumn('actions', function ($row) use ($action) {
                return '
                <div class="d-flex align-items-center gap-2">
                    <a href="' . route('payment-breakdown.edit', ['payment_breakdown' => $row->id, 'action' => $action]) . '"
                        class="btn btn-secondary text-white text-uppercase fw-bold" 
                        id="update-btn" data-id="' . e($row->id) . '">
                        <i class="bx bx-edit-alt"></i>
                    </a>
                    <button class="btn btn-danger text-white text-uppercase fw-bold btn-delete" id="btn-delete" data-id="' . e($row->id) . '">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
        }

        if($action == 'penalty') {
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('amount', function($row) {
                    return '₱' .  number_format($row->amount ?? 0, 2);
                })
                ->make(true);
        }

        if($action == 'service_fee') {
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('property', function($row) {
                    return $row->property->name;
                })
                ->editColumn('amount', function($row) {
                    return '₱' .  number_format($row->amount ?? 0, 2);
                })
                ->make(true);
        }
    }

}
