<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Imports\ConcessionaireImport;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

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

        if (!$request->hasFile('file')) {
            return response([
                'status' => 'error',
                'message' => 'No file uploaded.'
            ]);
        }

        $file = $request->file('file');

        if (!$file->isValid() || $file->getClientOriginalExtension() !== 'csv' || !in_array($file->getMimeType(), ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only CSV files are allowed.',
            ]);
        }

        $headings = (new HeadingRowImport())->toArray($file)[0][0];

        $expected = [
            'account_no', 'name', 'address', 'rate_code', 'status',
            'meter_brand', 'meter_serial_no', 'sc_no', 'date_connected',
            'contact_no', 'sequence_no'
        ];

        $missing = array_diff($expected, array_values($headings));

        if (count($missing)) {
            return response([
                'status' => 'error',
                'message' => 'Invalid file. Please make sure to upload the correct template.',
                'missing_headers' => array_values($missing),
            ]);
        }
        
        try {

            $import = new ConcessionaireImport;

            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');

            Excel::import($import, $file, null, null, [
                'readOnly' => true,
            ]);

            $failures = $import->failures();

            if ($failures->isNotEmpty()) {

                $messages = [];

                foreach ($failures as $failure) {
                    $row = $failure->row();
                    foreach ($failure->errors() as $error) {
                        $messages[] = "Row [$row]: $error";
                    }
                }

                return response()->json([
                    'status' => 'warning',
                    'message' => 'Some rows were skipped due to validation errors.',
                    'errors' => $messages,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Concessionaires imported successfully.',
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            
            $failures = $e->failures();
            $messages = [];

            foreach ($failures as $failure) {
                $row = $failure->row();
                foreach ($failure->errors() as $error) {
                    $messages[] = "Row [$row]: $error";
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors found during import.',
                'errors' => $messages,
            ]);

        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during import: ' . $e->getMessage(),
            ], 500);
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
