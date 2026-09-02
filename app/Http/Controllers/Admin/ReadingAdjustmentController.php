<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Reading;
use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Models\ReadingAdjustment;
use App\Models\ConcessionerAccount;
use App\Models\Rates;

class ReadingAdjustmentController extends Controller
{
    public function index(Request $request)
{
    $query = Reading::query()
        ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
        ->leftJoin('users', 'ca.user_id', '=', 'users.id')
        ->select(
            'readings.*',
            'users.name as concessioner_name'
        );

    if ($request->search) {
        $query->where(function ($q) use ($request) {
            $q->where('readings.account_no', 'like', '%' . $request->search . '%')
              ->orWhere('users.name', 'like', '%' . $request->search . '%');
        });
    }

    if ($request->month) {
        $month = $request->month;
    } else {
        $latest = Reading::max('created_at');

        $month = $latest ? date('Y-m', strtotime($latest)) : now()->format('Y-m');
    }

    $query->whereYear('readings.created_at', date('Y', strtotime($month)))
          ->whereMonth('readings.created_at', date('m', strtotime($month)));

    $readings = $query->orderByDesc('readings.created_at')
        ->paginate(20)
        ->withQueryString();

    $missingAccounts = collect();

    if ($request->filled('search')) {
        $search = trim($request->search);

        $missingAccounts = ConcessionerAccount::with('user')
            ->whereDoesntHave('readings')
            ->where(function ($q) use ($search) {
                $q->where('account_no', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            })
            ->where(function ($q) {
                $q->whereNull('application_status')
                    ->orWhere('application_status', 'approved');
            })
            ->orderBy('sequence_no')
            ->limit(20)
            ->get();
    }

    $allAdjustments = ReadingAdjustment::leftJoin('readings', 'reading_adjustments.reading_id', '=', 'readings.id')
    ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
    ->leftJoin('users', 'ca.user_id', '=', 'users.id')
    ->select(
        'reading_adjustments.*',
        'readings.account_no',
        'users.name as concessioner_name'
    )
    ->orderByDesc('reading_adjustments.created_at')
    ->limit(200)
    ->get();

    return view('admins.reading-adjustments.index', compact('readings', 'month', 'allAdjustments', 'missingAccounts'));
}

    public function edit($id)
    {
        $reading = Reading::findOrFail($id);

        return view('admins.reading-adjustments.edit', compact('reading'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'present_reading' => 'required|numeric|min:0',
            'new_previous_reading' => 'nullable|numeric|min:0',
            'reading_datetime' => 'required|date',
            'reason' => 'required|string'
        ]);

        DB::beginTransaction();

        try {
            $reading = Reading::findOrFail($id);

            $oldPresent = $reading->present_reading;
            $oldPrevious = $reading->previous_reading;
            $oldConsumption = $reading->consumption;

            $newPresent = $request->present_reading;
            $newPrevious = $request->new_previous_reading !== null && $request->new_previous_reading !== ''
                ? $request->new_previous_reading
                : $oldPrevious;

            // Validation: present reading should not be less than previous reading
            if ($newPresent < $newPrevious) {
                return back()->withErrors('Present reading cannot be less than previous reading.');
            }

            $newConsumption = $newPresent - $newPrevious;

            $account = ConcessionerAccount::where('account_no', $reading->account_no)->first();

            $rate = Rates::where('property_types_id', $account->rate_code)
                ->orderBy('cu_m')
                ->get();

            $amount = 0;
            foreach ($rate as $r) {
                if ($newConsumption <= $r->cu_m) {
                    $amount = $r->amount;
                    break;
                }
            }

            $readingDateTime = Carbon::parse($request->reading_datetime);

            ReadingAdjustment::create([
                'reading_id' => $reading->id,
                'old_present_reading' => $oldPresent,
                'new_present_reading' => $newPresent,
                'old_consumption' => $oldConsumption,
                'new_consumption' => $newConsumption,
                'reason' => $request->reason,
                'adjusted_by' => auth()->id(),
            ]);

            $reading->update([
                'previous_reading' => $newPrevious,
                'present_reading' => $newPresent,
                'consumption' => $newConsumption,
                'created_at' => $readingDateTime,
                'updated_at' => $readingDateTime,
            ]);

            DB::commit();

            return redirect()->route('admins.reading-adjustments.index')
                ->with('success', 'Reading adjusted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage());
        }
    }

    public function createInitial(Request $request)
    {
        $payload = $request->validate([
            'account_no' => ['required', 'string', 'exists:concessioner_accounts,account_no'],
            'reading_date' => ['required', 'date'],
        ]);

        DB::beginTransaction();

        try {
            $account = ConcessionerAccount::with('user')
                ->where('account_no', $payload['account_no'])
                ->lockForUpdate()
                ->firstOrFail();

            if (Reading::where('account_no', $account->account_no)->exists()) {
                return back()
                    ->withInput()
                    ->withErrors('This concessionaire already has a reading record.');
            }

            $readingDate = Carbon::parse($payload['reading_date'])->startOfDay();
            $storedDate = $readingDate->format('Y-m-d H:i:s');
            $referenceNo = $this->generateInitialReferenceNo();
            $readerName = auth()->user()->name ?? 'Admin';

            $reading = Reading::create([
                'zone' => $account->zone ?? substr($account->account_no, 0, 3),
                'account_no' => $account->account_no,
                'reference_no' => $referenceNo,
                'previous_reading' => 0,
                'present_reading' => 0,
                'consumption' => 0,
                'reader_name' => $readerName,
                'created_at' => $storedDate,
                'updated_at' => $storedDate,
            ]);

            $bill = Bill::create([
                'reading_id' => $reading->id,
                'reference_no' => $referenceNo,
                'bill_period_from' => $storedDate,
                'bill_period_to' => $storedDate,
                'previous_unpaid' => 0,
                'total' => 0,
                'discount' => 0,
                'penalty' => 0,
                'amount' => 0,
                'amount_after_due' => 0,
                'amount_paid' => 0,
                'change' => 0,
                'isPaid' => true,
                'isInstallment' => false,
                'isPartial' => false,
                'hasPenalty' => false,
                'hasDisconnection' => false,
                'hasDisconnected' => false,
                'date_paid' => $storedDate,
                'due_date' => $storedDate,
                'penalty_date' => $storedDate,
                'disconnection_date' => $storedDate,
                'payor_name' => $account->user->name ?? null,
            ]);

            $bill->created_at = $storedDate;
            $bill->updated_at = $storedDate;
            $bill->saveQuietly();

            $breakdown = BillBreakdown::create([
                'bill_id' => $bill->id,
                'name' => 'Initial Reading',
                'description' => 'Initial zero paid bill',
                'amount' => 0,
            ]);

            $breakdown->created_at = $storedDate;
            $breakdown->updated_at = $storedDate;
            $breakdown->saveQuietly();

            DB::commit();

            return redirect()
                ->route('admins.reading-adjustments.index', [
                    'search' => $account->account_no,
                    'month' => $readingDate->format('Y-m'),
                ])
                ->with('success', 'Initial reading and zero paid bill created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors($e->getMessage());
        }
    }

    private function generateInitialReferenceNo(): string
    {
        $prefix = env('REF_PREFIX', 'NST-STA');
        $adminId = auth()->id() ?? 0;

        do {
            $referenceNo = "{$prefix}-ADJ-{$adminId}-" . now()->format('YmdHis') . random_int(100, 999);
            $exists = Bill::where('reference_no', $referenceNo)->exists()
                || Reading::where('reference_no', $referenceNo)->exists();
        } while ($exists);

        return $referenceNo;
    }
}
