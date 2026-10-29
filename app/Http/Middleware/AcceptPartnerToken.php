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

        $token = $this->normalizeBearerToken($request->bearerToken());
        if (empty($token)) {
            Log::channel('single')->warning('Novustream offline API: missing bearer token', [
                'path' => $request->path(),
                'auth_header_present' => $request->headers->has('Authorization'),
            ]);
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
            Log::channel('single')->warning('Novustream offline API: direct token found but not allowed', [
                'path' => $request->path(),
                'token_id' => $accessToken->id,
                'tokenable_type' => $accessToken->tokenable_type,
                'is_valid' => $valid,
                'resolved_user_type' => $tokenable?->user_type,
            ]);
        } else {
            Log::channel('single')->warning('Novustream offline API: direct token lookup failed', [
                'path' => $request->path(),
                'token_length' => strlen($token),
                'token_prefix' => substr($token, 0, 8),
                'has_pipe_id_format' => str_contains($token, '|'),
                'hint' => 'Token may be malformed/truncated, wrapped in quotes, or from another app/db.',
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

    private function normalizeBearerToken(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $token = trim($token);
        $token = trim($token, "\"' \t\n\r\0\x0B");

        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        return $token === '' ? null : $token;
    }
}
