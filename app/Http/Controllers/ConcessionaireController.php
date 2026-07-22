<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use App\Services\MeterService;
use App\Models\UserAccounts;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

            if (!Gate::any(['admin', 'cashier'])) {
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
        $listFilter = $request->list_filter ?? 'all';

        $zones = $this->meterService->getZones()->pluck('area', 'zone');

        $query = \App\Models\User::with('accounts')
        ->leftJoin('concessioner_accounts', 'users.id', '=', 'concessioner_accounts.user_id')
        ->select('users.*');

        if ($zone !== 'all') {
            $query->whereHas('accounts', function ($q) use ($zone) {
                $q->where('zone', $zone);
            });
        }

        if ($listFilter === 'seniors') {
            $query->whereHas('accounts', function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('discount')
                        ->whereColumn('discount.account_no', 'concessioner_accounts.account_no')
                        ->where('discount.discount_type_id', 1);
                });
            });
        } elseif ($listFilter === 'inactive') {
            $query->whereHas('accounts', function ($q) {
                $q->whereIn('status', ['BL', 'ID', 'IV']);
            });
        }

        if (!empty($search)) {

            if (is_numeric($search)) {

                $query->where(
                    'concessioner_accounts.sequence_no',
                    '>=',
                    $search
                );

            } else {

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
        }

        $data = $query
            ->orderBy('sequence_no', 'asc')
            ->paginate($entries)
            ->withQueryString();

        return view('concessionaires.index', compact(
            'data',
            'entries',
            'zone',
            'zones',
            'listFilter'
        ))->with('toSearch', $search);
    }

    public function registrants(Request $request)
    {
        $entries = $request->entries ?? 10;
        $search = trim($request->search ?? '');
        $status = $request->status ?? 'pending';

        $query = UserAccounts::with('user')
            ->where(function ($q) {
                $q->whereNotNull('application_soa_path')
                    ->orWhereNotNull('application_id_path');
            });

        if ($status === 'pending') {
            $query->where('application_status', 'pending');
        } elseif ($status === 'approved') {
            $query->where('application_status', 'approved');
        } elseif ($status === 'denied') {
            $query->where('application_status', 'denied');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('account_no', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query
            ->orderByDesc('created_at')
            ->paginate($entries)
            ->withQueryString();

        return view('concessionaires.registrants', compact('data', 'entries', 'search', 'status'));
    }

    public function approveApplication(int $account)
    {
        $account = UserAccounts::with('user')->findOrFail($account);

        $account->update([
            'isApproved' => true,
            'application_status' => 'approved',
            'approved_at' => now(),
            'denied_at' => null,
            'approval_denial_reason' => null,
        ]);

        $this->sendApplicationDecisionNotification($account, 'approved');

        return redirect()
            ->back()
            ->with('status', 'Application approved.');
    }

    public function denyApplication(Request $request, int $account)
    {
        $payload = $request->validate([
            'approval_denial_reason' => ['required', 'string', 'max:1000'],
        ]);

        $account = UserAccounts::with('user')->findOrFail($account);

        $account->update([
            'isApproved' => false,
            'application_status' => 'denied',
            'approved_at' => null,
            'denied_at' => now(),
            'approval_denial_reason' => $payload['approval_denial_reason'] ?? null,
        ]);

        $this->sendApplicationDecisionNotification($account, 'denied');

        return redirect()
            ->back()
            ->with('status', 'Application denied.');
    }

    private function sendApplicationDecisionNotification(UserAccounts $account, string $decision): void
    {
        $user = $account->user;

        if (!$user || empty($user->email)) {
            return;
        }

        $message = $decision === 'approved'
            ? 'Your concessionaire application has been approved. You can now use your online account services.'
            : 'Your concessionaire application has been denied. Please contact Sta. Rita Water District for more information.';

        if ($decision === 'denied' && !empty($account->approval_denial_reason)) {
            $message .= "\n\nReason: " . $account->approval_denial_reason;
        }

        try {
            Mail::raw($message, function ($mail) use ($user, $decision) {
                $mail->to($user->email)
                    ->subject('Concessionaire Application ' . ucfirst($decision));
            });
        } catch (\Throwable $e) {
            Log::warning('Unable to send application decision email.', [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'decision' => $decision,
                'error' => $e->getMessage(),
            ]);
        }
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
            foreach ($payload['accounts'] as $account) {

                if (!empty($account['has_sc_discount']) && $account['has_sc_discount'] == 1) {

                    DB::table('discount')->insert([
                        'account_no'       => $account['account_no'],
                        'id_no'            => $account['sc_id_no'] ?? null,
                        'discount_type_id' => 1,
                        'effective_date'   => null,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }

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

        foreach ($data->accounts as $account) {

            $hasSenior = DB::table('discount')
                ->where('account_no', $account->account_no)
                ->where('discount_type_id', 1)
                ->exists();

            $account->has_sc_discount = $hasSenior ? 1 : 0;
        }

        $property_types = $this->propertyTypesService::getData();
        $status_code = $this->clientService::getStatusCode();

        return view('concessionaires.form', compact('data', 'status_code', 'property_types'));
    }

    public function update(int $id, UpdateClientRequest $request)
    {
        $payload = $request->validated();

        $payload['name'] = strtoupper(trim($payload['name']));

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

            foreach ($payload['accounts'] as $index => $account) {

                $newAccountNo = $account['account_no'];
                $oldAccountNo = $existingClient->accounts[$index]->account_no ?? null;

                if ((int)($account['has_sc_discount'] ?? 0) === 1) {

                    DB::table('discount')->updateOrInsert(
                        [
                            'account_no' => $newAccountNo,
                            'discount_type_id' => 1,
                        ],
                        [
                            'id_no'          => $account['sc_id_no'] ?? null,
                            'effective_date' => now(),
                            'updated_at'     => now(),
                        ]
                    );

                    // If account number changed, remove old senior row
                    if ($oldAccountNo && $oldAccountNo !== $newAccountNo) {
                        DB::table('discount')
                            ->where('account_no', $oldAccountNo)
                            ->where('discount_type_id', 1)
                            ->delete();
                    }

                } else {

                    DB::table('discount')
                        ->where('discount_type_id', 1)
                        ->where(function ($q) use ($newAccountNo, $oldAccountNo) {
                            $q->where('account_no', $newAccountNo);

                            if ($oldAccountNo) {
                                $q->orWhere('account_no', $oldAccountNo);
                            }
                        })
                        ->delete();
                }
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
