<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\PropertyTypesService;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class PropertyTypesController extends Controller
{

    public $propertyTypeService;

    public function __construct(PropertyTypesService $propertyTypeService) {

        $this->middleware(function ($request, $next) {

            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });

        $this->propertyTypeService = $propertyTypeService;
    }

    public function index() {

        $data = $this->propertyTypeService::getData();

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('property-type.index', $data);
    }

    public function create() {
        return view('property-type.form');
    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'rate_code' => 'required|unique:property_types,name',
            'name' => 'required',
            'description' => 'nullable'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->propertyTypeService::create($payload);

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

        $data = $this->propertyTypeService::getData($id);

        return view('property-type.form', compact('data'));
    }

    public function update(int $id, Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'rate_code' => [
                'required',
                Rule::unique('property_types', 'rate_code')->ignore($id)
            ],
            'name' => 'required',
            'description' => 'nullable'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->propertyTypeService::update($id, $payload);

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

        $response = $this->propertyTypeService::delete($id);

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
                    <div class="d-flex align-items-center gap-3">
                        <a href="' . route('property-types.edit', ['property_type' => $row->id]) . '"
                            class="btn btn-primary text-white text-uppercase fw-bold">
                            <i class="bx bx-edit" ></i>
                        </a>
                        <button data-id="' . $row->id . '"
                            class="btn-delete btn btn-danger text-white text-uppercase fw-bold">
                            <i class="bx bx-trash" ></i>
                        </button>
                    </div>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

}
