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
        $type = PenaltyExemptionType::findOrFail($request->penalty_exemption_type_id);

        $rules = [
            'account_no' => 'required|string|max:255|exists:concessioner_accounts,account_no',
            'penalty_exemption_type_id' => 'required|exists:penalty_exemption_type,id',
        ];

        if ($type->penalty_exemption_name === 'Temporary') {
            $rules['effective_date'] = 'required|date';
            $rules['expired_date'] = 'required|date|after_or_equal:effective_date';
        } else {
            $rules['effective_date'] = 'nullable|date';
            $rules['expired_date'] = 'nullable|date';
        }

        $validated = $request->validate($rules, [
            'account_no.required' => 'Account number is required.',
            'account_no.exists' => 'Account does not exist.',
            'penalty_exemption_type_id.required' => 'Exemption type is required.',
            'effective_date.required' => 'Effective date is required for Temporary exemption.',
            'expired_date.required' => 'Expired date is required for Temporary exemption.',
            'expired_date.after_or_equal' => 'Expired date must be after or equal to effective date.',
        ]);

        $existing = PenaltyExemption::where('account_no', $validated['account_no'])->first();
        if ($existing) {
            return redirect()
                ->route('penalty-exemption.index')
                ->with('warning', 'This account already has a penalty exemption. Please edit instead.');
        }

        PenaltyExemption::create($validated);

        return redirect()
            ->route('penalty-exemption.index')
            ->with('success', 'Penalty exemption added successfully.');
    }

    public function update(Request $request, $id)
    {
        $exemption = PenaltyExemption::findOrFail($id);
        $type = PenaltyExemptionType::findOrFail($request->penalty_exemption_type_id);

        $rules = [
            'account_no' => 'required|string|max:255|exists:concessioner_accounts,account_no',
            'penalty_exemption_type_id' => 'required|exists:penalty_exemption_type,id',
        ];

        if ($type->penalty_exemption_name === 'Temporary') {
            $rules['effective_date'] = 'required|date';
            $rules['expired_date'] = 'required|date|after_or_equal:effective_date';
        } else {
            $rules['effective_date'] = 'nullable|date';
            $rules['expired_date'] = 'nullable|date';
        }

        try {
            $validated = $request->validate($rules, [
                'account_no.required' => 'The account number field is required.',
                'account_no.exists' => 'Account does not exist.',
                'penalty_exemption_type_id.required' => 'The exemption type field is required.',
                'effective_date.required' => 'Effective date is required for Temporary exemption.',
                'expired_date.required' => 'Expired date is required for Temporary exemption.',
                'expired_date.after_or_equal' => 'Expired date must be after or equal to effective date.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('open_modal', $id);
        }

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
