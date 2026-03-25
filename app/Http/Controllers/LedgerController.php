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


    public function show($userId)
    {
        $user = User::with('accounts')->findOrFail($userId);

        $accountNumbers = $user->accounts->pluck('account_no');

        $bills = Bill::with(['reading'])
            ->whereHas('reading', function ($query) use ($accountNumbers) {
                $query->whereIn('account_no', $accountNumbers);
            })
            ->orderBy('bill_period_to', 'asc')
            ->get()
            ->values();

        $openBalances = [];

        foreach ($bills as $index => $bill) {
            $metrics = $this->buildLedgerMetrics($bill);

            $bill->computed_amount = $metrics['display_amount'];
            $bill->computed_paid = 0.0;
            $bill->computed_balance = $metrics['row_due'];
            $bill->computed_penalty = $metrics['display_penalty'];
            $bill->computed_status = 'UNPAID';
            $bill->computed_arrears = $metrics['carried_arrears'];
            $bill->computed_own_amount = $metrics['own_amount'];
            $bill->computed_allocated_to_arrears = 0.0;
            $bill->advances = $bill->isChangeForAdvancePayment === true ? (float) ($bill->change ?? 0) : 0;

            $arrearsPool = min($metrics['carried_arrears'], $metrics['raw_payment']);
            $remainingArrearsPool = $arrearsPool;

            foreach ($openBalances as $openIndex => $remaining) {
                if ($remaining <= 0 || $remainingArrearsPool <= 0) {
                    continue;
                }

                $applied = round(min($remaining, $remainingArrearsPool), 2);
                $bills[$openIndex]->computed_paid = round($bills[$openIndex]->computed_paid + $applied, 2);
                $bills[$openIndex]->computed_balance = round(max(0, $bills[$openIndex]->computed_balance - $applied), 2);
                $remainingArrearsPool = round($remainingArrearsPool - $applied, 2);
                $openBalances[$openIndex] = round(max(0, $remaining - $applied), 2);
            }

            $bill->computed_allocated_to_arrears = $arrearsPool - $remainingArrearsPool;

            $ownPayment = round(max(0, $metrics['raw_payment'] - $arrearsPool), 2);
            $appliedOwnPayment = round(min($metrics['row_due'], $ownPayment), 2);

            $bill->computed_paid = round($bill->computed_paid + $appliedOwnPayment, 2);
            $bill->computed_balance = round(max(0, $metrics['row_due'] - $bill->computed_paid), 2);
            $openBalances[$index] = $bill->computed_balance;
        }

        foreach ($bills as $bill) {
            $today = Carbon::today();
            $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;

            if ($bill->computed_balance <= 0.01) {
                $bill->computed_status = 'PAID';
                $bill->computed_balance = 0.0;
                continue;
            }

            if ($bill->computed_paid > 0) {
                $bill->computed_status = 'PARTIAL';
                continue;
            }

            $bill->computed_status = ($dueDate && $today->gt($dueDate)) ? 'OVERDUE' : 'UNPAID';
        }

        return view('ledger.show', compact('user', 'bills'));
    }

    private function buildLedgerMetrics(Bill $bill): array
    {
        $total = (float) ($bill->total ?? 0);
        $penalty = (float) ($bill->penalty ?? 0);
        $discount = (float) ($bill->discount ?? 0);
        $advances = (float) ($bill->advances ?? 0);
        $change = (float) ($bill->change ?? 0);
        $partialPayment = (float) ($bill->partial_payment ?? 0);
        $carriedArrears = max(0, min((float) ($bill->previous_unpaid ?? 0), $total));

        $paidDate = !empty($bill->date_paid) ? Carbon::parse($bill->date_paid)->startOfDay() : null;
        $dueDate = !empty($bill->due_date) ? Carbon::parse($bill->due_date)->startOfDay() : null;
        $today = Carbon::today();

        $displayPenalty = 0.0;
        $displayAmount = $total;

        if (!$paidDate && $dueDate && $today->gt($dueDate)) {
            $displayPenalty = $penalty;
            $displayAmount += $penalty;
        } elseif ($paidDate && $dueDate && $paidDate->gt($dueDate)) {
            $displayPenalty = $penalty;
            $displayAmount += $penalty;
        }

        $displayAmount = max(0, round($displayAmount - $discount - $advances, 2));
        $rowDue = max(0, round($displayAmount - $carriedArrears, 2));

        $rawAmountPaid = (float) ($bill->amount_paid ?? 0);
        $adjustedPaid = $bill->isChangeForAdvancePayment ? $rawAmountPaid : max(0, $rawAmountPaid - $change);
        $rawPayment = max($adjustedPaid, $partialPayment);
        $inferredSettledAmount = $this->billSettlementService->inferSettledAmount($bill, $paidDate ?? $today);

        if ($bill->isPaid && $rawPayment + 0.009 < $inferredSettledAmount) {
            $rawPayment = $inferredSettledAmount;
        }

        return [
            'display_amount' => $displayAmount,
            'display_penalty' => $displayPenalty,
            'carried_arrears' => round($carriedArrears, 2),
            'own_amount' => $rowDue,
            'row_due' => $rowDue,
            'raw_payment' => round($rawPayment, 2),
        ];
    }

}
