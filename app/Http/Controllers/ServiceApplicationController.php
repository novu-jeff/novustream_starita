<?php

namespace App\Http\Controllers;

use App\Models\ServiceApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceApplicationController extends Controller
{
    /**
     * Display the application form.
     */
    public function create()
    {
        $applicationDefaults = $this->applicationDefaults();

        // Prevent duplicate pending applications (optional)
        $existing = ServiceApplication::where('user_id', Auth::id())
            ->whereIn('status', ['Pending', 'For Inspection'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('application.print', $existing)
                ->with('info', 'You already have a pending application.');
        }

        return view('application.create', compact('applicationDefaults'));
    }

    /**
     * Store a newly created application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cellphone' => 'required|string|max:20',
            'applicant_name' => 'required|string|max:255',
            'service_address' => 'required|string',

            'application_type' => 'required|string',
            'application_type_other' => 'nullable|string|max:255',

            'connection_size' => 'nullable|string|max:100',
            'installation_location' => 'required|string',

            'property_owner' => 'required|string|max:255',

            'promissory_note' => 'nullable|boolean',
            'promissory_amount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $application = ServiceApplication::create([
                'user_id' => Auth::id(),

                'application_no' => null,

                'cellphone' => $validated['cellphone'],
                'applicant_name' => strtoupper($validated['applicant_name']),
                'service_address' => $validated['service_address'],

                'application_type' => $validated['application_type'],
                'application_type_other' => $validated['application_type_other'] ?? null,

                'connection_size' => $validated['connection_size'] ?? null,
                'installation_location' => $validated['installation_location'],

                'property_owner' => $validated['property_owner'],

                'promissory_note' => $request->boolean('promissory_note'),
                'promissory_amount' => $validated['promissory_amount'] ?? null,

                'status' => 'Pending',
            ]);

            // Generate application number
            $application->update([
                'application_no' => 'SRWD-' .
                    now()->format('Y') .
                    '-' .
                    str_pad($application->id, 6, '0', STR_PAD_LEFT)
            ]);

            DB::commit();

            return redirect()
                ->route('application.print', $application)
                ->with('success', 'Application submitted successfully.');

        } catch (\Exception $e) {

            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors([
                    'error' => $e->getMessage()
                ]);
        }
    }

    /**
     * Display the application.
     */
    public function show(ServiceApplication $application)
    {
        // Prevent users from viewing other users' applications
        abort_if($application->user_id != Auth::id(), 403);

        $printData = $this->printData($application);

        return view('application.print', compact('application', 'printData'));
    }

    public function print(ServiceApplication $application)
    {
        // Prevent users from printing other users' applications
        abort_if($application->user_id != Auth::id(), 403);

        $printData = $this->printData($application);

        return view('application.print', compact('application', 'printData'));
    }

    private function applicationDefaults(): array
    {
        $user = Auth::user()?->load('accounts');
        $account = $user?->accounts?->first();

        return [
            'sc_no' => $account->sc_no ?? '',
            'meter_no' => $account->meter_serial_no ?? '',
            'account_no' => $account->account_no ?? '',
            'cellphone' => $user->contact_no ?? '',
            'applicant_name' => $user->registrants ?? $user->name ?? '',
            'service_address' => $account->address ?? '',
            'property_owner' => $user->name ?? '',
            'signature_name' => $user->registrants ?? $user->name ?? '',
            'application_date' => now()->format('Y-m-d'),
        ];
    }

    private function printData(ServiceApplication $application): array
    {
        $application->loadMissing('user.accounts');
        $account = $application->user?->accounts?->first();

        return [
            'sc_no' => $account->sc_no ?? '',
            'meter_no' => $account->meter_serial_no ?? '',
            'account_no' => $account->account_no ?? '',
            'cellphone' => $application->cellphone,
            'applicant_name' => $application->applicant_name,
            'service_address' => $application->service_address,
            'application_type' => $application->application_type,
            'connection_size' => $application->connection_size,
            'installation_location' => $application->installation_location,
            'signature_name' => $application->applicant_name,
            'application_date' => optional($application->created_at)->format('Y-m-d'),
            'property_owner' => $application->property_owner,
            'promissory_amount' => $application->promissory_amount,
        ];
    }
}
