<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $host = strtolower($request->getHost());

        if ($host === EnforceStaritaHost::ADMIN_HOST) {
            return 'https://'.EnforceStaritaHost::ADMIN_HOST.'/login';
        }

        if ($host === EnforceStaritaHost::PORTAL_HOST) {
            return 'https://'.EnforceStaritaHost::PORTAL_HOST.'/login';
        }

        return route('auth.index');
    }
}
