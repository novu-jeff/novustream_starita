<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientService;
use App\Services\PropertyTypesService;
use App\Services\MeterService;
use App\Models\UserAccounts;
use App\Models\ServiceApplication;
use App\Models\ConcessionerAccountLink;
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
    $zone       = $request->zone ?? 'all';
    $entries    = (int) ($request->entries ?? 10);
    $search     = trim($request->search ?? '');
    $listFilter = $request->list_filter ?? 'all';

    $zones = $this->meterService->getZones()->pluck('area', 'zone');

    /*
     * ============================================================
     * BASE QUERY
     * ============================================================
     */
    $query = \App\Models\User::with('accounts')
        ->leftJoin(
            'concessioner_accounts',
            'users.id',
            '=',
            'concessioner_accounts.user_id'
        )
        ->select('users.*')
        ->whereHas('accounts', function ($q) {
            $q->whereNull('application_status')
              ->orWhere('application_status', 'approved');
        });

    /*
     * ============================================================
     * ZONE FILTER
     * ============================================================
     */
    if ($zone !== 'all') {
        $query->whereHas('accounts', function ($q) use ($zone) {
            $q->where('zone', $zone);
        });
    }

    /*
     * ============================================================
     * LIST FILTER
     * ============================================================
     */
    if ($listFilter === 'seniors') {

        $query->whereHas('accounts', function ($q) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('discount')
                    ->whereColumn(
                        'discount.account_no',
                        'concessioner_accounts.account_no'
                    )
                    ->where('discount.discount_type_id', 1);
            });
        });

    } elseif ($listFilter === 'inactive') {

        $query->whereHas('accounts', function ($q) {
            $q->whereIn('status', ['BL', 'ID', 'IV']);
        });
    }

    /*
     * ============================================================
     * SEARCH
     * ============================================================
     *
     * Search by:
     * - Account Number
     * - Account Name
     *
     * If search exists:
     *
     * 1. Find the exact starting account/name.
     * 2. Get that account's sequence_no and id.
     * 3. Get that account + the next 9 accounts.
     *
     * IMPORTANT:
     *
     * We cannot simply use:
     *
     *     sequence_no >= $startSequence
     *
     * because multiple accounts can have the same sequence number.
     *
     * Example:
     *
     * 5  DIMACALI
     * 5  NACU        <-- searched account
     *
     * Searching NACU must NOT return DIMACALI first.
     *
     * Therefore:
     *
     *     sequence_no > starting sequence
     *
     * OR
     *
     *     sequence_no = starting sequence
     *     AND id >= starting account id
     *
     * ============================================================
     */
    if ($search !== '') {

        /*
         * --------------------------------------------------------
         * Find the starting account
         * --------------------------------------------------------
         */
        $matchedAccountQuery = UserAccounts::query()
            ->whereNotNull('sequence_no')

            /*
             * Account number OR account name
             */
            ->where(function ($q) use ($search) {

                $q->where(
                    'account_no',
                    'like',
                    "%{$search}%"
                )
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where(
                        'name',
                        'like',
                        "%{$search}%"
                    );
                });
            })

            /*
             * Only valid accounts
             */
            ->where(function ($q) {
                $q->whereNull('application_status')
                  ->orWhere('application_status', 'approved');
            });

        /*
         * Zone
         */
        if ($zone !== 'all') {
            $matchedAccountQuery->where('zone', $zone);
        }

        /*
         * Seniors
         */
        if ($listFilter === 'seniors') {

            $matchedAccountQuery->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('discount')
                    ->whereColumn(
                        'discount.account_no',
                        'concessioner_accounts.account_no'
                    )
                    ->where('discount.discount_type_id', 1);
            });
        }

        /*
         * Inactive
         */
        elseif ($listFilter === 'inactive') {

            $matchedAccountQuery->whereIn(
                'status',
                ['BL', 'ID', 'IV']
            );
        }

        /*
         * Find the first matching account by sequence and ID.
         */
        $matchedAccount = $matchedAccountQuery
            ->orderBy('sequence_no', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        /*
         * --------------------------------------------------------
         * MATCH FOUND
         * --------------------------------------------------------
         */
        if ($matchedAccount) {

            $startSequence = $matchedAccount->sequence_no;
            $startId       = $matchedAccount->id;

            /*
             * ----------------------------------------------------
             * Get starting account + next 9 accounts
             * ----------------------------------------------------
             *
             * Example:
             *
             * Starting account:
             *
             * ID 3536
             * Sequence 5
             *
             * Other records:
             *
             * ID 3529  Sequence 5
             * ID 3536  Sequence 5  <-- START
             * ID 3531  Sequence 6
             * ID 3539  Sequence 6
             * ID 3532  Sequence 7
             *
             * We use:
             *
             * sequence > 5
             *
             * OR
             *
             * sequence = 5 AND id >= 3536
             *
             * This removes records BEFORE the searched account.
             */
            $sequenceAccountQuery = UserAccounts::query()
                ->where(function ($q) use ($startSequence, $startId) {

                    $q->where(
                        'sequence_no',
                        '>',
                        $startSequence
                    )
                    ->orWhere(function ($sameSequence) use (
                        $startSequence,
                        $startId
                    ) {

                        $sameSequence
                            ->where(
                                'sequence_no',
                                '=',
                                $startSequence
                            )
                            ->where(
                                'id',
                                '>=',
                                $startId
                            );
                    });
                })

                /*
                 * Only valid accounts
                 */
                ->where(function ($q) {
                    $q->whereNull('application_status')
                      ->orWhere('application_status', 'approved');
                });

            /*
             * Zone
             */
            if ($zone !== 'all') {
                $sequenceAccountQuery->where('zone', $zone);
            }

            /*
             * Seniors
             */
            if ($listFilter === 'seniors') {

                $sequenceAccountQuery->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('discount')
                        ->whereColumn(
                            'discount.account_no',
                            'concessioner_accounts.account_no'
                        )
                        ->where('discount.discount_type_id', 1);
                });
            }

            /*
             * Inactive
             */
            elseif ($listFilter === 'inactive') {

                $sequenceAccountQuery->whereIn(
                    'status',
                    ['BL', 'ID', 'IV']
                );
            }

            /*
             * ----------------------------------------------------
             * IMPORTANT:
             *
             * The ordering must be:
             *
             * sequence_no ASC
             * id ASC
             *
             * This makes the result deterministic when multiple
             * accounts have the same sequence number.
             * ----------------------------------------------------
             */
            $sequenceAccounts = $sequenceAccountQuery
                ->orderBy('sequence_no', 'asc')
                ->orderBy('id', 'asc')
                ->limit(10)
                ->get();

            /*
             * Get the user IDs.
             */
            $userIds = $sequenceAccounts
                ->pluck('user_id')
                ->unique()
                ->values();

            /*
             * Restrict main query to the 10 accounts.
             */
            $query->whereIn(
                'concessioner_accounts.user_id',
                $userIds
            );

            /*
             * Search results always show maximum 10.
             */
            $entries = 10;

        } else {

            /*
             * No matching account.
             */
            $query->whereRaw('1 = 0');

            $entries = 10;
        }
    }

    /*
     * ============================================================
     * FINAL ORDER
     * ============================================================
     *
     * Always display according to:
     *
     * sequence_no ASC
     * id ASC
     *
     * The ID is important because several accounts can have the
     * same sequence number.
     */
    $query
        ->orderBy(
            'concessioner_accounts.sequence_no',
            'asc'
        )
        ->orderBy(
            'concessioner_accounts.id',
            'asc'
        );

    /*
     * ============================================================
     * PAGINATION
     * ============================================================
     */
    $data = $query
        ->paginate($entries)
        ->withQueryString();

    /*
     * ============================================================
     * VIEW
     * ============================================================
     */
    return view(
        'concessionaires.index',
        compact(
            'data',
            'entries',
            'zone',
            'zones',
            'listFilter'
        )
    )->with('toSearch', $search);
}


    public function registrants(Request $request)
    {
        $entries = $request->entries ?? 10;
        $search = trim($request->search ?? '');
        $status = $request->status ?? 'pending';
        $type = $request->type ?? 'all';

        $query = UserAccounts::with(['user.serviceApplications' => function ($query) {
                $query->latest();
            }])
            ->whereNotNull('application_status');

        if ($status === 'pending') {
            $query->where('application_status', 'pending');
        } elseif ($status === 'approved') {
            $query->where('application_status', 'approved');
        } elseif ($status === 'denied') {
            $query->where('application_status', 'denied');
        }

        if ($type === 'existing_account') {
            $query->where(function ($q) {
                $q->where('application_type', 'existing_account')
                    ->orWhereNull('application_type');
            });
        } elseif ($type === 'new_connection') {
            $query->where('application_type', 'new_connection');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('account_no', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('registrants', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query
            ->orderByDesc('created_at')
            ->paginate($entries)
            ->withQueryString();

        $accountLinkRequests = ConcessionerAccountLink::with(['account.user', 'user'])
            ->where('status', 'pending')
            ->when($request->filled('link_search'), function ($query) use ($request) {
                $linkSearch = trim($request->link_search);

                $query->where(function ($searchQuery) use ($linkSearch) {
                    $searchQuery->whereHas('account', function ($accountQuery) use ($linkSearch) {
                        $accountQuery->where('account_no', 'like', "%{$linkSearch}%")
                            ->orWhereHas('user', function ($userQuery) use ($linkSearch) {
                                $userQuery->where('name', 'like', "%{$linkSearch}%")
                                    ->orWhere('registrants', 'like', "%{$linkSearch}%");
                            });
                    })
                    ->orWhere('requested_name', 'like', "%{$linkSearch}%")
                    ->orWhereHas('user', function ($userQuery) use ($linkSearch) {
                        $userQuery->where('name', 'like', "%{$linkSearch}%")
                            ->orWhere('email', 'like', "%{$linkSearch}%");
                    });
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'links_page')
            ->withQueryString();
        $pendingAccountLinkCount = ConcessionerAccountLink::where('status', 'pending')->count();

        $linkSearch = trim($request->link_search ?? '');

        return view('concessionaires.registrants', compact('data', 'entries', 'search', 'status', 'type', 'accountLinkRequests', 'linkSearch', 'pendingAccountLinkCount'));
    }

    public function approveAccountLink(int $link)
    {
        $accountLink = ConcessionerAccountLink::with('account')->findOrFail($link);

        if ($accountLink->status !== 'pending') {
            return back()->with('error', 'This account link request has already been processed.');
        }

        $accountLink->update([
            'status' => 'approved',
            'approved_at' => now(),
            'denied_at' => null,
            'denial_reason' => null,
        ]);

        return back()
            ->with('status', 'Account access approved.')
            ->with('registrant_action', [
                'icon' => 'success',
                'title' => 'Account access approved',
                'message' => 'The linked account was approved successfully.',
            ]);
    }

    public function denyAccountLink(Request $request, int $link)
    {
        $payload = $request->validate([
            'denial_reason' => ['required', 'string', 'max:1000'],
        ]);
        $accountLink = ConcessionerAccountLink::findOrFail($link);

        $accountLink->update([
            'status' => 'denied',
            'approved_at' => null,
            'denied_at' => now(),
            'denial_reason' => $payload['denial_reason'],
        ]);

        return back()
            ->with('status', 'Account access denied.')
            ->with('registrant_action', [
                'icon' => 'success',
                'title' => 'Account access denied',
                'message' => 'The linked account was denied successfully.',
            ]);
    }

    public function completeRegistrant(int $account)
    {
        $account = UserAccounts::with('user')->findOrFail($account);

        if ($account->application_type !== 'new_connection') {
            return redirect()
                ->route('registrants.index')
                ->with('error', 'Only new connection requests need remaining account details.');
        }

        if ($account->application_status !== 'pending') {
            return redirect()
                ->route('registrants.index', ['type' => 'new_connection'])
                ->with('error', 'Only pending new connection requests can be completed.');
        }

        return redirect()
            ->route('concessionaires.edit', ['concessionaire' => $account->user_id, 'registrant' => $account->id]);
    }

    public function printRegistrantForm(int $account)
    {
        $account = UserAccounts::with('user')->findOrFail($account);

        if ($account->application_type !== 'new_connection') {
            return redirect()
                ->route('registrants.index')
                ->with('error', 'Only new connection requests have an application form.');
        }

        $user = $account->user;
        $printData = [
            'sc_no' => $account->sc_no ?? '',
            'meter_no' => $account->meter_serial_no ?? '',
            'account_no' => str_starts_with((string) $account->account_no, 'NEW-') ? '' : ($account->account_no ?? ''),
            'cellphone' => $user->contact_no ?? '',
            'applicant_name' => $user->registrants ?? $user->name ?? '',
            'service_address' => $account->address ?? '',
            'application_type' => 'Water Service Connection',
            'connection_size' => '',
            'installation_location' => $account->address ?? '',
            'signature_name' => $user->registrants ?? $user->name ?? '',
            'application_date' => optional($account->created_at)->format('Y-m-d'),
            'property_owner' => $user->registrants ?? $user->name ?? '',
            'promissory_amount' => '',
        ];

        return view('application.print', compact('printData'));
    }

    public function approveApplication(int $account)
    {
        $account = UserAccounts::with('user')->findOrFail($account);
        $application = ServiceApplication::with('documents')
            ->where('user_id', $account->user_id)
            ->latest()
            ->first();

        if ($account->application_type === 'new_connection'
            && ($application?->connection_type ?? 'on_line') === 'traverse'
            && empty($application?->documents?->boring_permit)) {
            return redirect()
                ->back()
                ->with('error', 'Traverse applications require a Boring/Cutting Permit before approval.')
                ->with('registrant_action', [
                    'icon' => 'warning',
                    'title' => 'Permit required',
                    'message' => 'Please wait for the concessionaire to upload the Boring/Cutting Permit before approval.',
                ]);
        }

        if ($account->application_type === 'new_connection'
            && $application
            && $application->application_fee_status !== 'paid') {
            return redirect()
                ->back()
                ->with('error', 'The application fee must be paid before this application can be approved.')
                ->with('registrant_action', [
                    'icon' => 'warning',
                    'title' => 'Application fee unpaid',
                    'message' => 'The application fee of PHP '
                        . number_format((float) $application->application_fee_amount, 2)
                        . ' has not been paid yet. Please settle the fee before approving this application.',
                ]);
        }

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
            ->with('status', 'Application approved.')
            ->with('registrant_action', [
                'icon' => 'success',
                'title' => 'Application approved',
                'message' => 'The registrant application was approved successfully.',
            ]);
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
            ->with('status', 'Application denied.')
            ->with('registrant_action', [
                'icon' => 'success',
                'title' => 'Application denied',
                'message' => 'The registrant application was denied successfully.',
            ]);
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
        $data?->loadMissing('serviceApplications.documents');
        $registrantId = request('registrant');

        foreach ($data->accounts as $account) {

            $hasSenior = DB::table('discount')
                ->where('account_no', $account->account_no)
                ->where('discount_type_id', 1)
                ->exists();

            $account->has_sc_discount = $hasSenior ? 1 : 0;
        }

        $property_types = $this->propertyTypesService::getData();
        $status_code = $this->clientService::getStatusCode();

        return view('concessionaires.form', compact('data', 'status_code', 'property_types', 'registrantId'));
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

        if ($request->filled('registrant_id') && $newAccountNo) {
            $newAccountId = $payload['accounts'][0]['id'] ?? null;

            if (str_starts_with(strtoupper($newAccountNo), 'NEW-')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'accounts.0.account_no' => ['Please replace the temporary account number before approving this new connection.'],
                ]);
            }

            $accountExists = UserAccounts::where('account_no', $newAccountNo)
                ->when($newAccountId, fn ($q) => $q->where('id', '!=', $newAccountId))
                ->exists();

            if ($accountExists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'accounts.0.account_no' => ['The account number is already registered.'],
                ]);
            }
        }

        DB::beginTransaction();

        try {
            $client = $this->clientService::update($payload, $id);

            if ($request->filled('registrant_id')) {
                $connectionType = $payload['connection_type'] ?? 'on_line';
                $application = ServiceApplication::with('documents')
                    ->where('user_id', $id)
                    ->latest()
                    ->first();

                if ($application) {
                    $application->update([
                        'connection_type' => $connectionType,
                        'application_fee_amount' => $application->application_fee_amount ?? 4000,
                        'application_fee_status' => $application->application_fee_status ?? 'unpaid',
                    ]);
                }

                $canApprove = $connectionType !== 'traverse'
                    || !empty($application?->documents?->boring_permit);

                UserAccounts::where('id', $request->registrant_id)
                    ->where('user_id', $id)
                    ->where('application_type', 'new_connection')
                    ->update([
                        'isApproved' => $canApprove,
                        'application_status' => $canApprove ? 'approved' : 'pending',
                        'approved_at' => $canApprove ? now() : null,
                        'denied_at' => null,
                        'approval_denial_reason' => null,
                    ]);
            }

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

            $message = 'Client ' . $payload['name'] . ' updated successfully.';

            if ($request->filled('registrant_id') && ($payload['connection_type'] ?? 'on_line') === 'traverse') {
                $message = 'Client details saved. Traverse application remains pending until the Boring/Cutting Permit is uploaded and reviewed.';
            }

            return response([
                'data' => $client,
                'status' => 'success',
                'message' => $message
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
