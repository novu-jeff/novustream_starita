<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Offline API auth: local Sanctum token (or direct token lookup for Admin).
 * Optional: partner app URL for multi-branch (one login for both). For dedicated
 * apps (novustream_offline_starita / novustream_offline_morong) leave OFFLINE_PARTNER_APP_URL empty.
 */
class AcceptPartnerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // 0) Optional: skip token and use default admin (OFFLINE_REQUIRE_TOKEN=false)
        if (!config('app.offline_require_token', true)) {
            $defaultAdmin = $this->defaultAdmin();
            if ($defaultAdmin !== null) {
                $request->setUserResolver(fn () => $defaultAdmin);
                Log::channel('single')->info('Novustream offline API: auth skipped (OFFLINE_REQUIRE_TOKEN=false)', [
                    'admin_id' => $defaultAdmin->id,
                ]);
                return $next($request);
            }
        }

        // 1) Try local Sanctum token first
        $user = Auth::guard('sanctum')->user();
        if ($user !== null) {
            return $next($request);
        }

        $token = $request->bearerToken();
        if (empty($token)) {
            return $this->unauthorized($request);
        }

        // 1b) Fallback: resolve token directly (handles Admin tokens when guard does not)
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken !== null) {
            $valid = !$accessToken->expires_at || !$accessToken->expires_at->isPast();
            $tokenable = $valid ? $accessToken->tokenable : null;
            if ($tokenable instanceof Admin && in_array($tokenable->user_type, ['technician', 'admin'], true)) {
                $request->setUserResolver(fn () => $tokenable);
                Log::channel('single')->info('Novustream offline API: authenticated via direct token lookup', [
                    'admin_id' => $tokenable->id,
                ]);
                return $next($request);
            }
            // Token found in DB but rejected: log reason for 401
            Log::channel('single')->warning('Novustream offline API: token in DB but rejected', [
                'token_found' => true,
                'expired' => !$valid,
                'tokenable_type' => $accessToken->tokenable_type ?? null,
                'user_type' => $tokenable->user_type ?? null,
            ]);
        } else {
            // Token not in this app's DB (e.g. issued by another app or never logged in here)
            Log::channel('single')->warning('Novustream offline API: token not found in DB', [
                'token_preview' => strlen($token) > 8 ? (substr($token, 0, 4) . '...' . substr($token, -4)) : '(short)',
                'has_pipe' => str_contains($token, '|'),
                'hint' => 'Ensure user logged in to this app (sta-rita) and use that token only for this API.',
            ]);
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
                    if (empty($email)) {
                        Log::channel('single')->warning('Novustream offline API: partner returned user but no email', [
                            'partner' => $partnerUrl,
                            'keys' => array_keys($data ?? []),
                        ]);
                    } else {
                        $localAdmin = Admin::where('email', $email)->first();
                        if (!$localAdmin) {
                            Log::channel('single')->warning('Novustream offline API: partner token email has no local Admin', [
                                'email' => $email,
                                'partner' => $partnerUrl,
                                'hint' => 'Create an admin in this app with this email (technician or admin).',
                            ]);
                        } elseif (!in_array($localAdmin->user_type, ['technician', 'admin'], true)) {
                            Log::channel('single')->warning('Novustream offline API: partner token local Admin not allowed', [
                                'admin_id' => $localAdmin->id,
                                'user_type' => $localAdmin->user_type,
                                'partner' => $partnerUrl,
                            ]);
                        } else {
                            $request->setUserResolver(fn () => $localAdmin);
                            Log::channel('single')->info('Novustream offline API: accepted partner token', [
                                'admin_id' => $localAdmin->id,
                                'email' => $email,
                                'partner' => $partnerUrl,
                            ]);
                            return $next($request);
                        }
                    }
                } else {
                    Log::channel('single')->warning('Novustream offline API: partner rejected token', [
                        'partner' => $partnerUrl,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
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
        $label = config('app.offline_app_label', 'this app');
        return response()->json([
            'error' => 'Unauthenticated',
            'message' => 'Token not recognized. Log in to this app (' . $label . ') via POST /api/login or POST /api/auth/login and use the returned token.',
        ], 401);
    }

    private function defaultAdmin(): ?Admin
    {
        $id = config('app.offline_default_admin_id');
        if ($id !== null && $id !== '') {
            $admin = Admin::find((int) $id);
            if ($admin && in_array($admin->user_type, ['technician', 'admin'], true)) {
                return $admin;
            }
        }
        return Admin::whereIn('user_type', ['technician', 'admin'])
            ->orderBy('id')
            ->first();
    }
}
