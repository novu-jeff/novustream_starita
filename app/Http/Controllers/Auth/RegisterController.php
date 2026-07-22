<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contact_no' => ['required', 'string', 'max:20'],
            'account_no' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'soa_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'data_privacy_consent' => ['accepted'],
        ]);
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = DB::transaction(function () use ($request) {
            $accountNo = trim($request->account_no);
            $matchingAccounts = UserAccounts::where('account_no', $accountNo)
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

            $user = $this->create($request->all());

            $account->update([
                'user_id' => $user->id,
                'zone' => $account->zone ?: substr($accountNo, 0, 3),
                'account_no' => $accountNo,
                'address' => $account->address ?: $request->address,
                'sequence_no' => $account->sequence_no ?: $this->sequenceNoFromAccountNo($accountNo),
                'application_soa_path' => $request->file('soa_file')->store('applications/soa', 'public'),
                'application_id_path' => $request->file('id_file')->store('applications/id', 'public'),
                'application_status' => 'pending',
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
