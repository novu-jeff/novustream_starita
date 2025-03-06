<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\PropertyTypesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ClientController extends Controller
{

    public $clientService;
    public $propertyTypesService;

    public function __construct(ClientService $clientService, PropertyTypesService $propertyTypesService) {
        
        $this->middleware(function ($request, $next) {
    
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });

        $this->clientService = $clientService;
        $this->propertyTypesService = $propertyTypesService;
    }

    public function index() {

        $data = $this->clientService::getData();

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('clients.index', compact('data'));
    }

    public function create() {

        $property_types = $this->propertyTypesService::getData();

        return view('clients.form', compact('property_types'));
    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'firstname' => 'required',
            'lastname' => 'required',
            'middlename' => 'nullable',
            'address' => 'required',
            'contact_no' => 'required',
            'email' => 'required|unique:users,email',
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
            'isValidated' => 'required|in:true,false',
            'contract_no' => 'required|unique:users,contract_no',
            'contract_date' => 'required',
            'property_type' => 'required|exists:property_types,id',
            'meter_no' => 'required'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->clientService::create($payload);

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

        $data = $this->clientService::getData($id);
        $property_types = $this->propertyTypesService::getData();

        return view('clients.form', compact('data', 'property_types'));
    }

    public function update(int $id, Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'firstname' => 'required',
            'lastname' => 'required',
            'middlename' => 'nullable',
            'address' => 'required',
            'contact_no' => 'required',
            'email' => ['required', Rule::unique('users')->ignore($id)],
            'password' => 'nullable|min:8|required_with:confirm_password',
            'confirm_password' => 'nullable|same:password|required_with:password',
            'isValidated' => 'required|in:true,false',
            'contract_no' => ['required', Rule::unique('users', 'contract_no')->ignore($id)],
            'contract_date' => 'required',
            'property_type' => 'required|exists:property_types,id',
            'meter_no' => 'required'
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $response = $this->clientService::update($id, $payload);

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

        $response = $this->clientService::delete($id);

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
                    <a href="' . route('clients.edit', $row->id) . '" 
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
