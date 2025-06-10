<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Imports\ConcessionaireImport;
use App\Imports\SCDiscountImport;
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
        return view('concessionaires.import', [
            'label' => "Client's CSV File",
            'toProcess' => 'concessionaire',
        ]);
    }

    public function import_senior(Request $request) {
        
       return view('concessionaires.import', [
            'label' => 'Senior Discount File',
            'toProcess' => 'sc_discount',
        ]);

    }

    private function getUploadErrorMessage($errorCode)
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error.',
        };
    }

    public function import_action(Request $request)
    {
        if (!$request->hasFile('file')) {
            return $this->errorResponse('No file uploaded.');
        }

        $file = $request->file('file');
        $toProcess = $request->toProcess;

        $allowedProcesses = [
            'concessionaire' => [
                'expected_headers' => [
                    'account_no', 'name', 'address', 'rate_code', 'status',
                    'meter_brand', 'meter_serial_no', 'sc_no', 'date_connected',
                    'contact_no', 'sequence_no'
                ],
                'extensions' => ['csv'],
                'mime_types' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                'import_class' => ConcessionaireImport::class,
                'success_message' => 'Concessionaires imported successfully.'
            ],
            'sc_discount' => [
                'expected_headers' => [
                    'account_no', 'name', 'id_no', 'effectivity_date', 'expired_date'
                ],
                'extensions' => ['xls', 'xlsx'],
                'mime_types' => [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ],
                'import_class' => SCDiscountImport::class,
                'success_message' => 'Senior Citizen Discount imported successfully.'
            ]
        ];

        if (!isset($allowedProcesses[$toProcess])) {
            return $this->errorResponse("Process '{$toProcess}' unavailable");
        }

        $processConfig = $allowedProcesses[$toProcess];

        // Validate file
        if (
            !$file->isValid() ||
            !in_array($file->getClientOriginalExtension(), $processConfig['extensions']) ||
            !in_array($file->getMimeType(), $processConfig['mime_types'])
        ) {
            return $this->errorResponse("Only " . implode(', ', $processConfig['extensions']) . " files are allowed.");
        }

        // Check headers
        $headings = (new HeadingRowImport())->toArray($file)[0][0] ?? [];
        $missing = array_diff($processConfig['expected_headers'], array_values($headings));

        if (count($missing)) {
            return $this->errorResponse('Invalid file. Please make sure to upload the correct template.', [
                'missing_headers' => array_values($missing)
            ]);
        }

        try {
            $import = new $processConfig['import_class'];

            Excel::import($import, $file, null, null, ['readOnly' => true]);

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
                'message' => $processConfig['success_message'],
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->errorResponse('An error occurred during import: ' . $e->getMessage(), [], 500);
        }
    }

    private function errorResponse($message, array $extra = [], int $status = 400)
    {
        return response()->json(array_merge([
            'status' => 'error',
            'message' => $message,
        ], $extra), $status);
    }

    private function handleValidationException($e)
    {
        $messages = [];

        foreach ($e->failures() as $failure) {
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
