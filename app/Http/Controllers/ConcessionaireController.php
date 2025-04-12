<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Imports\ClientImport;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ConcessionaireController extends Controller
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

        return view('concessionaires.index', compact('data'));
    }

    public function create() {

        $property_types = $this->propertyTypesService::getData();

        return view('concessionaires.form', compact('property_types'));
    }

    public function store(StoreClientRequest $request) {
        
        $payload = $request->validated();

        DB::beginTransaction();

        try {

            $client = $this->clientService::create($payload);

            DB::commit();

            return response(['data' => $client, 'status' => 'success', 'message' => 'Client ' . $payload['name'] . ' added.']);

        } catch  (\Exception $e)  {

            DB::rollBack();

            return response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function import_view() {
        return view('concessionaires.import');
    }

    public function import_action(Request $request)
    {
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
    
            Excel::import(new ClientImport, $request->file('file'));


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
    }

    public function edit(int $id) {

        $data = $this->clientService::getData($id);
        $property_types = $this->propertyTypesService::getData();
        
        return view('concessionaires.form', compact('data', 'property_types'));
    }

    public function update(int $id, UpdateClientRequest $request) {


        $payload = $request->validated();

        DB::beginTransaction();

        try {

            $client = $this->clientService::update($payload, $id);

            DB::commit();

            return response(['data' => $client, 'status' => 'success', 'message' => 'Client ' . $payload['name'] . ' update succesfully.']);

        } catch (\Exception $e) {

            DB::rollBack();

            return response(['status' => 'error', 'message' => $e->getMessage()]);
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
            ->addColumn('accounts', function ($user) {
                return $user->accounts->pluck('account_no')->implode(', '); 
            })
            ->addColumn('actions', function ($row) {
                return '
                <div class="d-flex align-items-center gap-2">
                    <a href="' . route('concessionaires.edit', $row->id) . '" 
                        class="btn btn-secondary text-white text-uppercase fw-bold" 
                        id="update-btn" data-id="' . e($row->id) . '">
                        <i class="bx bx-edit-alt"></i>
                    </a>
                    <button class="btn btn-danger text-white text-uppercase fw-bold btn-delete" id="delete-btn" data-id="' . e($row->id) . '">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>';
            })
            ->rawColumns(['actions', 'accounts'])
            ->make(true);
    }
}
