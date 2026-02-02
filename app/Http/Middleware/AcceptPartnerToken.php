<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multi-branch offline app: authenticate via local Sanctum token OR partner app token.
 * Use this instead of auth:sanctum on offline routes so one login works for both apps.
 */
class AcceptPartnerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Try local Sanctum token first
        $user = Auth::guard('sanctum')->user();
        if ($user !== null) {
            return $next($request);
        }

        $token = $request->bearerToken();
        if (empty($token)) {
            return $this->unauthorized($request);
        }

        // 2) Try partner app (e.g. morong) and map to local Admin by email
        $partnerUrl = rtrim(config('app.offline_partner_app_url', ''), '/');
        if ($partnerUrl !== '') {
            try {
                $response = Http::timeout(10)
                    ->withToken($token)
                    ->get($partnerUrl . '/api/user');

                if ($response->successful()) {
                    $data = $response->json();
                    $email = $data['email'] ?? null;
                    if (!empty($email)) {
                        $localAdmin = Admin::where('email', $email)->first();
                        if ($localAdmin && in_array($localAdmin->user_type, ['technician', 'admin'], true)) {
                            $request->setUserResolver(fn () => $localAdmin);
                            Log::channel('single')->info('Novustream offline API: accepted partner token', [
                                'admin_id' => $localAdmin->id,
                                'email' => $email,
                                'partner' => $partnerUrl,
                            ]);
                            return $next($request);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Novustream offline API: partner token check failed', [
                    'error' => $e->getMessage(),
                    'partner' => $partnerUrl,
                ]);
            }
        }

        return $this->unauthorized($request);
    }

    private function unauthorized(Request $request): Response
    {
        return response()->json([
            'error' => 'Unauthenticated',
            'message' => 'Invalid or missing token. Log in via POST /api/login (this app or partner app) and send Authorization: Bearer <token>.',
        ], 401);
    }
}
