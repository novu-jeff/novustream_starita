<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Reading;
use App\Models\Bill;
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

    return view('admins.reading-adjustments.index', compact('readings', 'month', 'allAdjustments'));
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
            ]);

            DB::commit();

            return redirect()->route('admins.reading-adjustments.index')
                ->with('success', 'Reading adjusted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors($e->getMessage());
        }
    }
}
