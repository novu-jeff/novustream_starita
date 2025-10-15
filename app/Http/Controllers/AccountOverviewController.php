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
use App\Models\PaymentBreakdownPenalty;

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

        $my = Auth::user()->load('property_types', 'accounts.sc_discount');

        $id = $my->id;

        $data = $this->clientService::getData($id);

        $accounts = $data->accounts ?? [];

        $statement = [];

        $statement['transactions'] = [];

        foreach ($accounts as $account) {
            $bill = $this->meterService::getBills($account->account_no);
            if (!empty($bill) && $bill['isPaid'] == 0) {
                $bill['account_no'] = $account->account_no;
                $statement['transactions'][] = $bill;
            }
        }

        $statement['total'] = !empty($statement['transactions'])
            ? array_sum(array_column($statement['transactions'], 'amount'))
            : 0;

        $statement['due_date'] = !empty($statement['transactions'])
            ? collect($statement['transactions'])
            ->pluck('due_date')
            ->filter()
            ->sortDesc()
            ->first()
            : '';

        $statement['measurement'] = env('APP_PRODUCT') == 'novusurge' ? 'kwh' : 'm³';

        $sc_discounts = collect($data['accounts'])->pluck('sc_discount');

        return view('account-overview.index', compact('my', 'data', 'accounts', 'statement', 'sc_discounts'));
    }

            public function bills(Request $request, ?string $reference_no = null)
{
    $userId = Auth::id();

    // View specific bill by reference number
    if ($reference_no) {
    $data = $this->meterService::getBill($reference_no);

    if (!$data) {
        return redirect()->route('reading.index')->with('alert', [
            'status' => 'error',
            'message' => 'Bill Not Found',
        ]);
    }

    // Compute penalties
    $data['current_bill'] = $this->computeBillPenalty($data['current_bill']);

    $url = route('account-overview.bills.reference_no', ['reference_no' => $reference_no]);
    $qr_code = $this->generateService::qr_code($url, 80);
    $isViewBill = true;
    $account_no = null;
    $viewer = 'receipt';

    return view('account-overview.bill', compact('isViewBill', 'data', 'account_no', 'viewer', 'reference_no', 'qr_code'));
}


    $account_no = $request->query('account_no');
    $view = $request->query('view');

    $clientData = $this->clientService::getData($userId);
    $accounts = $clientData->accounts ?? [];

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
        ->rawColumns(['status', 'actions'])
        ->make(true);
}

    }

    private function computeBillPenalty(array $bill): array
{

    $amount = $bill['amount'] ?? 0;
    $dueDate = isset($bill['due_date']) ? Carbon::parse($bill['due_date']) : null;
    $today = Carbon::today();

    $penaltyAmount = 0;
    $daysOverdue = 0;
    $penaltyDate = null;

    if ($dueDate && $today->gt($dueDate)) {
        $daysOverdue = $dueDate->diffInDays($today);
        $penaltyDate = $dueDate->copy()->addDay();

        // Fetch the penalty rule that matches the days overdue
        $penaltyRule = PaymentBreakdownPenalty::where('due_from', '<=', $daysOverdue)
            ->where('due_to', '>=', $daysOverdue)
            ->first();

        if ($penaltyRule) {
            $penaltyBase = $amount;

            if ($penaltyRule->amount_type === 'percentage') {
                $penaltyAmount = round($penaltyBase * floatval($penaltyRule->amount), 2);
            } elseif ($penaltyRule->amount_type === 'fixed') {
                $penaltyAmount = round(floatval($penaltyRule->amount), 2);
            }
        }

    } elseif ($dueDate) {
        $penaltyDate = $dueDate->copy()->addDay();
    }

    $bill['computed_penalty'] = $penaltyAmount;
    $bill['computed_penalty_date'] = $penaltyDate ? $penaltyDate->format('Y-m-d') : null;
    $bill['computed_amount_after_due'] = $amount + $penaltyAmount;
    $bill['days_overdue'] = $daysOverdue;
    $bill['is_overdue'] = $daysOverdue > 0;

    return $bill;
}




}
