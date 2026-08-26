<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceStaritaHost
{
    public const PORTAL_HOST = 'portal.staritawaterdistrictpamp.gov.ph';

    public const ADMIN_HOST = 'admin.staritawaterdistrictpamp.gov.ph';

    public const LOCAL_HOSTS = [
        'localhost',
        '127.0.0.1',
        '::1',
    ];

    /**
     * Route portal vs admin hosts.
     *
     * Production:
     * - Portal traffic uses the portal host.
     * - Admin/API traffic uses the admin host.
     *
     * Local:
     * - localhost/127.0.0.1/::1 are treated as the admin application.
     * - No redirects to production domains occur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        /*
         * Local development.
         *
         * Never redirect local traffic to production.
         */
        if (self::isLocalHost($host)) {
            return $next($request);
        }

        /*
         * Production portal host.
         */
        if ($host === self::PORTAL_HOST) {

            // Admin routes belong on the admin host.
            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect()->away(
                    'https://' . self::ADMIN_HOST . $request->getRequestUri()
                );
            }

            // API routes belong on the admin host.
            if ($request->is('api') || $request->is('api/*')) {
                return redirect()->away(
                    'https://' . self::ADMIN_HOST . $request->getRequestUri(),
                    301
                );
            }
        }

        /*
         * Production admin host.
         */
        if ($host === self::ADMIN_HOST) {

            // Concessionaire routes belong on the portal host.
            if (
                $request->is('concessionaire') ||
                $request->is('concessionaire/*') ||
                $request->is('register')
            ) {
                return redirect()->away(
                    'https://' . self::PORTAL_HOST . $request->getRequestUri()
                );
            }

            // Payment routes belong on the portal host.
            if (
                $request->is('payments') ||
                $request->is('payments/*')
            ) {
                return redirect()->away(
                    'https://' . self::PORTAL_HOST . $request->getRequestUri()
                );
            }
        }

        return $next($request);
    }

    /**
     * Determine whether the current request is a portal request.
     */
    public static function isPortalHost(?string $host = null): bool
    {
        $host = strtolower($host ?? request()->getHost());

        return $host === self::PORTAL_HOST;
    }

    /**
     * Determine whether the current request is an admin request.
     *
     * Localhost is considered admin during local development.
     */
    public static function isAdminHost(?string $host = null): bool
    {
        $host = strtolower($host ?? request()->getHost());

        return $host === self::ADMIN_HOST
            || self::isLocalHost($host);
    }

    /**
     * Determine whether the current request is local.
     */
    public static function isLocalHost(?string $host = null): bool
    {
        $host = strtolower($host ?? request()->getHost());

        return in_array($host, self::LOCAL_HOSTS, true);
    }
}
