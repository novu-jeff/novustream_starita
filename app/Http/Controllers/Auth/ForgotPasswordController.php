<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Validate the address against concessionaire users, then issue the
     * reset notification through Laravel's configured users password broker.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->merge([
            'email' => trim((string) $request->input('email', '')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = trim($validated['email']);
        $user = User::where('email', $email)->first();

        if (!$user) {
            $message = 'We could not find an account with that email address.';

            return response()->json([
                'message' => $message,
                'errors' => ['email' => [$message]],
            ], 422);
        }

        $status = Password::broker('users')->sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent. Check your email inbox.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'A reset link was recently sent. Please wait before requesting another.',
            ], 429);
        }

        return response()->json([
            'message' => 'We could not send a reset link right now. Please try again.',
        ], 422);
    }
}
