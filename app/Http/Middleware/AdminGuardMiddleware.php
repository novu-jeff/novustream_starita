<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminGuardMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {

        if (!Auth::guard('admins')->check()) {
            return redirect()->route('auth.login')->with('error', 'Access denied. Admins only.');
        }

        return $next($request);
    }
}
