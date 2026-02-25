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
        $validated = $request->validate([
            'account_no' => 'required|string|max:255',
            'penalty_exemption_type_id' => 'required|exists:penalty_exemption_type,id',
            'effective_date' => 'required|date',
            'expired_date' => 'required|date|after_or_equal:effective_date',
        ], [
            'account_no.required' => 'Account number is required.',
            'penalty_exemption_type_id.required' => 'Exemption type is required.',
            'effective_date.required' => 'Effective date is required.',
            'expired_date.required' => 'Expired date is required.',
            'expired_date.after_or_equal' => 'Expired date must be after or equal to effective date.',
        ]);

        PenaltyExemption::create($validated);

        return redirect()->route('penalty-exemption.index')
            ->with('success', 'Penalty exemption added successfully.');
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'account_no' => 'required|string|max:255',
                'penalty_exemption_type_id' => 'required|exists:penalty_exemption_type,id',
                'effective_date' => 'required|date',
                'expired_date' => 'required|date|after_or_equal:effective_date',
            ], [
                'account_no.required' => 'The account number field is required.',
                'penalty_exemption_type_id.required' => 'The exemption type field is required.',
                'effective_date.required' => 'The effective date field is required.',
                'expired_date.required' => 'The expired date field is required.',
                'expired_date.after_or_equal' => 'Expired date must be after or equal to effective date.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {

            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('open_modal', $id);
        }

        $exemption = PenaltyExemption::findOrFail($id);
        $exemption->update($validated);

        return redirect()
            ->route('penalty-exemption.index')
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
