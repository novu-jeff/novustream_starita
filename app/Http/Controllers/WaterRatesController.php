<?php

namespace App\Http\Controllers;

use App\Models\WaterRates;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use App\Services\WaterRatesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class WaterRatesController extends Controller
{

    public $waterRatesService;
    public $propertyTypeService;

    public function __construct(WaterRatesService $waterRatesService, PropertyTypesService $propertyTypeService) {
        
        $this->middleware(function ($request, $next) {
    
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });
        
        $this->waterRatesService = $waterRatesService;
        $this->propertyTypeService = $propertyTypeService;
    }

    public function index() {

        $data = $this->waterRatesService::getData();

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('water-rates.index', $data);
    }

    public function create() {

        $property_types = $this->propertyTypeService::getData();

        return view('water-rates.form', compact('property_types'));
    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'property_type' => 'required|exists:property_types,id',
            'cubic_from' => 'required|integer',
            'cubic_to' => 'required|integer|gt:cubic_from',
            'rate' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $existingRange = WaterRates::where('property_types_id', $payload['property_type'])
            ->where(function ($query) use ($payload) {
                $query->whereBetween('cubic_from', [$payload['cubic_from'], $payload['cubic_to']])
                      ->orWhereBetween('cubic_to', [$payload['cubic_from'], $payload['cubic_to']])
                      ->orWhere(function ($q) use ($payload) {
                          $q->where('cubic_from', '<', $payload['cubic_from'])
                            ->where('cubic_to', '>', $payload['cubic_to']);
                      });
            })
            ->exists();
        
        if ($existingRange) {
            $lastRange = WaterRates::where('property_types_id', $payload['property_type'])
                ->orderByDesc('cubic_to')
                ->first();
        
            $suggestedStart = $lastRange ? $lastRange->cubic_to + 1 : 1;
        
            return redirect()->back()
                ->withErrors(['cubic_from' => "The range exists. The new range must start from {$suggestedStart}."])
                ->withInput();
        }

        $response = $this->waterRatesService::create($payload);

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

    public function edit(int $id) {

        $property_types = $this->propertyTypeService::getData();
        $data = $this->waterRatesService::getData($id);
        
        return view('water-rates.form', compact('data', 'property_types'));
    }

    public function update(int $id, Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'property_type' => 'required|exists:property_types,id',
            'cubic_from' => 'required|integer',
            'cubic_to' => 'required|integer',
            'rate' => 'required|numeric'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->waterRatesService::update($id, $payload);

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

        $response = $this->waterRatesService::delete($id);

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

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                return '
                <div class="d-flex align-items-center gap-2">
                    <a href="' . route('water-rates.edit', $row->id) . '" 
                        class="btn btn-secondary text-white text-uppercase fw-bold" 
                        id="update-btn" data-id="' . e($row->id) . '">
                        <i class="bx bx-edit-alt"></i>
                    </a>
                    <button class="btn btn-danger text-white text-uppercase fw-bold btn-delete" id="delete-btn" data-id="' . e($row->id) . '">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
    
}
