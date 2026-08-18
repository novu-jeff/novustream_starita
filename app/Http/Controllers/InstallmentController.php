<?php

namespace App\Http\Controllers;
use App\Models\Bill;
use App\Models\ConcessionerAccount;
use App\Models\Installment;
use App\Models\InstallmentAdjustment;
use App\Models\InstallmentSchedule;
use App\Models\BillDiscount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentController extends Controller
{

    public function index()
    {
        $totalInstallments = Installment::count();

        $activeInstallments = Installment::where('status','active')->count();

        $completedInstallments = Installment::where('status','completed')->count();

        $monthlyCollectible = Installment::where('status', 'active')
        ->with(['schedules' => function ($query) {
            $query->where('is_paid', 0)
                ->orderBy('due_date');
        }])
        ->get()
        ->sum(function ($installment) {
            return optional($installment->schedules->first())->amount ?? 0;
        });

        $installments = Installment::with('bill.reading.concessionaire.user','schedules')
                        ->latest()
                        ->paginate(10);

        $bills = Bill::where('isPaid',0)
                ->where('isInstallment',false)
                ->get();

        $installmentAdjustments = InstallmentAdjustment::with('bill.reading.concessionaire.user')
            ->latest()
            ->limit(200)
            ->get();

        return view('installment.index',compact(
            'totalInstallments',
            'activeInstallments',
            'completedInstallments',
            'monthlyCollectible',
            'installments',
            'bills',
            'installmentAdjustments'
        ));
    }


    public function store(Request $request)
    {
        $request->validate([
            'bill_id' => ['required', 'exists:bill,id'],
            'months' => ['required', 'integer', 'min:1', 'max:60'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            $bill = Bill::with('reading')->lockForUpdate()->findOrFail($request->bill_id);

            if ($bill->isPaid || $bill->isInstallment) {
                throw ValidationException::withMessages([
                    'bill_id' => 'This bill is already paid or already under installment.',
                ]);
            }

            $months = (int) $request->months;
            $oldData = [
                'installment' => null,
                'bill' => $this->billSnapshot($bill),
                'schedules' => [],
            ];

            $baseAmount = $this->installmentBaseAmount($bill);
            $monthly = round($baseAmount / $months, 2);

            $accountNo = $bill->reading?->account_no;

            $concessioner = ConcessionerAccount::where('account_no', $accountNo)->first();

            if (!$concessioner) {
                throw ValidationException::withMessages([
                    'bill_id' => 'No concessionaire account was found for this bill.',
                ]);
            }

            $userId = $concessioner->id;

            $installment = Installment::create([
                'bill_id' => $bill->id,
                'user_id' => $userId,
                'bill_amount' => $baseAmount,
                'months' => $months,
                'monthly_amount' => $monthly,
                'status' => 'active',
            ]);

            $firstDueDate = Carbon::parse($bill->due_date);

            $this->rebuildSchedules($installment, $months, $monthly, $firstDueDate);

            $bill->update($this->installmentBillData($monthly));

            BillDiscount::where('bill_id', $bill->id)->delete();

            $installment->load('schedules');

            InstallmentAdjustment::create([
                'installment_id' => $installment->id,
                'bill_id' => $bill->id,
                'action' => 'created',
                'old_data' => $oldData,
                'new_data' => $this->installmentSnapshot($installment),
                'reason' => $request->reason ?: 'Installment created.',
                'adjusted_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('installment.index')
                ->with('success','Installment created');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors($e->getMessage());
        }
    }

    public function update(Request $request, Installment $installment)
    {
        $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:60'],
            'first_due_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            $installment = Installment::with('bill', 'schedules')
                ->lockForUpdate()
                ->findOrFail($installment->id);

            if ($installment->schedules->contains(fn ($schedule) => (bool) $schedule->is_paid)) {
                throw ValidationException::withMessages([
                    'months' => 'Installments with paid schedules cannot be edited. Delete or edit only before payment starts.',
                ]);
            }

            $oldData = $this->installmentSnapshot($installment);
            $months = (int) $request->months;
            $monthly = round((float) $installment->bill_amount / $months, 2);
            $firstDueDate = Carbon::parse($request->first_due_date);

            $installment->update([
                'months' => $months,
                'monthly_amount' => $monthly,
                'status' => 'active',
            ]);

            $this->rebuildSchedules($installment, $months, $monthly, $firstDueDate);
            $installment->bill->update($this->installmentBillData($monthly));

            $installment->load('schedules', 'bill');

            InstallmentAdjustment::create([
                'installment_id' => $installment->id,
                'bill_id' => $installment->bill_id,
                'action' => 'updated',
                'old_data' => $oldData,
                'new_data' => $this->installmentSnapshot($installment),
                'reason' => $request->reason,
                'adjusted_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('installment.index')
                ->with('success', 'Installment updated.');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors($e->getMessage());
        }
    }

    public function destroy(Request $request, Installment $installment)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            $installment = Installment::with('bill', 'schedules', 'adjustments')
                ->lockForUpdate()
                ->findOrFail($installment->id);

            if ($installment->schedules->contains(fn ($schedule) => (bool) $schedule->is_paid)) {
                throw ValidationException::withMessages([
                    'reason' => 'Installments with paid schedules cannot be deleted. Delete only before payment starts.',
                ]);
            }

            $oldData = $this->installmentSnapshot($installment);
            $restoreData = $this->restoreBillData($installment);
            $bill = $installment->bill;

            InstallmentAdjustment::create([
                'installment_id' => $installment->id,
                'bill_id' => $installment->bill_id,
                'action' => 'deleted',
                'old_data' => $oldData,
                'new_data' => [
                    'bill_restore' => $restoreData,
                ],
                'reason' => $request->reason,
                'adjusted_by' => auth()->id(),
            ]);

            $installment->delete();

            if ($bill) {
                $bill->update($restoreData);
            }

            DB::commit();

            return redirect()->route('installment.index')
                ->with('success', 'Installment deleted and bill restored.');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors($e->getMessage());
        }
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

        $concessioner = ConcessionerAccount::with('user')
            ->where('account_no', $accountNo)
            ->first();

        return response()->json([
            'account_no' => $accountNo,
            'name' => $concessioner?->user?->name,
            'reference_no' => $installment->bill->reference_no,
            'bill_amount' => $installment->bill_amount,
            'monthly_amount' => $installment->monthly_amount,
            'months' => $installment->months,
            'status' => $installment->status,
            'schedules' => $installment->schedules
        ]);
    }

    public function getBillsByAccount(Request $request)
    {
        $search = $request->search;

        $bills = Bill::where('isPaid', 0)
            ->where('isInstallment', false)
            ->whereHas('reading', function ($reading) use ($search) {

                $reading->where('account_no', 'like', "%{$search}%")
                    ->orWhereHas('concessionaire.user', function ($user) use ($search) {

                        $user->where('name', 'like', "%{$search}%");

                    });

            })
            ->with('reading.concessionaire.user')
            ->get()
            ->map(function ($bill) {

                return [
                    'id' => $bill->id,
                    'reference_no' => $bill->reference_no,
                    'bill_period_to' => $bill->bill_period_to,
                    'due_date' => $bill->due_date,
                    'total' => (float) ($bill->total ?? 0),
                    'amount_after_due' => (float) ($bill->amount_after_due ?? 0),
                    'partial_payment' => (float) ($bill->partial_payment ?? 0),
                    'account_no' => $bill->reading->account_no,
                    'name' => optional($bill->reading->concessionaire->user)->name,
                ];

            });

        return response()->json($bills);
    }

    private function installmentBaseAmount(Bill $bill): float
    {
        $today = Carbon::today();
        $dueDate = $bill->due_date ? Carbon::parse($bill->due_date) : null;
        $baseAmount = $dueDate && $today->lte($dueDate)
            ? $bill->total
            : $bill->amount_after_due;

        return max(0, round((float) $baseAmount - (float) ($bill->partial_payment ?? 0), 2));
    }

    private function installmentBillData(float $monthly): array
    {
        return [
            'previous_unpaid' => 0,
            'total' => $monthly,
            'amount' => $monthly,
            'amount_after_due' => $monthly,
            'penalty' => 0,
            'hasPenalty' => 0,
            'isInstallment' => 1,
        ];
    }

    private function rebuildSchedules(Installment $installment, int $months, float $monthly, Carbon $firstDueDate): void
    {
        InstallmentSchedule::where('installment_id', $installment->id)->delete();

        for ($i = 1; $i <= $months; $i++) {
            InstallmentSchedule::create([
                'installment_id' => $installment->id,
                'month_no' => $i,
                'amount' => $monthly,
                'due_date' => $firstDueDate->copy()->addMonths($i - 1),
            ]);
        }
    }

    private function installmentSnapshot(Installment $installment): array
    {
        $installment->loadMissing('bill', 'schedules');

        return [
            'installment' => $installment->only([
                'id',
                'bill_id',
                'user_id',
                'bill_amount',
                'months',
                'monthly_amount',
                'status',
            ]),
            'bill' => $installment->bill ? $this->billSnapshot($installment->bill) : null,
            'schedules' => $installment->schedules
                ->map(fn ($schedule) => $schedule->only(['id', 'month_no', 'amount', 'due_date', 'is_paid', 'paid_at']))
                ->values()
                ->toArray(),
        ];
    }

    private function billSnapshot(Bill $bill): array
    {
        return $bill->only([
            'id',
            'previous_unpaid',
            'total',
            'discount',
            'penalty',
            'amount',
            'amount_after_due',
            'amount_paid',
            'change',
            'isPaid',
            'isInstallment',
            'isPartial',
            'partial_payment',
            'hasPenalty',
            'hasDisconnection',
            'hasDisconnected',
            'date_paid',
            'due_date',
            'penalty_date',
            'disconnection_date',
        ]);
    }

    private function restoreBillData(Installment $installment): array
    {
        $createHistory = $installment->adjustments
            ->where('action', 'created')
            ->sortBy('created_at')
            ->first();

        $billSnapshot = data_get($createHistory?->old_data, 'bill');

        if (is_array($billSnapshot)) {
            return collect($billSnapshot)
                ->only([
                    'previous_unpaid',
                    'total',
                    'discount',
                    'penalty',
                    'amount',
                    'amount_after_due',
                    'amount_paid',
                    'change',
                    'isPaid',
                    'isInstallment',
                    'isPartial',
                    'partial_payment',
                    'hasPenalty',
                    'hasDisconnection',
                    'hasDisconnected',
                    'date_paid',
                    'due_date',
                    'penalty_date',
                    'disconnection_date',
                ])
                ->toArray();
        }

        return [
            'previous_unpaid' => 0,
            'total' => $installment->bill_amount,
            'amount' => $installment->bill_amount,
            'amount_after_due' => $installment->bill_amount,
            'penalty' => 0,
            'hasPenalty' => 0,
            'isInstallment' => 0,
        ];
    }
}
