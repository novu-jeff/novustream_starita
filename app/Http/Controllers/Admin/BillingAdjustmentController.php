<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bill;
use App\Models\BillAdjustment;
use App\Models\BillBreakdown;

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

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('readings.account_no', 'like', '%' . $request->search . '%')
                ->orWhere('users.name', 'like', '%' . $request->search . '%')
                ->orWhere('bill.reference_no', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->month) {
            $month = $request->month;
        } else {
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
            'reason' => 'required|string',
            'previous_unpaid' => 'required|numeric|min:0',
            'basic_charge' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'change' => 'nullable|numeric|min:0',
            'partial_payment' => 'nullable|numeric|min:0',
            'advances' => 'nullable|numeric|min:0',
            'date_paid' => 'nullable|date',
            'due_date' => 'nullable|date',
            'isPaid' => 'required|boolean',
            'isPartial' => 'nullable|boolean',
            'isChangeForAdvancePayment' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            $bill = Bill::findOrFail($id);
            $oldData = $bill->toArray();
            $oldData['basic_charge'] = round((float) ($bill->total ?? 0) - (float) ($bill->previous_unpaid ?? 0), 2);
            $previousUnpaid = round((float) $request->previous_unpaid, 2);
            $basicCharge = round((float) $request->basic_charge, 2);
            $total = round($previousUnpaid + $basicCharge, 2);
            $discount = $request->filled('discount')
                ? round((float) $request->discount, 2)
                : 0;
            $penalty = round((float) $request->penalty, 2);
            $amount = round((float) $request->amount, 2);
            $amountAfterDue = round((float) $request->amount_after_due, 2);

            $newData = $request->only([
                'bill_period_from',
                'bill_period_to',
                'previous_unpaid',
                'discount',
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

            foreach (['amount_paid', 'change', 'partial_payment'] as $nullableField) {
                $newData[$nullableField] = $request->filled($nullableField)
                    ? round((float) $request->input($nullableField), 2)
                    : null;
            }

            $newData['isPaid'] = $request->has('isPaid') ? (int) $request->boolean('isPaid') : 0;
            $newData['isChangeForAdvancePayment'] = $request->has('isChangeForAdvancePayment') ? (int) $request->boolean('isChangeForAdvancePayment') : 0;
            $newData['discount'] = $discount;
            $newData['total'] = $total;
            $newData['penalty'] = $penalty;
            $newData['amount'] = $amount;
            $newData['amount_after_due'] = $amountAfterDue;

            $newDataForHistory = array_merge($newData, [
                'basic_charge' => $basicCharge,
            ]);

            BillAdjustment::create([
                'bill_id' => $bill->id,
                'old_amount' => $bill->amount,
                'new_amount' => $amount,
                'old_total' => $bill->total,
                'new_total' => $total,
                'old_data' => $oldData,
                'new_data' => $newDataForHistory,
                'reason' => $request->reason,
                'adjusted_by' => auth()->id(),
            ]);

            BillBreakdown::updateOrCreate([
                'bill_id' => $bill->id,
                'name' => 'Basic Charge',
            ], [
                'amount' => number_format($basicCharge, 2, '.', ''),
                'description' => 'Updated by billing adjustment',
            ]);

            if ($bill->isPaid && $amount != $bill->amount_paid) {
                $difference = $amount - $bill->amount_paid;

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
