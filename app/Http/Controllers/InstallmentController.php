<?php

namespace App\Http\Controllers;
use App\Models\Bill;
use App\Models\Installment;
use App\Models\InstallmentSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{

    public function index()
    {
        $totalInstallments = Installment::count();

        $activeInstallments = Installment::where('status','active')->count();

        $completedInstallments = Installment::where('status','completed')->count();

        $monthlyCollectible = InstallmentSchedule::where('is_paid',false)
                            ->sum('amount');

        $installments = Installment::with('bill','schedules')
                        ->latest()
                        ->paginate(10);

        $bills = Bill::where('isPaid',0)
                ->where('isInstallment',false)
                ->get();

        return view('installment.index',compact(
            'totalInstallments',
            'activeInstallments',
            'completedInstallments',
            'monthlyCollectible',
            'installments',
            'bills'
        ));
    }


    public function store(Request $request)
    {

        $bill = Bill::findOrFail($request->bill_id);

        $months = $request->months;

        $today = Carbon::today();
        $dueDate = Carbon::parse($bill->due_date);

        // ✅ Determine base amount
        if ($today->lte($dueDate)) {
            // NOT overdue → use total
            $baseAmount = $bill->total;
        } else {
            // overdue → use amount_after_due
            $baseAmount = $bill->amount_after_due;
        }

        $monthly = round($bill->amount / $months,2);

        $accountNo = $bill->reading->account_no;

        $concessioner = \App\Models\ConcessionerAccount::where('account_no', $accountNo)->first();

        $userId = $concessioner ? $concessioner->user_id : null;

        $installment = Installment::create([
            'bill_id' => $bill->id,
            'user_id' => $userId,
            'bill_amount' => $bill->amount,
            'months' => $months,
            'monthly_amount' => $monthly
        ]);

        for($i=1;$i<=$months;$i++){

            InstallmentSchedule::create([
                'installment_id'=>$installment->id,
                'month_no'=>$i,
                'amount'=>$monthly,
                'due_date'=>now()->addMonths($i)
            ]);
        }

        $basicCharge = $bill->total - $bill->previous_unpaid;

        Bill::where('id', $bill->id)->update([
            'isInstallment' => 1,

            // move installment to arrears
            'previous_unpaid' => $monthly,

            // reset totals (ONLY CURRENT BILL)
            'total' => $basicCharge + $monthly,
            'amount' => $basicCharge + $monthly,
            'amount_after_due' => $basicCharge + $monthly,

            // remove penalty
            'penalty' => 0,
            'hasPenalty' => 0
        ]);

        return redirect()->route('installment.index')
            ->with('success','Installment created');
    }

    public function details($id)
    {
        $installment = Installment::with([
            'bill.reading.concessionaire.user',
            'schedules' => function ($q) {
                $q->orderBy('month_no');
            }
        ])->findOrFail($id);

        $accountNo = $installment->bill->reading->account_no;

        $concessioner = \App\Models\ConcessionerAccount::with('user')
            ->where('account_no', $accountNo)
            ->first();

        return response()->json([
            'account_no' => $accountNo,
            'name' => $concessioner?->user?->name,
            'reference_no' => $installment->bill->reference_no,
            'bill_amount' => $installment->bill_amount,
            'monthly_amount' => $installment->monthly_amount,
            'schedules' => $installment->schedules
        ]);
    }

    public function getBillsByAccount(Request $request)
    {
        $accountNo = $request->account_no;

        $bills = Bill::where('isPaid',0)
            ->where('isInstallment',false)
            ->whereHas('reading', function($q) use ($accountNo){
                $q->where('account_no',$accountNo);
            })
            ->get();

        return response()->json($bills);
    }
}
