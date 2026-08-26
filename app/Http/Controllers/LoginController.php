<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceStaritaHost;
use App\Models\Admin;
use App\Models\User;
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

        /*
         * Prevent multiple active sessions for concessionaires/users.
         */
        if (
            $user instanceof User &&
            in_array($user->user_type, ['concessionaire', 'user'])
        ) {
            if (
                $user->current_session_id &&
                $user->current_session_id !== session()->getId()
            ) {
                session()->getHandler()->destroy(
                    $user->current_session_id
                );
            }

            $user->current_session_id = session()->getId();
            $user->save();
        }

        Auth::guard($guard)->login(
            $user,
            $request->has('remember')
        );

        /*
         * Redirect based on account type.
         */
        $targetHost = $user instanceof Admin
            ? EnforceStaritaHost::ADMIN_HOST
            : EnforceStaritaHost::PORTAL_HOST;

        return redirect()->to(
            $this->absoluteUrl(
                $targetHost,
                $this->pathForUser($user)
            )
        );
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

        /*
         * Local development:
         * Never send localhost to a production domain.
         */
        if (EnforceStaritaHost::isLocalHost()) {
            return url($path);
        }

        /*
         * Production:
         * Use the appropriate HTTPS domain.
         */
        return 'https://' . $host . $path;
    }
}
