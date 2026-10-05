<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CheckDefaultPassword
{
    public function handle($request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            $user->refresh();
        }

        if ($user && Hash::check('password', $user->password)) {
            session()->flash('using_default_password', true);
        }

        return $next($request);
    }
}
