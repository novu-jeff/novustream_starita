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

        $deletionHistories = DB::table('bill_deletion_histories')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('admins.billing-adjustments.index', compact('bills', 'month', 'billAdjustments', 'deletionHistories'));
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
            'created_at' => 'required|date',
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
                'created_at',
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

            $bill->created_at = $request->date('created_at');
            $bill->saveQuietly();

            DB::commit();

            return back()->with('success', 'Bill updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage());
        }
    }

    public function destroy(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $id) {
            $bill = Bill::findOrFail($id);
            $accountNo = optional($bill->reading)->account_no;
            $name = optional(optional($bill->reading)->concessionaire?->user)->name;

            DB::table('bill_deletion_histories')->insert([
                'bill_id' => $bill->id,
                'reference_no' => $bill->reference_no,
                'account_no' => $accountNo,
                'name' => $name,
                'bill_date' => $bill->created_at,
                'amount' => $bill->amount,
                'reason' => $request->reason,
                'deleted_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bill_breakdown')->where('bill_id', $bill->id)->delete();
            if ((float) ($bill->discount ?? 0) > 0) {
                DB::table('bill_discount')->where('bill_id', $bill->id)->delete();
            }
            DB::table('bill_adjustments')->where('bill_id', $bill->id)->delete();
            $reading = $bill->reading()->first();
            $bill->delete();
            if ($reading) {
                DB::table('reading_deletion_histories')->insert([
                    'reading_id' => $reading->id,
                    'account_no' => $reading->account_no,
                    'name' => optional($reading->concessionaire?->user)->name,
                    'reading_date' => $reading->created_at,
                    'previous_reading' => $reading->previous_reading,
                    'present_reading' => $reading->present_reading,
                    'reason' => $request->reason,
                    'deleted_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('reading_adjustments')->where('reading_id', $reading->id)->delete();
                $reading->delete();
            }
        });

        return back()->with('success', 'Bill was deleted successfully.');
    }
}
