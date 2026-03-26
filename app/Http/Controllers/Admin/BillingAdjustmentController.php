<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bill;
use App\Models\BillAdjustment;

class BillingAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Bill::query()
            ->leftJoin('readings', 'bill.reading_id', '=', 'readings.id')
            ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
            ->leftJoin('users', 'ca.user_id', '=', 'users.id')
            ->select(
                'bill.*',
                'readings.account_no',
                'users.name as concessioner_name'
            );

        // 🔍 SEARCH
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('readings.account_no', 'like', '%' . $request->search . '%')
                ->orWhere('users.name', 'like', '%' . $request->search . '%')
                ->orWhere('bill.reference_no', 'like', '%' . $request->search . '%');
            });
        }

        // 📅 MONTH FILTER (based on bill_period_to)
        if ($request->month) {
            $month = $request->month;
        } else {
            // get latest billing period
            $latest = DB::table('bill')->max('bill_period_to');
            $month = $latest ? date('Y-m', strtotime($latest)) : now()->format('Y-m');
        }

        $query->whereYear('bill.bill_period_to', date('Y', strtotime($month)))
            ->whereMonth('bill.bill_period_to', date('m', strtotime($month)));

        $bills = $query->orderByDesc('bill.created_at')
            ->paginate(20)
            ->withQueryString();

        $billAdjustments = DB::table('bill_adjustments as ba')
            ->leftJoin('bill as b', 'ba.bill_id', '=', 'b.id')
            ->leftJoin('readings', 'b.reading_id', '=', 'readings.id')
            ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
            ->leftJoin('users', 'ca.user_id', '=', 'users.id')
            ->select(
                'ba.*',
                'b.reference_no',
                'readings.account_no',
                'users.name as concessioner_name'
            )
            ->orderByDesc('ba.created_at')
            ->limit(200)
            ->get();

        return view('admins.billing-adjustments.index', compact('bills', 'month', 'billAdjustments'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string'
        ]);

        DB::beginTransaction();

        try {
            $bill = Bill::findOrFail($id);

            $oldData = $bill->toArray();

            $newData = $request->only([
                'bill_period_from',
                'bill_period_to',
                'previous_unpaid',
                'total',
                'discount',
                'penalty',
                'amount',
                'amount_after_due',
                'amount_paid',
                'change',
                'isPaid',
                'isPartial',
                'partial_payment',
                'advances',
                'date_paid',
                'due_date',
                'isChangeForAdvancePayment'
            ]);

            BillAdjustment::create([
                'bill_id' => $bill->id,
                'old_amount' => $bill->amount,
                'new_amount' => $request->amount,
                'old_total' => $bill->total,
                'new_total' => $request->total,
                'old_data' => $oldData,
                'new_data' => $newData,
                'reason' => $request->reason,
                'adjusted_by' => auth()->id(),
            ]);

            if ($bill->isPaid && $request->amount != $bill->amount_paid) {

                $difference = $request->amount - $bill->amount_paid;

                if ($difference > 0) {
                    $newData['isPaid'] = 0;
                } else {
                    $newData['advances'] = abs($difference);
                }
            }

            $bill->update($newData);

            DB::commit();

            return back()->with('success', 'Bill updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage());
        }
    }
}
