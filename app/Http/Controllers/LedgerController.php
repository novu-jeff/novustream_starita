<?php
namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\User;
use App\Services\BillSettlementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LedgerController extends Controller
{
    public function __construct(private readonly BillSettlementService $billSettlementService)
    {
        $this->middleware(function ($request, $next) {

            if (!Gate::any(['admin', 'cashier'])) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $search = $request->search;

        $clients = User::with('accounts')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhereHas('accounts', function ($q) use ($search) {
                        $q->where('account_no', 'like', "%$search%");
                    });
            })
            ->paginate(20);

        return view('ledger.index', compact('clients'));
    }


    public function show(Request $request,$userId)
    {
        $user = User::with('accounts')->findOrFail($userId);

        $accountNumbers = $user->accounts->pluck('account_no');

        $years = Bill::whereHas('reading', function ($query) use ($accountNumbers) {
                $query->whereIn('account_no', $accountNumbers);
            })
            ->selectRaw('YEAR(bill_period_to) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $selectedYear = $request->year ?? $years->first();

        $bills = Bill::with('reading')
        ->whereHas('reading', function ($query) use ($accountNumbers) {
            $query->whereIn('account_no', $accountNumbers);
        })
        ->whereYear('bill_period_to', $selectedYear)
        ->orderBy('bill_period_to', 'asc')
        ->get()
        ->values();

        foreach ($bills as $bill) {
            $metrics = $this->buildLedgerMetrics($bill);

            $bill->computed_amount = $metrics['debit'];
            $bill->computed_paid = $metrics['credit'];
            $bill->computed_balance = $metrics['balance'];
            $bill->computed_penalty = $metrics['penalty'];
            $bill->computed_status = $metrics['status'];
            $bill->computed_arrears = $metrics['carried_arrears'];
            $bill->computed_change = $metrics['change'];
            $bill->computed_date_paid = $metrics['date_paid'];
            $bill->computed_due_date = $metrics['due_date'];
            $bill->advances = $bill->isChangeForAdvancePayment === true ? (float) ($bill->change ?? 0) : 0;
        }

        foreach ($bills as $bill) {
            $today = Carbon::today();
            $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;

            if ($bill->isPaid == 1) {
                $bill->computed_status = 'PAID';
                $bill->computed_balance = 0.0;
                $bill->computed_paid = round((float) $bill->amount_paid - $bill->computed_change, 2);
                continue;
            }

            if ($bill->isPartial == 1) {
                $bill->computed_status = 'PARTIAL';
                $bill->computed_paid = round((float) $bill->partial_payment, 2);
                continue;
            }

            $bill->computed_status = ($dueDate && $today->gt($dueDate)) ? 'OVERDUE' : 'UNPAID';
        }

        return view('ledger.show', compact('user', 'bills', 'selectedYear', 'years'));
    }

    private function buildLedgerMetrics(Bill $bill): array
    {
        $amount = (float) ($bill->amount ?? 0);
        $previousUnpaid = (float) ($bill->previous_unpaid ?? 0);
        $total = (float) ($bill->total ?? 0);
        $amountAfterDue = (float) ($bill->amount_after_due ?? 0);
        $penalty = (float) ($bill->penalty ?? 0);
        $partialPayment = (float) ($bill->partial_payment ?? 0);
        $amountPaid = (float) ($bill->amount_paid ?? 0);
        $change = (float) ($bill->change ?? 0);
        $carriedArrears = round((float) ($bill->previous_unpaid ?? 0), 2);

        $paidDate = !empty($bill->date_paid) ? Carbon::parse($bill->date_paid)->startOfDay() : null;
        $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;
        $today = Carbon::today();

        if ($amountAfterDue <= 0) {
            $amountAfterDue = $amount;
        }

        $debit = max(0, round($total - $previousUnpaid, 2));

        $credit = round(max($amountPaid - $change, $partialPayment), 2);
        $balance = round(max(0, $debit - $credit), 2);

        if ((int) $bill->isPaid === 1) {
            $status = 'PAID';
            $balance = 0.0;
        } elseif ((int) $bill->isPartial === 1 || $partialPayment > 0) {
            $status = 'PARTIAL';
        } elseif ($dueDate && $today->gt($dueDate)) {
            $status = 'OVERDUE';
        } else {
            $status = 'UNPAID';
        }

        $displayPenalty = 0.0;
        if ($dueDate && $today->gt($dueDate)) {
            $displayPenalty = round($penalty, 2);
        }

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $balance,
            'penalty' => $displayPenalty,
            'carried_arrears' => $carriedArrears,
            'status' => $status,
            'change' => $change,
            'date_paid' => $paidDate,
            'due_date' => $dueDate,
        ];
    }

}
