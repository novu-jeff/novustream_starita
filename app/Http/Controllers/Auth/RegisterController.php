<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\ServiceApplication;
use App\Models\UserAccounts;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'registration_type' => ['required', 'in:existing_account,new_connection'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contact_no' => ['required', 'string', 'max:20'],
            'account_no' => ['required_if:registration_type,existing_account', 'nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'soa_file' => ['required_if:registration_type,existing_account', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_file' => ['required_if:registration_type,existing_account', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'picture_1x1' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'cedula_file' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'billing_file' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'authorization_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'data_privacy_consent' => ['accepted'],
        ]);
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = DB::transaction(function () use ($request) {
            if ($request->registration_type === 'new_connection') {
                return $this->createNewConnectionApplication($request);
            }

            $accountNo = trim($request->account_no);
            $matchingAccounts = UserAccounts::with('user')
                ->where('account_no', $accountNo)
                ->lockForUpdate()
                ->get();

            if ($matchingAccounts->isEmpty()) {
                throw ValidationException::withMessages([
                    'account_no' => 'Account no. was not found in our records.',
                ]);
            }

            if ($matchingAccounts->contains(fn ($account) => $this->applicationStatus($account) === 'approved')) {
                throw ValidationException::withMessages([
                    'account_no' => 'Account no. is already registered and active.',
                ]);
            }

            if ($matchingAccounts->contains(fn ($account) => $this->hasRegistrationApplication($account))) {
                throw ValidationException::withMessages([
                    'account_no' => 'Account no. is already registered or has a pending application.',
                ]);
            }

            $account = $matchingAccounts
                ->first(fn ($account) => !$this->hasRegistrationApplication($account));

            $user = $this->updateExistingAccountRegistrant($request->all(), $account);

            $account->update([
                'zone' => $account->zone ?: substr($accountNo, 0, 3),
                'account_no' => $accountNo,
                'address' => $account->address ?: $request->address,
                'sequence_no' => $account->sequence_no ?: $this->sequenceNoFromAccountNo($accountNo),
                'application_soa_path' => $request->file('soa_file')->store('applications/soa', 'public'),
                'application_id_path' => $request->file('id_file')->store('applications/id', 'public'),
                'application_status' => 'pending',
                'application_type' => 'existing_account',
                'isApproved' => false,
                'approved_at' => null,
                'denied_at' => null,
                'approval_denial_reason' => null,
            ]);

            return $user;
        });

        Auth::guard()->login($user);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Registration submitted for review.',
                'redirect' => route('account-overview.index'),
            ]);
        }

        return redirect()
            ->route('account-overview.index')
            ->with('status', 'Registration submitted for review.');
    }

    private function createNewConnectionApplication(Request $request): User
    {
        $user = $this->create($request->all());

        $documents = [
            'valid_id' => $request->file('picture_1x1')
                ? $request->file('picture_1x1')->store('applications/id', 'public')
                : null,
            'cedula' => $request->file('cedula_file') ? $request->file('cedula_file')->store('applications/cedula', 'public') : null,
            'proof_of_billing' => $request->file('billing_file') ? $request->file('billing_file')->store('applications/billing', 'public') : null,
            'authorization_letter' => $request->file('authorization_file')
                ? $request->file('authorization_file')->store('applications/authorization', 'public')
                : null,
        ];

        UserAccounts::create([
            'user_id' => $user->id,
            'zone' => null,
            'account_no' => 'NEW-' . $user->id,
            'address' => $request->address,
            'property_type' => null,
            'rate_code' => 1,
            'status' => 'AB',
            'sc_no' => '',
            'date_connected' => now()->toDateString(),
            'sequence_no' => $user->id,
            'application_id_path' => $documents['valid_id'],
            'application_status' => 'pending',
            'application_type' => 'new_connection',
            'isApproved' => false,
            'approved_at' => null,
            'denied_at' => null,
            'approval_denial_reason' => null,
        ]);

        $application = ServiceApplication::create([
            'user_id' => $user->id,
            'application_no' => null,
            'cellphone' => $request->contact_no,
            'applicant_name' => strtoupper($request->name),
            'service_address' => $request->address,
            'application_type' => 'Water Service Connection',
            'connection_type' => 'on_line',
            'connection_size' => null,
            'installation_location' => $request->address,
            'property_owner' => strtoupper($request->name),
            'promissory_note' => false,
            'promissory_amount' => null,
            'application_fee_amount' => 4000,
            'application_fee_status' => 'unpaid',
            'status' => 'Pending',
        ]);

        $application->update([
            'application_no' => 'SRWD-' .
                now()->format('Y') .
                '-' .
                str_pad($application->id, 6, '0', STR_PAD_LEFT),
        ]);

        ApplicationDocument::create([
            'service_application_id' => $application->id,
            'valid_id' => $documents['valid_id'],
            'cedula' => $documents['cedula'],
            'proof_of_billing' => $documents['proof_of_billing'],
            'authorization_letter' => $documents['authorization_letter'],
        ]);

        return $user;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create([
            'name'      => $data['name'],
            'contact_no' => $data['contact_no'],
            'email' => $data['email'],
            'user_type' => 'concessionaire',
            'password' => Hash::make($data['password']),
        ]);
    }

    protected function updateExistingAccountRegistrant(array $data, UserAccounts $account): User
    {
        $user = $account->user;

        if (!$user) {
            return User::create([
                'name' => $data['name'],
                'registrants' => $data['name'],
                'contact_no' => $data['contact_no'],
                'email' => $data['email'],
                'user_type' => 'concessionaire',
                'password' => Hash::make($data['password']),
            ]);
        }

        $user->update([
            'registrants' => $data['name'],
            'contact_no' => $data['contact_no'],
            'email' => $data['email'],
            'user_type' => 'concessionaire',
            'password' => Hash::make($data['password']),
        ]);

        return $user->refresh();
    }

    private function sequenceNoFromAccountNo(string $accountNo): int
    {
        $parts = preg_split('/\D+/', $accountNo, -1, PREG_SPLIT_NO_EMPTY);
        $sequence = end($parts) ?: preg_replace('/\D+/', '', $accountNo);

        return (int) $sequence;
    }

    private function hasRegistrationApplication(UserAccounts $account): bool
    {
        return !empty($account->application_status)
            || !empty($account->application_soa_path)
            || !empty($account->application_id_path)
            || !empty($account->denied_at);
    }

    private function applicationStatus(UserAccounts $account): ?string
    {
        if (!empty($account->application_status)) {
            return $account->application_status;
        }

        if ((bool) ($account->isApproved ?? false)) {
            return 'approved';
        }

        if (!empty($account->denied_at)) {
            return 'denied';
        }

        return $this->hasRegistrationApplication($account) ? 'pending' : null;
    }
}
