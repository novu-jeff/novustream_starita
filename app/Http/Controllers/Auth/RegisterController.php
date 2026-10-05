<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\ServiceApplication;
use App\Models\UserAccounts;
use App\Models\User;
use App\Services\AccountMailer;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users'],
            'contact_no' => ['required', 'string', 'max:20'],
            'account_no' => ['required_if:registration_type,existing_account', 'nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'soa_file' => ['required_if:registration_type,existing_account', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'id_file' => ['required_if:registration_type,existing_account', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'picture_1x1' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'cedula_file' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'billing_file' => ['required_if:registration_type,new_connection', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'authorization_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'data_privacy_consent' => ['accepted'],
        ]);
    }

    public function register(Request $request)
    {
        $recaptchaResponse = $request->input('g-recaptcha-response');

        if (!$recaptchaResponse) {
            return response()->json([
                'message' => 'Please complete the reCAPTCHA verification.',
            ], 422);
        }

        $verification = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $recaptchaResponse,
                'remoteip' => $request->ip(),
            ]
        );

        if (!$verification->successful() || !$verification->json('success')) {
            return response()->json([
                'message' => 'reCAPTCHA verification failed. Please try again.',
            ], 422);
        }

        $this->validator($request->all())->validate();

        $request->merge([
            'name' => mb_strtoupper(trim((string) $request->name)),
        ]);

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

            $account = $matchingAccounts->first(function (UserAccounts $account) use ($request) {
                return $this->namesMatch((string) ($account->user?->name ?? ''), $request->name);
            });

            if (!$account) {
                throw ValidationException::withMessages([
                    'name' => 'The name does not match the account no.',
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

        $user = $user->fresh();
        $this->sendRegistrationOtp($user);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Enter the verification code we sent to your email.',
                'redirect' => route('register.verify'),
            ]);
        }

        return redirect()
            ->route('register.verify')
            ->with('status', 'Enter the verification code we sent to your email.');
    }

    public function showVerification()
    {
        $user = $this->pendingVerificationUser();

        if (!$user) {
            return redirect()
                ->route('register')
                ->with('error', 'Submit your registration first so we can email you a verification code.');
        }

        return view('auth.verify-otp', [
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->merge([
            'otp' => preg_replace('/\D+/', '', (string) $request->input('otp')),
        ]);

        $payload = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->pendingVerificationUser();

        if (!$user) {
            return redirect()
                ->route('register')
                ->with('error', 'Your verification session expired. Please register again.');
        }

        if (empty($user->email_otp) || empty($user->email_otp_expires_at) || now()->greaterThan($user->email_otp_expires_at)) {
            return back()->withErrors([
                'otp' => 'That code has expired. Send a new code and try again.',
            ]);
        }

        $attempts = (int) $request->session()->get('registration_otp_attempts', 0);

        if ($attempts >= 5) {
            return back()->withErrors([
                'otp' => 'Too many incorrect codes. Send a new code and try again.',
            ]);
        }

        if (!Hash::check($payload['otp'], $user->email_otp)) {
            $request->session()->put('registration_otp_attempts', $attempts + 1);

            return back()->withErrors([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_otp' => null,
            'email_otp_expires_at' => null,
        ])->save();

        $request->session()->forget([
            'registration_otp_user_id',
            'registration_otp_attempts',
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->current_session_id = $request->session()->getId();
        $user->save();

        return redirect()
            ->route('account-overview.index')
            ->with('status', 'Your email is verified. Registration was submitted for review.');
    }

    public function resendOtp(Request $request)
    {
        $user = $this->pendingVerificationUser();

        if (!$user) {
            return redirect()
                ->route('register')
                ->with('error', 'Your verification session expired. Please register again.');
        }

        $this->sendRegistrationOtp($user);

        return back()->with('status', 'A new verification code was sent to your email.');
    }

    private function sendRegistrationOtp(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_otp' => Hash::make($code),
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        session([
            'registration_otp_user_id' => $user->id,
            'registration_otp_attempts' => 0,
        ]);

        app(AccountMailer::class)->notifyEmailOtp($user->email, $user->name, $code);
    }

    private function pendingVerificationUser(): ?User
    {
        $userId = session('registration_otp_user_id');

        if (!$userId) {
            return null;
        }

        return User::find($userId);
    }

    private function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if (!str_contains($email, '@')) {
            return 'your email';
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 1);

        return $visible.str_repeat('*', max(strlen($name) - 1, 1)).'@'.$domain;
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

        $user->forceFill([
            'registrants' => $data['name'],
            'contact_no' => $data['contact_no'],
            'email' => $data['email'],
            'user_type' => 'concessionaire',
            'password' => Hash::make($data['password']),
            'email_verified_at' => null,
        ])->save();

        return $user->refresh();
    }

    private function sequenceNoFromAccountNo(string $accountNo): int
    {
        $parts = preg_split('/\D+/', $accountNo, -1, PREG_SPLIT_NO_EMPTY);
        $sequence = end($parts) ?: preg_replace('/\D+/', '', $accountNo);

        return (int) $sequence;
    }

    private function namesMatch(string $firstName, string $secondName): bool
    {
        $normalize = static function (string $name): string {
            $normalized = preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim($name)));

            return $normalized ?? '';
        };

        $first = $normalize($firstName);
        $second = $normalize($secondName);

        return $first !== '' && $first === $second;
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
