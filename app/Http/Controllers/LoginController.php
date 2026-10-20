<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceStaritaHost;
use App\Models\Admin;
use App\Models\User;
use App\Services\AccountMailer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        if (Auth::guard('admins')->check()) {
            return redirect()->to(
                $this->absoluteUrl(
                    EnforceStaritaHost::ADMIN_HOST,
                    '/admin/dashboard'
                )
            );
        }

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            return redirect()->to(
                $this->absoluteUrl(
                    EnforceStaritaHost::PORTAL_HOST,
                    $this->pathForUser($user)
                )
            );
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        $isAdminHost = EnforceStaritaHost::isAdminHost();
        $isPortalHost = EnforceStaritaHost::isPortalHost();
        $isLocalHost = EnforceStaritaHost::isLocalHost();

        $user = null;
        $guard = 'web';

        /*
         * Production admin host authenticates against the Admin model.
         * Local development supports both account types and tries users first.
         */
        if ($isAdminHost && !$isLocalHost) {
            $user = Admin::where('email', $credentials['email'])->first();
            $guard = 'admins';
        }

        elseif ($isLocalHost) {
            $user = User::where('email', $credentials['email'])
                ->where('isActive', 1)
                ->first();

            $guard = 'web';

            if (! $user) {
                $user = Admin::where('email', $credentials['email'])->first();
                $guard = 'admins';
            }
        }

        /*
         * Production portal host:
         * authenticate against the concessionaire/user model.
         */
        elseif ($isPortalHost) {
            $user = User::where('email', $credentials['email'])
                ->where('isActive', 1)
                ->first();

            $guard = 'web';
        }

        /*
         * Legacy / unknown host:
         * previous dual lookup behavior.
         */
        else {
            $user = User::where('email', $credentials['email'])
                ->where('isActive', 1)
                ->first();

            $guard = 'web';

            if (! $user) {
                $user = Admin::where('email', $credentials['email'])->first();
                $guard = 'admins';
            }
        }

        if (! $user) {
            return back()->withErrors([
                'email' => ($isAdminHost && !$isLocalHost)
                    ? 'Invalid staff credentials.'
                    : ($isPortalHost
                        ? 'Invalid concessionaire credentials or account inactive.'
                        : 'Invalid credentials or account inactive.'),
            ]);
        }

        /*
         * Portal must not accept Admin accounts.
         */
        if ($isPortalHost && $user instanceof Admin) {
            return back()->withErrors([
                'email' => 'Staff accounts must sign in at ' . EnforceStaritaHost::ADMIN_HOST,
            ]);
        }

        /*
         * Production admin host must not accept concessionaire accounts.
         * Local development allows both account types.
         */
        if (($isAdminHost && !$isLocalHost) && $user instanceof User) {
            return back()->withErrors([
                'email' => 'Concessionaire accounts must sign in at ' . EnforceStaritaHost::PORTAL_HOST,
            ]);
        }

        if (empty($user->password)) {
            return back()->withErrors([
                'email' => 'No password found for this account.',
            ]);
        }

        $hashInfo = Hash::info($user->password);

        if ($hashInfo['algoName'] !== 'bcrypt') {
            return back()->withErrors([
                'email' => 'No password found for this account.',
            ]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ]);
        }

        // Require a fresh email code for every web account before establishing its guard session.
        if ($user instanceof User || $user instanceof Admin) {
            if (!filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors([
                    'email' => 'This account needs a valid email address before it can sign in.',
                ]);
            }

            if ($this->loginOtpIsLocked($user)) {
                return back()->withErrors([
                    'email' => 'Too many incorrect verification codes. Try signing in again after the 5-minute lockout.',
                ]);
            }

            $verifiedAt = $user->login_otp_verified_at;
            // Daily verification expires at midnight in the application's timezone.
            if ($verifiedAt && Carbon::parse($verifiedAt)->isSameDay(now())) {
                return $this->completeLogin($request, $user, $guard, $request->has('remember'));
            }

            // Reuse an outstanding code so repeated password submissions cannot
            // resend email or extend its expiry window.
            if (!empty($user->login_otp) && !empty($user->login_otp_expires_at)) {
                $samePendingLogin = (int) $request->session()->get('login_otp_user_id') === (int) $user->id
                    && $request->session()->get('login_otp_guard') === $guard;

                $request->session()->put([
                    'login_otp_user_id' => $user->id,
                    'login_otp_guard' => $guard,
                ]);
                if (!$samePendingLogin) {
                    $request->session()->put('login_otp_remember', $request->has('remember'));
                }

                return redirect()->route('login.verify');
            }

            if ((int) $user->login_otp_failed_attempts >= 5) {
                $user->login_otp_failed_attempts = 0;
            }

            $code = (string) random_int(100000, 999999);
            $user->forceFill([
                'login_otp' => Hash::make($code),
                'login_otp_expires_at' => now()->addMinutes(5),
            ])->save();

            $request->session()->put([
                'login_otp_user_id' => $user->id,
                'login_otp_guard' => $guard,
                'login_otp_remember' => $request->has('remember'),
            ]);

            app(AccountMailer::class)->notifyLoginOtp($user->email, $user->name, $code);

            return redirect()->route('login.verify');
        }

        return $this->completeLogin($request, $user, $guard, $request->has('remember'));
    }

    public function showLoginVerification(Request $request)
    {
        $user = $this->pendingLoginAccount($request);

        if (!$user) {
            return redirect()->route('auth.index');
        }

        $expiresAt = $user->login_otp_expires_at
            ? Carbon::parse($user->login_otp_expires_at)
            : null;
        $resendAvailableAt = $expiresAt?->copy()->subMinutes(4);
        $isLocked = $this->loginOtpIsLocked($user);

        return view('auth.verify-otp', [
            'maskedEmail' => $this->maskEmail($user->email),
            'verifyRoute' => route('login.verify.store'),
            'resendRoute' => route('login.verify.resend'),
            'pageTitle' => 'Verify your login',
            'submitLabel' => 'Verify and sign in',
            'expiryMinutes' => 5,
            'expiresAtTimestampMs' => $expiresAt
                ? $expiresAt->getTimestamp() * 1000
                : now()->getTimestamp() * 1000,
            'resendAvailableAtTimestampMs' => $resendAvailableAt?->getTimestamp() * 1000,
            'resendCooldownSeconds' => $resendAvailableAt
                ? max(0, $resendAvailableAt->getTimestamp() - now()->getTimestamp())
                : 0,
            'lockedUntil' => $isLocked
                ? Carbon::parse($user->login_otp_locked_until)->format('g:i A')
                : null,
            'lockoutRemainingSeconds' => $isLocked
                ? max(1, now()->diffInSeconds(Carbon::parse($user->login_otp_locked_until)))
                : 0,
            'attemptsRemaining' => max(0, 5 - (int) $user->login_otp_failed_attempts),
            'codeExpired' => !$expiresAt || $expiresAt->isPast(),
        ]);
    }

    public function verifyLoginOtp(Request $request)
    {
        $request->merge([
            'otp' => preg_replace('/\\D+/', '', (string) $request->input('otp')),
        ]);
        $payload = $request->validate(['otp' => ['required', 'digits:6']]);
        $user = $this->pendingLoginAccount($request);

        if (!$user) {
            return redirect()->route('auth.index')->with('error', 'Your verification session expired. Please sign in again.');
        }

        if ($this->loginOtpIsLocked($user)) {
            return back()->withErrors(['otp' => 'Verification is locked until '.Carbon::parse($user->login_otp_locked_until)->format('g:i A').'.']);
        }

        if (empty($user->login_otp) || empty($user->login_otp_expires_at) || now()->greaterThan($user->login_otp_expires_at)) {
            return back()->withErrors(['otp' => 'That code has expired. Send a new code and try again.']);
        }

        if (!Hash::check($payload['otp'], $user->login_otp)) {
            $user->login_otp_failed_attempts = (int) $user->login_otp_failed_attempts + 1;
            if ($user->login_otp_failed_attempts >= 5) {
                $user->forceFill([
                    'login_otp' => null,
                    'login_otp_expires_at' => null,
                    'login_otp_locked_until' => now()->addMinutes(5),
                ]);
                $user->save();
                return back()->withErrors(['otp' => 'Five incorrect codes were entered. Verification is locked for 5 minutes.']);
            }
            $user->save();
            return back()->withErrors(['otp' => 'The verification code is incorrect.']);
        }

        $remember = (bool) $request->session()->get('login_otp_remember', false);
        $user->forceFill([
            'login_otp' => null,
            'login_otp_expires_at' => null,
            'login_otp_verified_at' => now(),
            'login_otp_failed_attempts' => 0,
            'login_otp_locked_until' => null,
        ])->save();
        $request->session()->forget(['login_otp_user_id', 'login_otp_guard', 'login_otp_remember']);

        $guard = $user instanceof Admin ? 'admins' : 'web';
        return $this->completeLogin($request, $user, $guard, $remember);
    }

    public function resendLoginOtp(Request $request)
    {
        $user = $this->pendingLoginAccount($request);
        if (!$user) {
            return redirect()->route('auth.index')->with('error', 'Your verification session expired. Please sign in again.');
        }

        if ($this->loginOtpIsLocked($user)) {
            return back()->withErrors(['otp' => 'Verification is locked until '.Carbon::parse($user->login_otp_locked_until)->format('g:i A').'.']);
        }

        if (!empty($user->login_otp_expires_at)) {
            $resendAvailableAt = Carbon::parse($user->login_otp_expires_at)->subMinutes(4);
            if (now()->lt($resendAvailableAt)) {
                return back()->withErrors(['otp' => 'You can resend the code when 4 minutes remain on the countdown.']);
            }
        }

        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'login_otp' => Hash::make($code),
            'login_otp_expires_at' => now()->addMinutes(5),
        ])->save();
        if ((int) $user->login_otp_failed_attempts >= 5) {
            $user->login_otp_failed_attempts = 0;
            $user->save();
        }
        app(AccountMailer::class)->notifyLoginOtp($user->email, $user->name, $code);

        return back()->with('status', 'A new verification code was sent to your email.');
    }

    private function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if (!str_contains($email, '@')) return 'your email';
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 1).str_repeat('*', max(strlen($name) - 1, 1)).'@'.$domain;
    }

    private function pendingLoginAccount(Request $request)
    {
        $id = $request->session()->get('login_otp_user_id');
        return $request->session()->get('login_otp_guard') === 'admins'
            ? Admin::find($id)
            : User::find($id);
    }

    private function loginOtpIsLocked($account): bool
    {
        return !empty($account->login_otp_locked_until)
            && Carbon::parse($account->login_otp_locked_until)->isFuture();
    }

    private function completeLogin(Request $request, $account, string $guard, bool $remember)
    {
        $request->session()->regenerate();

        if ($account instanceof User && in_array($account->user_type, ['concessionaire', 'user'])) {
            if ($account->current_session_id && $account->current_session_id !== $request->session()->getId()) {
                $request->session()->getHandler()->destroy($account->current_session_id);
            }

            $account->current_session_id = $request->session()->getId();
            $account->save();
        }

        Auth::guard($guard)->login($account, $remember);

        $targetHost = $account instanceof Admin
            ? EnforceStaritaHost::ADMIN_HOST
            : EnforceStaritaHost::PORTAL_HOST;

        return redirect()->to($this->absoluteUrl($targetHost, $this->pathForUser($account)));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        $guard = ($user instanceof Admin)
            ? 'admins'
            : 'web';

        $loginHost = ($user instanceof Admin)
            ? EnforceStaritaHost::ADMIN_HOST
            : EnforceStaritaHost::PORTAL_HOST;

        if (
            $user instanceof User &&
            in_array($user->user_type, ['concessionaire', 'user'])
        ) {
            $user->current_session_id = null;
            $user->save();
        }

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(
            $this->absoluteUrl(
                $loginHost,
                '/login'
            )
        );
    }

    public function redirectTo($user): string
    {
        return $this->pathForUser($user);
    }

    protected function pathForUser($user): string
    {
        return match ($user->user_type) {
            'admin',
            'cashier',
            'superadmin' => '/admin/dashboard',

            'technician' => '/admin/reading',

            'concessionaire',
            'user',
            null => '/concessionaire/my/overview',

            default => '/login',
        };
    }

    /**
     * Generate the correct URL for the current environment.
     *
     * Local:
     *     http://localhost/path
     *
     * Production:
     *     https://admin.staritawaterdistrictpamp.gov.ph/path
     */
    protected function absoluteUrl(string $host, string $path): string
    {
        $path = '/' . ltrim($path, '/');

        $currentHost = strtolower(request()->getHost());
        $isProductionHost = in_array($currentHost, [
            EnforceStaritaHost::ADMIN_HOST,
            EnforceStaritaHost::PORTAL_HOST,
        ], true);

        if (! $isProductionHost) {
            return url($path);
        }

        return 'https://'.$host.$path;
    }
}
