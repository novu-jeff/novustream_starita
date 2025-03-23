<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
    
        if (auth()->guard('admins')->attempt($credentials)) {
            
            $admin = auth()->guard('admins')->user();

            if($admin->user_type !== 'technician') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized technician',
                ], 401);
            }

            $token = $admin->createToken('authToken', ['role:technician'])->plainTextToken;
    
            return response()->json([
                'status' => 'success',
                'token' => $token,
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out',
        ], 200);
    }
    

}
