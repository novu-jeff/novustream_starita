<?php

namespace App\Http\Controllers;

use App\Models\ServiceApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Storage;

class ServiceApplicationController extends Controller
{
    /**
     * Display the application form.
     */
    public function create()
    {
        $applicationDefaults = $this->applicationDefaults();
        $application = $this->currentApplication();

        if ($application) {
            $applicationDefaults = array_merge($applicationDefaults, [
                'cellphone' => $application->cellphone,
                'applicant_name' => $application->applicant_name,
                'service_address' => $application->service_address,
                'application_type' => $application->application_type,
                'connection_size' => $application->connection_size,
                'installation_location' => $application->installation_location,
                'property_owner' => $application->property_owner,
                'promissory_amount' => $application->promissory_amount,
            ]);
        }

        return view('application.create', compact('applicationDefaults', 'application'));
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

            $application = $this->currentApplication();

            $applicationPayload = [
                'user_id' => Auth::id(),
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
            ];

            if ($application) {
                $application->update($applicationPayload);
            } else {
                $application = ServiceApplication::create($applicationPayload + [
                    'application_no' => null,
                ]);

                $application->update([
                    'application_no' => 'SRWD-' .
                        now()->format('Y') .
                        '-' .
                        str_pad($application->id, 6, '0', STR_PAD_LEFT)
                ]);
            }

            ApplicationDocument::updateOrCreate(
                ['service_application_id' => $application->id],
                [
                    'valid_id' => $this->documentPath($request, 'id_file', 'applications/id')
                        ?? $application->documents?->valid_id,

                    'cedula' => $this->documentPath($request, 'cedula_file', 'applications/cedula')
                        ?? $application->documents?->cedula,

                    'proof_of_billing' => $this->documentPath($request, 'billing_file', 'applications/billing')
                        ?? $application->documents?->proof_of_billing,

                    'authorization_letter' => $this->documentPath($request, 'authorization_file', 'applications/authorization')
                        ?? $application->documents?->authorization_letter,
                ]
            );

            DB::commit();

            return redirect()
                ->route('account-overview.index')
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
        $this->authorizeApplicationView($application);

        $printData = $this->printData($application);
        $autoPrint = false;

        return view('application.print', compact('application', 'printData', 'autoPrint'));
    }

    public function print(ServiceApplication $application)
    {
        $this->authorizeApplicationView($application);

        $printData = $this->printData($application);
        $autoPrint = true;

        return view('application.print', compact('application', 'printData', 'autoPrint'));
    }

    public function contract(ServiceApplication $application)
    {
        $this->authorizeApplicationView($application);

        $printData = $this->contractData($application);
        $autoPrint = false;

        return view('application.contract', compact('application', 'printData', 'autoPrint'));
    }

    public function printContract(ServiceApplication $application)
    {
        $this->authorizeApplicationView($application);

        $printData = $this->contractData($application);
        $autoPrint = true;

        return view('application.contract', compact('application', 'printData', 'autoPrint'));
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
            'installation_location' => $account->address ?? '',
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

    private function contractData(ServiceApplication $application): array
    {
        return [
            'applicant_name' => $application->applicant_name,
            'signature_name' => $application->applicant_name,
            'service_address' => $application->service_address,
        ];
    }

    private function authorizeApplicationView(ServiceApplication $application): void
    {
        if (Auth::guard('admins')->check()) {
            return;
        }

        abort_if($application->user_id != Auth::id(), 403);
    }

    private function currentApplication(): ?ServiceApplication
    {
        return ServiceApplication::with('documents')
            ->where('user_id', Auth::id())
            ->whereIn('status', ['Pending', 'For Inspection'])
            ->latest()
            ->first();
    }

    private function documentPath(Request $request, string $input, string $directory): ?string
    {
        return $request->file($input)
            ? $request->file($input)->store($directory, 'public')
            : null;
    }

    public function replaceDocument(Request $request, ServiceApplication $serviceApplication)
    {
        $request->validate([
            'document_type' => [
                'required',
                'in:valid_id,cedula,proof_of_billing,authorization_letter'
            ],
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $documentType = $request->document_type;

        $documents = $serviceApplication->documents;

        if (!$documents) {
            return back()->with('error', 'Document record not found.');
        }

        if (!empty($documents->$documentType)) {
            Storage::disk('public')->delete($documents->$documentType);
        }

        $path = $request->file('document')->store(
            'service-applications/documents',
            'public'
        );

        $documents->$documentType = $path;
        $documents->save();

        return back()->with('success', 'Document replaced successfully.');
    }
}
