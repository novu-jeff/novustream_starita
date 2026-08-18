<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceStaritaHost
{
    public const PORTAL_HOST = 'portal.staritawaterdistrictpamp.gov.ph';

    public const ADMIN_HOST = 'admin.staritawaterdistrictpamp.gov.ph';

    /**
     * Route portal vs admin hosts: concessionaire traffic on portal, staff/API on admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($host === self::PORTAL_HOST) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect()->away('https://'.self::ADMIN_HOST.$request->getRequestUri());
            }

            if ($request->is('api') || $request->is('api/*')) {
                return redirect()->away('https://'.self::ADMIN_HOST.$request->getRequestUri(), 301);
            }
        }

        if ($host === self::ADMIN_HOST) {
            if ($request->is('concessionaire') || $request->is('concessionaire/*') || $request->is('register')) {
                return redirect()->away('https://'.self::PORTAL_HOST.$request->getRequestUri());
            }

            if ($request->is('payments') || $request->is('payments/*')) {
                return redirect()->away('https://'.self::PORTAL_HOST.$request->getRequestUri());
            }
        }

        return $next($request);
    }

    public static function isPortalHost(?string $host = null): bool
    {
        $host = strtolower($host ?? request()->getHost());

        return $host === self::PORTAL_HOST;
    }

    public static function isAdminHost(?string $host = null): bool
    {
        $host = strtolower($host ?? request()->getHost());

        return $host === self::ADMIN_HOST;
    }
}
