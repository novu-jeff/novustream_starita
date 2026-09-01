<?php

namespace App\Http\Controllers;

use App\Models\ReadingDate;
use App\Models\Zone;
use App\Services\BillingPeriodService;
use Illuminate\Http\Request;

class ReadingDateController extends Controller
{
    public function __construct(
        protected BillingPeriodService $billingPeriodService
    ) {
    }

    public function index()
    {
        $zones = Zone::all();

        $readingDates = ReadingDate::with('zone')
            ->orderByDesc('is_active')
            ->orderBy('zone_id')
            ->orderByDesc('bill_period_to')
            ->get();

        $hasSnapshot = $this->billingPeriodService->hasSnapshot();
        $activeBillingMonth = $this->billingPeriodService->yearMonth();

        return view('reading-dates.index', compact('zones', 'readingDates', 'hasSnapshot', 'activeBillingMonth'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_zone_id' => 'required|exists:zones,id',
            'to_zone_id'   => 'required|exists:zones,id',
            'bill_period_from' => 'required|date',
            'bill_period_to'   => 'required|date',
            'due_date'         => 'required|date',
        ]);

        $from = min($validated['from_zone_id'], $validated['to_zone_id']);
        $to   = max($validated['from_zone_id'], $validated['to_zone_id']);

        $zones = Zone::whereBetween('id', [$from, $to])->pluck('id');

        foreach ($zones as $zoneId) {
            ReadingDate::where('zone_id', $zoneId)->update(['is_active' => false]);

            ReadingDate::create([
                'zone_id' => $zoneId,
                'bill_period_from' => $validated['bill_period_from'],
                'bill_period_to'   => $validated['bill_period_to'],
                'due_date'         => $validated['due_date'],
                'is_active'        => true,
            ]);
        }

        return redirect()
            ->route('reading-dates.index')
            ->with('success', 'Reading date applied to selected zone range successfully.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'bill_period_from' => 'required|date',
            'bill_period_to' => 'required|date|after_or_equal:bill_period_from',
            'due_date' => 'required|date|after_or_equal:bill_period_to',
        ]);

        $readingDate = ReadingDate::findOrFail($id);

        ReadingDate::where('zone_id', $validated['zone_id'])
            ->where('id', '!=', $id)
            ->update(['is_active' => false]);

        $readingDate->update(array_merge($validated, ['is_active' => true]));

        return back()->with('success', 'Reading date updated successfully.');
    }

    public function destroy($id)
    {
        $readingDate = ReadingDate::findOrFail($id);
        $readingDate->delete();

        return back()->with('success', 'Reading date deleted successfully.');
    }

    public function destroyAll()
    {
        ReadingDate::truncate();

        return redirect()
            ->route('reading-dates.index')
            ->with('success', 'All reading date schedules have been deleted.');
    }

    /**
     * Staging: shift active reading_dates so bill_period_to lands in target yyyy-MM.
     */
    public function simulateMonth(Request $request)
    {
        if (!app()->environment('staging')) {
            abort(404);
        }

        $validated = $request->validate([
            'target_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $count = $this->billingPeriodService->simulateMonth($validated['target_month']);
        } catch (\Throwable $e) {
            return back()->withErrors(['target_month' => $e->getMessage()]);
        }

        return redirect()
            ->route('reading-dates.index')
            ->with('success', "Simulated billing month {$validated['target_month']} for {$count} zone(s). Demo app Sync will use these dates.");
    }

    /**
     * Staging: restore reading_dates from snapshot taken before simulateMonth.
     */
    public function restoreCalendarMonth(Request $request)
    {
        if (!app()->environment('staging')) {
            abort(404);
        }

        try {
            $count = $this->billingPeriodService->restoreFromSnapshot();
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => $e->getMessage()]);
        }

        return redirect()
            ->route('reading-dates.index')
            ->with('success', "Restored calendar billing dates for {$count} zone(s).");
    }
}
