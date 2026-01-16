<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CheckDefaultPassword
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        // Check if user is logged in and password is default
        if ($user && Hash::check('password', $user->password)) {
            // Set a session flag to show modal in Blade
            session()->flash('using_default_password', true);
        }

        return $next($request);
    }
}
