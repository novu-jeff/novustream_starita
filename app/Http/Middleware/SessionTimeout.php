<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionTimeout
{
    protected $timeout = 1800;

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity_time');
            $now = time();

            if ($lastActivity && ($now - $lastActivity) > $this->timeout) {
                $user = Auth::user();

                if ($user instanceof \App\Models\User && in_array($user->user_type, ['concessionaire', 'user'])) {
                    $user->current_session_id = null;
                    $user->save();
                }

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('auth.login')->withErrors([
                    'email' => 'You have been logged out due to inactivity.'
                ]);
            }

            session(['last_activity_time' => $now]);
        }

        return $next($request);
    }
}
