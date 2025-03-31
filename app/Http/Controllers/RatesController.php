<?php

namespace App\Http\Controllers;

use App\Services\PropertyTypesService;
use App\Services\RatesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class RatesController extends Controller
{

    public $RatesService;
    public $propertyTypeService;

    public function __construct(RatesService $RatesService, PropertyTypesService $propertyTypeService) {
        
        $this->middleware(function ($request, $next) {
    
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });
        
        $this->RatesService = $RatesService;
        $this->propertyTypeService = $propertyTypeService;
    }

    public function index(Request $request) {
        $propertyTypeId = $request->get('property_type') ?? 1;
        $data = $this->RatesService->getData($propertyTypeId);

        $property_types = $this->propertyTypeService::getData();
        $base_rate = $this->RatesService->getActiveBaseRate($propertyTypeId);

        $app_type = config('app.product') === 'novustream' ? 'Water' : 'Electricity';
        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('rates.index', compact('property_types', 'base_rate', 'propertyTypeId', 'app_type'));
    }

    public function store(Request $request) {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'property_type' => 'required|exists:property_types,id',
            'cubic_from' => 'required|integer',
            'cubic_to' => 'required|integer|gt:cubic_from',
            'charge' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {

            $updatecharge = $this->RatesService->updateCharge($payload);
            $this->RatesService->recomputeRates($payload['property_type']);

            DB::commit();

            return redirect()->back()->with('alert', [
                'update_charge' => $updatecharge,
                'status' => 'success',
                'message' => 'Water rate updated.'
            ]);

        } catch (\Exception $e) {
            
            DB::rollBack();

            return redirect()->back()->withInput()->with('alert', [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ]);
        }
        
    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->rawColumns(['actions'])
            ->make(true);
    }
    
}
