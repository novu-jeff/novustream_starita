<?php

namespace App\Http\Controllers;

use App\Models\BaseRate;
use App\Services\PropertyTypesService;
use App\Services\RatesService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;


class BaseRateController extends Controller
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

    public function index(Request $request)
    {
        $propertyTypeId = $request->get('property_type') ?? 1;
        $data = $this->RatesService->getBaseRates($propertyTypeId);

        $property_types = $this->propertyTypeService::getData();

        $app_type = env('APP_PRODUCT') === 'novustream' ? 'Water' : 'Electricity';

        $base_rate = $this->RatesService->getActiveBaseRate($propertyTypeId);

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('base-rate.index', compact('property_types', 'base_rate', 'propertyTypeId', 'app_type'));
    }

    public function store(Request $request)
{
    $payload = $request->all();

    $validator = Validator::make($payload, [
        'property_type' => 'required|exists:property_types,id',
        'rate' => 'required|numeric|min:1|max:999999.99'
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    $activeRate = $this->RatesService->getActiveBaseRate($payload['property_type']);

    if ($activeRate && (float)$activeRate === (float)$payload['rate']) {
        return redirect()->back()
            ->withInput()
            ->withErrors(['rate' => 'Base rate already active. Enter a different value.']);
    }

    DB::beginTransaction();
    try {
        $createBaseRate = $this->RatesService->createBaseRate($payload);
        $this->RatesService->recomputeRates($payload['property_type']);

        DB::commit();

        return redirect()->route('base-rate.index', [
            'property_type' => $payload['property_type']
        ])->with('alert', [
            'data' => $createBaseRate,
            'status' => 'success',
            'message' => 'Water rate updated.'
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return redirect()->back()->withInput()->with('alert', [
            'status' => 'error',
            'message' => 'Error occurred: ' . $e->getMessage()
        ]);
    }
}


    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {

                $latestBaseRate = BaseRate::where('property_type_id', $row->property_type_id)->latest()->first();

                if ($latestBaseRate->id === $row->id) {
                    return '<span class="badge bg-success">Active</span>';
                } else {
                    return '<span class="badge bg-secondary opacity-75">Inactive</span>';
                }
            })
            ->addColumn('month_day', function ($row) {
                return $row->created_at ? $row->created_at->format('F j') : 'N/A';
            })
            ->addColumn('year', function ($row) {
                return $row->created_at ? $row->created_at->format('Y') : 'N/A';
            })
            ->rawColumns(['status', 'month', 'year'])
            ->make(true);
    }
}
