<?php

namespace App\Http\Controllers;

use App\Models\ReadingDate;
use App\Models\Zone;
use Illuminate\Http\Request;

class ReadingDateController extends Controller
{
    public function index()
    {
        $zones = Zone::all();

        $readingDates = ReadingDate::with('zone')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('reading-dates.index', compact('zones', 'readingDates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id|unique:reading_dates,zone_id',
            'bill_period_from' => 'required|date',
            'bill_period_to' => 'required|date',
            'due_date' => 'required|date',
        ], [
            'zone_id.unique' => 'This zone already has a reading date configured.',
        ]);

        ReadingDate::create([
            'zone_id'          => $validated['zone_id'],
            'bill_period_from' => $validated['bill_period_from'],
            'bill_period_to'   => $validated['bill_period_to'],
            'due_date'         => $validated['due_date'],
            'is_active'        => true,
        ]);

        return redirect()
            ->route('reading-dates.index')
            ->with('success', 'Reading date created successfully.');
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

        $readingDate->update($validated);

        return back()->with('success', 'Reading date updated successfully.');
    }

    public function destroy($id)
    {
        $readingDate = ReadingDate::findOrFail($id);
        $readingDate->delete();

        return back()->with('success', 'Reading date deleted successfully.');
    }
}
