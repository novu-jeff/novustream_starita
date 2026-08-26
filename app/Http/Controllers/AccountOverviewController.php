<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\PaymentBreakdownPenalty;
use App\Models\Bill;
use App\Models\ServiceApplication;
use App\Models\UserAccounts;
use App\Models\ConcessionerAccountLink;

class AccountOverviewController extends Controller
{

    public $clientService;
    public $meterService;
    public $generateService;

    public function __construct(ClientService $clientService, MeterService $meterService, GenerateService $generateService) {
        $this->clientService = $clientService;
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

public function index()
{
    $my = Auth::user()->load(
        'property_types',
        'accounts.sc_discount',
        'accounts.user',
        'accountLinks.account.sc_discount',
        'accountLinks.account.property_types',
        'accountLinks.account.user'
    );
    $id = $my->id;

    $data = $this->clientService::getData($id) ?? $my;
    $accounts = collect($data->accounts ?? []);
    foreach ($my->accountLinks->whereIn('status', ['pending', 'approved']) as $link) {
        if ($link->account) {
            $link->account->access_link_status = $link->status;
            $accounts->push($link->account);
        }
    }
    $accounts = $accounts->unique('id')->values();
    $data->setRelation('accounts', $accounts);
    $approvalNotice = $this->approvalNotice($accounts);
    $applicationNotification = $this->applicationNotification($accounts);
    $accountNotifications = $this->accountNotifications($my, $accounts, $applicationNotification);
    $canApplyForNewServiceConnection = $this->canApplyForNewServiceConnection($accounts);
    $serviceApplication = ServiceApplication::with('documents')
        ->where('user_id', $id)
        ->latest()
        ->first();

    $applicationStatus = $accounts
        ->pluck('application_status')
        ->filter()
        ->first();
    $approvedAccounts = collect($accounts)->filter(function ($account) {
        return $this->canUseAccount($account);
    });

    $statement = [];
    $statement['transactions'] = [];

    foreach ($approvedAccounts as $account) {
        $bill = $this->meterService::getBills($account->account_no);

        // Only include unpaid bills
        if (!empty($bill) && ($bill['isPaid'] ?? 0) == 0) {
            $bill['account_no'] = $account->account_no;
            $statement['transactions'][] = $bill;
        }
    }

    // Determine the current bill
    $statement['current_bill'] = collect($statement['transactions'])
        ->filter(function ($bill) {
            return ($bill['isPaid'] ?? 0) == 0
                && (
                    empty($bill['amount_paid']) ||
                    floatval($bill['amount_paid']) < floatval($bill['amount'])
                );
        })
        ->sortByDesc('due_date')
        ->first();

        if (!empty($statement['current_bill'])) {
            $statement['current_bill'] =
                $this->computeBillPenalty($statement['current_bill']);
        }


    // Compute total for all transactions
    $statement['total'] = !empty($statement['transactions'])
        ? array_sum(array_map(function($bill) {
            $amount = $bill['total'] ?? 0;
            $discount = $bill['discount'] ?? 0;
            $advance = $bill['advances'] ?? 0;

        $penalty = $statement['current_bill']['penalty'] ?? 0;
        $dueDate = isset($data['current_bill']['due_date'])
                        ? \Carbon\Carbon::parse($data['current_bill']['due_date'])
                        : null;

        $today = \Carbon\Carbon::today();

        $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;

            return ($amount + $applicablePenalty) - ($discount + $advance);
        }, $statement['transactions']))
        : 0;

    $statement['due_date'] = !empty($statement['transactions'])
        ? collect($statement['transactions'])
            ->pluck('due_date')
            ->filter()
            ->sortDesc()
            ->first()
        : '';

    $statement['measurement'] = env('APP_PRODUCT') == 'novusurge' ? 'kwh' : 'm³';

    $accountStatements = [];
    foreach ($accounts as $account) {
        $accountStatement = [
            'account' => $account,
            'transactions' => [],
            'total' => 0,
        ];

        if ($this->canUseAccount($account)) {
            $bill = $this->meterService::getBills($account->account_no);

            if (!empty($bill) && ($bill['isPaid'] ?? 0) == 0) {
                $bill = $this->computeBillPenalty($bill);
                $bill['account_no'] = $account->account_no;
                $accountStatement['transactions'][] = $bill;
                $discount = is_array($bill['discount'] ?? null)
                    ? collect($bill['discount'])->sum('amount')
                    : (float) ($bill['discount'] ?? 0);
                $accountStatement['total'] = (float) ($bill['total'] ?? $bill['amount'] ?? 0)
                    + (float) ($bill['computed_penalty'] ?? 0)
                    - $discount
                    - (float) ($bill['advances'] ?? 0);
            }
        }

        $accountStatements[] = $accountStatement;
    }

    $sc_discounts = $accounts->pluck('sc_discount');

    // -----------------------------
    // Generate online payment URL
    // -----------------------------
    $statement['current_bill_qr'] = null;

    if (!empty($statement['current_bill'])) {

        $currentBill = $statement['current_bill'];

        $payload = [
            'reference_no' => $currentBill['reference_no'] ?? '',
            'amount' => $currentBill['amount'] ?? 0,
            'customer' => [
                'name' => $data->name ?? '',
                'account_no' => $currentBill['account_no'] ?? '',
                'address' => $currentBill['address'] ?? '',
            ],
        ];

        $paymentController = app(\App\Http\Controllers\PaymentController::class);
        $qrResolved = $paymentController->resolveSoaPaymentQrUrl(
            $currentBill['reference_no'] ?? '',
            $currentBill,
            $payload
        );
        $statement['current_bill_qr'] = $qrResolved['url'];
    }

    return view('account-overview.index', compact('my', 'data', 'accounts', 'statement', 'accountStatements', 'sc_discounts', 'approvalNotice', 'applicationNotification', 'accountNotifications', 'canApplyForNewServiceConnection', 'applicationStatus', 'serviceApplication'));
}

public function addAccount(Request $request)
{
    $validated = $request->validate([
        'account_no' => ['required', 'string', 'max:255'],
        'name' => ['required', 'string', 'max:255'],
        'soa_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'id_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'data_privacy_consent' => ['accepted'],
    ]);

    $user = Auth::user();

    DB::transaction(function () use ($request, $validated, $user) {
        $account = UserAccounts::with('user')
            ->where('account_no', trim($validated['account_no']))
            ->lockForUpdate()
            ->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account_no' => 'Account no. was not found in our records.',
            ]);
        }

        if ($account->user_id === $user->id) {
            throw ValidationException::withMessages([
                'account_no' => 'This account is already linked to your login.',
            ]);
        }

        if ($account->application_status === 'denied' || $account->denied_at) {
            throw ValidationException::withMessages([
                'account_no' => 'This account was denied and cannot be added. Please contact the district office.',
            ]);
        }

        $existingLink = ConcessionerAccountLink::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingLink) {
            throw ValidationException::withMessages([
                'account_no' => 'This account is already linked or waiting for approval on your login.',
            ]);
        }

