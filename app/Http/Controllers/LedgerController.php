<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\ConcessionerAccount;
use App\Models\User;
use App\Models\Bill;

class LedgerController extends Controller
{
    public function __construct()
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


    public function show($userId)
    {
        $user = User::with('accounts')->findOrFail($userId);

        $accountNumbers = $user->accounts->pluck('account_no');

        $bills = Bill::with(['reading'])
            ->whereHas('reading', function ($query) use ($accountNumbers) {
                $query->whereIn('account_no', $accountNumbers)
                ->where('isReRead', 0);
            })
            ->orderBy('bill_period_to', 'asc')
            ->get()
            ->map(function ($bill) {

                $total = (float) $bill->total;
                $amount = (float) $bill->amount;
                $penalty = (float) $bill->penalty;
                $totalPaid = (float) $bill->amount_paid;
                $discount = (float) $bill->discount ?? 0;
                $change = (float) $bill->change ?? 0;
                $advances = (float) $bill->advances ?? 0;
                $previousReading = (float) $bill->previous_reading ?? 0;

                $paidDate = $bill->date_paid
                    ? \Carbon\Carbon::parse($bill->date_paid)->startOfDay()
                    : null;

                $dueDate = $bill->due_date
                    ? \Carbon\Carbon::parse($bill->due_date)->startOfDay()
                    : null;

                $today = \Carbon\Carbon::today();

                $appliedPenalty = 0;

                if (!$paidDate && $dueDate && $today->gt($dueDate)) {
                    $appliedPenalty = $penalty;
                }

                if ($paidDate && $dueDate && $paidDate->gt($dueDate)) {
                    $appliedPenalty = $penalty;
                    $totalAmount = $total;
                } else {
                    $totalAmount = $amount;
                }

                $finalAmount = $totalAmount - $previousReading+ $appliedPenalty - $discount - $advances - $change;

                $balance = $finalAmount - $totalPaid;

                if ($balance <= 0 || $bill->isPaid == 1) {
                    $status = 'PAID';
                    $balance = 0;
                } elseif ($totalPaid > 0) {
                    $status = 'PARTIAL';
                } elseif ($dueDate && $today->gt($dueDate)) {
                    $status = 'OVERDUE';
                } else {
                    $status = 'UNPAID';
                }

                $amountPaid = (float) $bill->amount_paid;
                $change     = (float) $bill->change;

                $bill->computed_amount  = $finalAmount;
                $bill->computed_paid    = $bill->isChangeForAdvancePayment === false ? $amountPaid - $change : $amountPaid;
                $bill->computed_balance = max(0, $balance);
                $bill->computed_status  = $status;
                $bill->advances         = $bill->isChangeForAdvancePayment === true ? $change : 0;
                return $bill;
            });

        return view('ledger.show', compact('user', 'bills'));
    }

}
