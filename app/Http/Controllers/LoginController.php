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
            return redirect()->away($this->absoluteUrl(EnforceStaritaHost::ADMIN_HOST, '/admin/dashboard'));
        }

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            return redirect()->away($this->absoluteUrl(
                EnforceStaritaHost::PORTAL_HOST,
                $this->pathForUser($user)
            ));
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

        $user = null;
        $guard = 'web';

        if ($isAdminHost) {
            $user = Admin::where('email', $credentials['email'])->first();
            $guard = 'admins';
        } elseif ($isPortalHost) {
            $user = User::where('email', $credentials['email'])
                ->where('isActive', 1)
                ->first();
            $guard = 'web';
        } else {
            // Legacy / unknown host: previous dual lookup
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
                'email' => $isAdminHost
                    ? 'Invalid staff credentials.'
                    : ($isPortalHost
                        ? 'Invalid concessionaire credentials or account inactive.'
                        : 'Invalid credentials or account inactive.'),
            ]);
        }

        if ($isPortalHost && $user instanceof Admin) {
            return back()->withErrors([
                'email' => 'Staff accounts must sign in at '.EnforceStaritaHost::ADMIN_HOST,
            ]);
        }

        if ($isAdminHost && $user instanceof User) {
            return back()->withErrors([
                'email' => 'Concessionaire accounts must sign in at '.EnforceStaritaHost::PORTAL_HOST,
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

        if ($user instanceof User && in_array($user->user_type, ['concessionaire', 'user'])) {
            if ($user->current_session_id && $user->current_session_id !== session()->getId()) {
                session()->getHandler()->destroy($user->current_session_id);
            }

            $user->current_session_id = session()->getId();
            $user->save();
        }

        Auth::guard($guard)->login($user, $request->has('remember'));

        $targetHost = $user instanceof Admin
            ? EnforceStaritaHost::ADMIN_HOST
            : EnforceStaritaHost::PORTAL_HOST;

        return redirect()->away($this->absoluteUrl($targetHost, $this->pathForUser($user)));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $guard = ($user instanceof Admin) ? 'admins' : 'web';
        $loginHost = ($user instanceof Admin)
            ? EnforceStaritaHost::ADMIN_HOST
            : EnforceStaritaHost::PORTAL_HOST;

        if ($user instanceof User && in_array($user->user_type, ['concessionaire', 'user'])) {
            $user->current_session_id = null;
            $user->save();
        }

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($this->absoluteUrl($loginHost, '/login'));
    }

    public function redirectTo($user): string
    {
        return $this->pathForUser($user);
    }

    protected function pathForUser($user): string
    {
        return match ($user->user_type) {
            'admin', 'cashier', 'superadmin' => '/admin/dashboard',
            'technician' => '/admin/reading',
            'concessionaire', 'user', null => '/concessionaire/my/overview',
            default => '/login',
        };
    }

    protected function absoluteUrl(string $host, string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return 'https://'.$host.$path;
    }
}