        ConcessionerAccountLink::updateOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $user->id,
            ],
            [
                'requested_name' => $validated['name'],
                'soa_path' => $request->file('soa_file')->store('applications/soa', 'public'),
                'id_path' => $request->file('id_file')->store('applications/id', 'public'),
                'status' => 'pending',
                'approved_at' => null,
                'denied_at' => null,
                'denial_reason' => null,
            ]
        );
    });

    return redirect()
        ->route('account-overview.index')
        ->with('status', 'Additional account submitted for verification.');
}

    public function getBillColumns()
    {
        // Fetch the first 50 rows from the bill table
        $bills = DB::table('bill')->limit(50)->get();

        return $bills;
    }

            public function bills(Request $request, ?string $reference_no = null)
{
    $userId = Auth::id();
    $user = Auth::user();
    $clientData = $user;
    $accounts = $this->accessibleAccounts($userId);

    if (!$this->hasUsableAccount($accounts)) {
        return redirect()
            ->route('account-overview.index')
            ->with('approval_notice', $this->approvalNotice($accounts));
    }

    $accounts = collect($accounts)->filter(function ($account) {
        return $this->canUseAccount($account);
    })->values();

    // View specific bill by reference number
    if ($reference_no) {
    $data = $this->meterService::getBill($reference_no);

    if (!$data) {
        return redirect()->route('reading.index')->with('alert', [
            'status' => 'error',
            'message' => 'Bill Not Found',
        ]);
    }

    $billAccountNo = $data['current_bill']['reading']['account_no']
        ?? $data['client']['account_no']
        ?? $data['current_bill']['account_no']
        ?? null;

    $validAccountNos = $accounts->pluck('account_no')->toArray();
    if (!in_array($billAccountNo, $validAccountNos)) {
        return redirect()->route('account-overview.bills')->with('alert', [
            'status' => 'error',
            'message' => 'Invalid bill reference for your account.',
        ]);
    }

    // Compute penalties
    $data['current_bill'] = $this->computeBillPenalty($data['current_bill']);
    $currentBill = $data['current_bill'];

    // 🧮 Use dynamic penalty computation (from PaymentBreakdownPenalty)
        $amount = (float)($currentBill['total'] ?? 0);
        $amount_afterDue = (float)($currentBill['total'] ?? 0);
        $discount = (float)($currentBill['discount'] ?? 0);
        $currentDay = now()->day;

        $penaltyEntry = \App\Models\PaymentBreakdownPenalty::where('due_from', '<=', $currentDay)
            ->where('due_to', '>=', $currentDay)
            ->first();

        $penalty = $currentBill['penalty'] ?? 0;
        $dueDate = isset($data['current_bill']['due_date'])
                        ? \Carbon\Carbon::parse($data['current_bill']['due_date'])
                        : null;

        $today = \Carbon\Carbon::today();

        $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;

        // ✅ Always ensure defaults
        $assumedPenalty = 0;
        $assumedAmountAfterDue = $amount_afterDue + $applicablePenalty;

        // 🔹 Try to compute based on dynamic penalty config
        if ($penaltyEntry) {
            $penaltyBase = $amount - $discount;

            if ($penaltyEntry->amount_type === 'percentage') {
                $assumedPenalty = $penaltyBase * floatval($penaltyEntry->amount);
            } elseif ($penaltyEntry->amount_type === 'fixed') {
                $assumedPenalty = floatval($penaltyEntry->amount);
            }
        } else {
            // fallback 10%
            $assumedPenalty = $amount * 0.10;
        }

        $assumedAmountAfterDue = $amount - $discount;

        $data['current_bill']['assumed_penalty'] = $applicablePenalty;
        $data['current_bill']['assumed_amount_after_due'] = $assumedAmountAfterDue;

        // 💰 Add service fees
        $hitpay_fee = 20;
        $novupay_fee = 10;
        $additional_service_fee = $hitpay_fee + $novupay_fee;

        $final_amount = $assumedAmountAfterDue + $additional_service_fee;

        // 🧾 Build payment payload
        $paymentPayload = [
            'reference_no' => $reference_no,
            'amount' => $final_amount,
            'customer' => [
                'name' => $data['client']['name'] ?? '',
                'account_no' => $billAccountNo ?? '',
                'address' => $data['client']['address'] ?? '',
            ],
        ];

    $paymentController = app(\App\Http\Controllers\PaymentController::class);
    $qrResolved = $paymentController->resolveSoaPaymentQrUrl(
        $reference_no,
        $data['current_bill'] ?? [],
        $paymentPayload
    );
    $url = $qrResolved['url'];

    $qr_code = $this->generateService::qr_code($url, 80);
    $payment_url = $url;
    $isViewBill = true;
    $account_no = null;
    $viewer = 'receipt';

    return view('account-overview.bill', compact('isViewBill', 'data', 'account_no', 'viewer', 'reference_no', 'qr_code', 'payment_url'));
}


    $account_no = $request->query('account_no');
    $view = $request->query('view');

    $validAccountNos = $accounts->pluck('account_no')->toArray();

    $isAccountNoValid = !empty($account_no) && in_array($account_no, $validAccountNos);
    $isViewValid = in_array($view, ['unpaid', 'paid']);

    if ((!$isAccountNoValid) && !$isViewValid) {
        if ($account_no !== null || $view !== null) {
            return redirect()->route('account-overview.bills');
        }
    }

    $statements = [];
    $isPaid = $view === 'paid';

    foreach ($accounts as $account) {
        $bills = $this->meterService::getBills($account->account_no, true, $isPaid);

        if (!empty($bills)) {
            // Compute penalty for each bill
            $bills = array_map(function ($bill) {
                return $this->computeBillPenalty($bill);
            }, $bills);

            $statements[$account->account_no] = $bills;
        }
    }


    if ($isAccountNoValid && $isViewValid) {
        $data = $statements[$account_no] ?? [];

        if ($request->ajax() && $request->has('account_no') && $request->has('view')) {
            return $this->datatable('bills', $data);
        }

        $viewer = 'bills';
        return view('account-overview.bill', compact('viewer', 'account_no', 'view'));
    }

    if ($request->ajax()) {
        return $this->datatable('account_nos', $accounts);
    }

    $viewer = 'accounts';
    return view('account-overview.bill', compact('viewer'));
}



    public function datatable($type, $query)
    {

        if($type == 'account_nos') {
            return  DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('account_no', function ($row) {
                    return $row['account_no'];
                })
                ->editColumn('meter_no', function ($row) {
                    return $row['meter_serial_no'];
                })
                ->editColumn('address', function ($row) {
                    return $row['address'] ?? 'N/A';
                })
                ->editColumn('property_type', function ($row) {
                    return $row['property_type'] ?? 'N/A';
                })
                ->editColumn('date_connected', function ($row) {
                    return $row['date_connected'] ?? 'N/A';
                })

                ->addColumn('actions', function ($row) {
                    return '<div class="d-flex align-items-center gap-2">
                        <a href="' . e(route('account-overview.bills', [
                            'account_no' => $row['account_no'],
                            'view' => 'unpaid'
                        ])) . '"
                            class="btn btn-primary text-white text-uppercase fw-bold">
                            <i class="bx bx-receipt"></i>
                        </a>
                    </div>';
                })
                ->rawColumns(['status', 'actions'])
                ->make(true);
        }

        if($type == 'bills') {
    return DataTables::of($query)
        ->addIndexColumn()
        ->editColumn('billing_period', function ($row) {
            return ($row['bill_period_from'] && $row['bill_period_to'])
                ? Carbon::parse($row['bill_period_from'])->format('M d, Y') . ' TO ' . Carbon::parse($row['bill_period_to'])->format('M d, Y')
                : 'N/A';
        })
        ->editColumn('bill_date', function ($row) {
            return $row['bill_period_to'] ? Carbon::parse($row['bill_period_to'])->format('M d, Y') : 'N/A';
        })
        ->editColumn('due_date', function ($row) {
            return $row['due_date'] ? Carbon::parse($row['due_date'])->format('M d, Y') : 'N/A';
        })
        ->editColumn('penalty_date', function ($row) {
            return $row['due_date']
                ? Carbon::parse($row['due_date'])->addDay()->format('M d, Y')
                : '—';
        })
        ->editColumn('penalty_amount', function ($row) {
            return isset($row['penalty'])
                ? '₱' . number_format($row['penalty'], 2)
                : '₱0.00';
        })
        ->editColumn('amount_after_due', function ($row) {
            return isset($row['amount_after_due'])
                ? '₱' . number_format($row['amount_after_due'], 2)
                : '₱' . number_format($row['amount'], 2);
        })
        ->editColumn('status', function ($row) {
            return $row['isPaid']
                ? '<div class="alert alert-primary mb-0 py-1 px-2 text-center">Paid</div>'
                : '<div class="alert alert-danger mb-0 py-1 px-2 text-center">Unpaid</div>';
        })
        ->addColumn('actions', function ($row) {
            $reference_no = $row['reference_no'] ?? null;
            if ($reference_no) {
                return '<div class="d-flex align-items-center gap-2">
                    <a href="' . e(route('account-overview.bills.reference_no', $reference_no)) . '"
                        class="btn btn-primary text-white text-uppercase fw-bold"
                        id="show-btn" data-id="' . e($row['id']) . '">
                        <i class="bx bx-receipt"></i>
                    </a>
                </div>';
            }
            return '<span class="text-muted">No Reference</span>';
        })
       ->addColumn('pay', function ($row) {
    $reference_no = is_array($row) ? ($row['reference_no'] ?? null) : ($row->reference_no ?? null);

    if (empty($reference_no)) {
        return '<span class="text-muted">No Reference</span>';
    }

    return '<div class="d-flex align-items-center gap-2">
        <button type="button"
            class="btn btn-success text-white text-uppercase fw-bold pay-now-btn"
            data-reference="' . e($reference_no) . '"
            data-id="' . e($row['id'] ?? '') . '">
            <i class="bx bx-credit-card"></i> Pay Now
        </button>
    </div>';
})

        ->rawColumns(['status', 'actions', 'pay'])
        ->make(true);
}

    }

    public function payOnline(Request $request, string $reference_no)
{
    // Get the current authenticated user's accounts
    $userId = Auth::id();
    $user = Auth::user();
    $clientData = $user;
    $accounts = $this->accessibleAccounts($userId);

    if (!$this->hasUsableAccount($accounts)) {
        return redirect()
            ->route('account-overview.index')
            ->with('approval_notice', $this->approvalNotice($accounts));
    }

    $accounts = collect($accounts)->filter(function ($account) {
        return $this->canUseAccount($account);
    });

    // Check if reference_no belongs to this user's accounts
    $validReference = false;
    foreach ($accounts as $account) {
        $bill = $this->meterService::getBill($reference_no);
        if ($bill && $bill['current_bill']['account_no'] == $account->account_no) {
            $validReference = true;
            break;
        }
    }

    if (!$validReference) {
        return redirect()->back()->with('alert', [
            'status' => 'error',
            'message' => 'Invalid bill reference for your account.'
        ]);
    }

    // Prepare payload for online payment
    $payload = [
        'payor' => $clientData->name ?? 'Customer',
        'email' => $clientData->email ?? 'customer@example.com',
        'account_no' => $bill['current_bill']['account_no'],
        'amount' => $bill['current_bill']['amount'] ?? 0,
    ];

    $paymentController = app(\App\Http\Controllers\PaymentController::class);

    if (\App\Http\Controllers\PaymentController::isSoaQrVoided($bill['current_bill'] ?? [])) {
        return redirect()->route('payments.qr-voided', ['reference_no' => $reference_no]);
    }

    $hitpayData = $paymentController->createHitpayPaymentRequest($reference_no, $payload);

    if (!$hitpayData || empty($hitpayData['url'])) {
        return redirect()->back()->with('alert', [
            'status' => 'error',
            'message' => 'Failed to initiate online payment.'
        ]);
    }

    return redirect($hitpayData['url']);
}

    private function hasUsableAccount($accounts): bool
    {
        return collect($accounts)->contains(function ($account) {
            return $this->canUseAccount($account);
        });
    }

    private function canUseAccount($account): bool
    {
        if (!$account) {
            return false;
        }

        if ($this->applicationStatus($account) === 'denied') {
            return false;
        }

        if ($this->applicationStatus($account) === null) {
            return true;
        }

        if (($account->access_link_status ?? null) === 'approved') {
            return true;
        }

        if ($this->applicationStatus($account) === 'approved') {
            return true;
        }

        return !$this->isRegistrationApplication($account);
    }

    private function accessibleAccounts(int $userId): \Illuminate\Support\Collection
    {
        $user = Auth::user()->loadMissing('accounts.sc_discount', 'accounts.user');
        $accounts = $user->accounts;

        $links = ConcessionerAccountLink::with('account.sc_discount', 'account.property_types', 'account.user')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->get();

        foreach ($links as $link) {
            if ($link->account) {
                $link->account->access_link_status = 'approved';
                $accounts->push($link->account);
            }
        }

        return $accounts->unique('id')->values();
    }

    private function isRegistrationApplication($account): bool
    {
        return !empty($account->application_status)
            || !empty($account->application_soa_path)
            || !empty($account->application_id_path);
    }

    private function canApplyForNewServiceConnection($accounts): bool
    {
        return collect($accounts)->contains(function ($account) {
            return ($account->application_type ?? null) === 'new_connection';
        });
    }

    private function approvalNotice($accounts): ?array
    {
        $accounts = collect($accounts);

        if ($accounts->isEmpty() || $this->hasUsableAccount($accounts)) {
            return null;
        }

        $deniedApplication = $accounts->first(fn ($account) => $this->applicationStatus($account) === 'denied');

        if ($deniedApplication) {
            $message = 'Your concessionaire application was denied. Please contact Sta. Rita Water District for more information.';

            if (!empty($deniedApplication->approval_denial_reason)) {
                $message .= ' Reason: ' . $deniedApplication->approval_denial_reason;
            }

            return [
                'status' => 'danger',
                'message' => $message,
            ];
        }

        return [
            'status' => 'warning',
            'message' => 'Your application is currently in the approval stage. You can view your account overview, but online services are unavailable until approval.',
        ];
    }

    private function applicationNotification($accounts): ?array
    {
        $application = collect($accounts)
            ->filter(fn ($account) => $this->isRegistrationApplication($account))
            ->sortBy('updated_at')
            ->first();

        if (!$application) {
            return null;
        }

        if ($this->applicationStatus($application) === 'approved') {
            return [
                'status' => 'success',
                'title' => 'Application approved',
                'message' => 'Your concessionaire application has been approved. You can now use your online account services.',
                'date' => optional($application->approved_at)->format('M d, Y h:i A'),
            ];
        }

        if ($this->applicationStatus($application) === 'denied') {
            $message = 'Your concessionaire application was denied. Please contact Sta. Rita Water District for more information.';

            if (!empty($application->approval_denial_reason)) {
                $message .= ' Reason: ' . $application->approval_denial_reason;
            }

            return [
                'status' => 'danger',
                'title' => 'Application denied',
                'message' => $message,
                'date' => optional($application->denied_at)->format('M d, Y h:i A'),
            ];
        }

        return [
            'status' => 'warning',
            'title' => 'Application pending',
            'message' => 'Your application is currently in the approval stage.',
            'date' => optional($application->created_at)->format('M d, Y h:i A'),
        ];
    }

    private function accountNotifications($user, $accounts, ?array $applicationNotification): array
    {
        $notifications = [];

        if ($applicationNotification) {
            $notifications[] = $applicationNotification + [
                'type' => 'application',
            ];
        }

        $accountNos = collect($accounts)
            ->pluck('account_no')
            ->filter()
            ->values();

        if ($accountNos->isNotEmpty()) {
            $latestUnpaidBill = Bill::with('reading')
                ->where('isPaid', false)
                ->whereHas('reading', fn ($query) => $query->whereIn('account_no', $accountNos))
                ->latest('created_at')
                ->first();

            if ($latestUnpaidBill) {
                $notifications[] = [
                    'type' => 'bill',
                    'status' => 'danger',
                    'title' => 'Bill to pay',
                    'message' => 'A new statement of account is available. Amount due: PHP ' . number_format((float) ($latestUnpaidBill->amount ?? 0), 2) . '.',
                    'date' => optional($latestUnpaidBill->created_at)->format('M d, Y h:i A'),
                    'timestamp' => optional($latestUnpaidBill->created_at)->timestamp,
                ];
            }

            $latestPaidBill = Bill::with('reading')
                ->where('isPaid', true)
                ->whereHas('reading', fn ($query) => $query->whereIn('account_no', $accountNos))
                ->latest('date_paid')
                ->latest('updated_at')
                ->first();

            if ($latestPaidBill) {
                $paidAt = $this->notificationDate($latestPaidBill->date_paid) ?? $latestPaidBill->updated_at;

                $notifications[] = [
                    'type' => 'payment',
                    'status' => 'success',
                    'title' => 'Payment posted',
                    'message' => 'Your payment for reference ' . $latestPaidBill->reference_no . ' was successfully posted.',
                    'date' => optional($paidAt)->format('M d, Y h:i A'),
                    'timestamp' => optional($paidAt)->timestamp,
                ];
            }
        }

        $latestAccountUpdate = collect($accounts)
            ->filter(fn ($account) => $account->updated_at && $account->created_at && $account->updated_at->gt($account->created_at))
            ->sortBy('updated_at')
            ->first();

        if ($latestAccountUpdate) {
            $notifications[] = [
                'type' => 'account',
                'status' => 'info',
                'title' => 'Account updated',
                'message' => 'Your concessionaire account information was updated.',
                'date' => optional($latestAccountUpdate->updated_at)->format('M d, Y h:i A'),
                'timestamp' => optional($latestAccountUpdate->updated_at)->timestamp,
            ];
        } elseif ($user->updated_at && $user->created_at && $user->updated_at->gt($user->created_at)) {
            $notifications[] = [
                'type' => 'account',
                'status' => 'info',
                'title' => 'Profile updated',
                'message' => 'Your profile information was updated.',
                'date' => optional($user->updated_at)->format('M d, Y h:i A'),
                'timestamp' => optional($user->updated_at)->timestamp,
            ];
        }

        return collect($notifications)
            ->map(function ($notification) {
                $notification['timestamp'] = $notification['timestamp'] ?? $this->timestampFromNotificationDate($notification['date'] ?? null);

                return $notification;
            })
            ->sortBy('timestamp')
            ->values()
            ->all();
    }

    private function notificationDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function timestampFromNotificationDate(?string $date): int
    {
        return optional($this->notificationDate($date))->timestamp ?? 0;
    }

    private function applicationStatus($account): ?string
    {
        if (!empty($account->application_status)) {
            return $account->application_status;
        }

        if ((bool) ($account->isApproved ?? false)) {
            return 'approved';
        }

        if (!empty($account->denied_at)) {
            return 'denied';
        }

        return $this->isRegistrationApplication($account) ? 'pending' : null;
    }


    private function computeBillPenalty(array $bill): array
{
    $amount = (float) ($bill['amount'] ?? 0);
    $penaltyAmount = (float) ($bill['penalty'] ?? 0);

    $dueDate = isset($bill['due_date']) ? Carbon::parse($bill['due_date']) : null;
    $today = Carbon::today();

    $daysOverdue = 0;
    $penaltyDate = null;

    if ($dueDate && $today->gt($dueDate)) {
        $daysOverdue = $dueDate->diffInDays($today);
        $penaltyDate = $dueDate->copy()->addDay();
    }

    $bill['computed_penalty'] = $penaltyAmount;
    $bill['computed_penalty_date'] = $penaltyDate?->format('Y-m-d');
    $bill['computed_amount_after_due'] = $amount + $penaltyAmount;
    $bill['days_overdue'] = $daysOverdue;
    $bill['is_overdue'] = $daysOverdue > 0;

    return $bill;
}

}
