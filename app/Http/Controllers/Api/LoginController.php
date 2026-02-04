<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        Log::channel('single')->info('Novustream offline API: login attempt', ['email' => $credentials['email'] ?? null]);

        if (auth()->guard('admins')->attempt($credentials)) {

            $admin = auth()->guard('admins')->user();

            if (!in_array($admin->user_type, ['technician', 'admin'], true)) {
                Log::channel('single')->warning('Novustream offline API: login rejected (not technician or admin)', ['admin_id' => $admin->id, 'user_type' => $admin->user_type]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only technician or admin can use the offline app.',
                ], 401);
            }

            $token = $admin->createToken('authToken', ['role:' . $admin->user_type])->plainTextToken;

            $user = [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'user_type' => $admin->user_type,
                'zone_assigned' => $admin->zone_assigned,
            ];

            Log::channel('single')->info('Novustream offline API: login success', ['admin_id' => $admin->id]);

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => $user,
                'data' => $user,
            ], 200);
        }

        Log::channel('single')->warning('Novustream offline API: login failed (invalid credentials)', ['email' => $credentials['email'] ?? null]);
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized',
        ], 401);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            Log::channel('single')->info('Novustream offline API: logout', ['admin_id' => $user->id]);
            $user->tokens()->delete();
        } else {
            Log::channel('single')->info('Novustream offline API: logout (no authenticated user)');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out',
        ], 200);
    }
}
