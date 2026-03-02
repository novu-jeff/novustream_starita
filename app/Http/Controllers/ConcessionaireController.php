<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use App\Services\MeterService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;


class ConcessionaireController extends Controller
{

    public $clientService;
    public $propertyTypesService;
    public $meterService;

    public function __construct(MeterService $meterService, ClientService $clientService, PropertyTypesService $propertyTypesService) {

        $this->middleware(function ($request, $next) {

            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });

        $this->clientService = $clientService;
        $this->propertyTypesService = $propertyTypesService;
        $this->meterService = $meterService;
    }

    public function index(Request $request)
    {
        $zone     = $request->zone ?? 'all';
        $entries  = $request->entries ?? 10;
        $search   = trim($request->search ?? '');

        $zones = $this->meterService->getZones()->pluck('area', 'zone');

        $query = \App\Models\User::with('accounts');

        if ($zone !== 'all') {
            $query->whereHas('accounts', function ($q) use ($zone) {
                $q->where('zone', $zone);
            });
        }

        if (!empty($search)) {
            $tokens = preg_split('/\s+/', $search);

            $query->where(function ($q) use ($tokens, $search) {

                foreach ($tokens as $token) {
                    $q->where('name', 'like', "%{$token}%");
                }

                $q->orWhereHas('accounts', function ($aq) use ($search) {
                    $aq->where('account_no', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
                });
            });
        }

        $data = $query
            ->orderByDesc('created_at')
            ->paginate($entries)
            ->withQueryString();

        return view('concessionaires.index', compact(
            'data',
            'entries',
            'zone',
            'zones'
        ))->with('toSearch', $search);
    }


    public function create() {

        $property_types = $this->propertyTypesService::getData();
        $status_code = $this->clientService::getStatusCode();

        return view('concessionaires.form', compact('property_types', 'status_code',));
    }

    public function store(StoreClientRequest $request) {

    $payload = $request->validated();

    // ADD THIS: set zone from account_no
    if (isset($payload['accounts']) && is_array($payload['accounts'])) {
        foreach ($payload['accounts'] as &$account) {
            $account['zone'] = isset($account['account_no'])
                ? substr($account['account_no'], 0, 3)
                : null;
        }
    }

    DB::beginTransaction();

    try {
        $client = $this->clientService::create($payload);
        DB::commit();

        return response([
            'data' => $client,
            'status' => 'success',
            'message' => 'Client ' . $payload['name'] . ' added.'
        ]);

    } catch  (\Exception $e)  {
        DB::rollBack();
        return response(['status' => 'error', 'message' => $e->getMessage()]);
    }
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
        $status_code = $this->clientService::getStatusCode();

        return view('concessionaires.form', compact('data', 'status_code', 'property_types'));
    }

    public function update(int $id, UpdateClientRequest $request)
    {
        $payload = $request->validated();

        $existingClient = $this->clientService::getData($id);
        $oldAccountNo = $existingClient->accounts[0]->account_no ?? null;

        if (isset($payload['accounts']) && is_array($payload['accounts'])) {
            foreach ($payload['accounts'] as &$account) {
                $account['zone'] = isset($account['account_no'])
                    ? substr($account['account_no'], 0, 3)
                    : null;
            }
        }

        $newAccountNo = $payload['accounts'][0]['account_no'] ?? null;

        DB::beginTransaction();

        try {
            $client = $this->clientService::update($payload, $id);

            if ($oldAccountNo && $newAccountNo && $oldAccountNo !== $newAccountNo) {
                DB::table('readings')
                    ->where('account_no', $oldAccountNo)
                    ->update([
                        'account_no' => $newAccountNo,
                        'zone'       => substr($newAccountNo, 0, 3),
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();

            return response([
                'data' => $client,
                'status' => 'success',
                'message' => 'Client ' . $payload['name'] . ' updated successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response([
                'status' => 'error',
                'message' => $e->getMessage()
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



}
