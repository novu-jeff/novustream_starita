<?php

namespace App\Http\Controllers;

use App\Models\PenaltyExemption;
use App\Models\PenaltyExemptionType;
use Illuminate\Http\Request;

class PenaltyExemptionController extends Controller
{
    public function index()
    {
        $exemptions = PenaltyExemption::with([
            'type',
            'account.user'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        $types = PenaltyExemptionType::all();

        return view('penalty-exemption.index', compact('exemptions', 'types'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_no' => 'required|string|max:255',
            'id_no' => 'nullable|string|max:255',
            'penalty_exemption_type_id' => 'nullable|exists:penalty_exemption_type,id',
            'effective_date' => 'nullable|string|max:255',
            'expired_date' => 'nullable|string|max:255',
        ]);

        PenaltyExemption::create($request->all());

        return redirect()->route('penalty-exemption.index')
            ->with('success', 'Penalty exemption added successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'account_no' => 'required|string|max:255',
            'penalty_exemption_type_id' => 'nullable|exists:penalty_exemption_type,id',
            'effective_date' => 'nullable|date',
            'expired_date' => 'nullable|date',
        ]);

        $exemption = PenaltyExemption::findOrFail($id);

        $exemption->update([
            'account_no' => $request->account_no,
            'penalty_exemption_type_id' => $request->penalty_exemption_type_id,
            'effective_date' => $request->effective_date,
            'expired_date' => $request->expired_date,
        ]);

        return redirect()->route('penalty-exemption.index')
            ->with('success', 'Penalty exemption updated successfully.');
    }

    public function destroy($id)
    {
        $exemption = PenaltyExemption::findOrFail($id);
        $exemption->delete();

        return redirect()->route('penalty-exemption.index')
            ->with('success', 'Pnealty exemption removed successfully.');
    }
}
