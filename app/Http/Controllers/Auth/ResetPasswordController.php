<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\ResetsPasswords;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    /**
     * Return reset links to the login page, where the reset dialog is shown.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return redirect()->route('auth.index', array_filter([
            'reset_token' => $token,
            'email' => $request->query('email'),
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Enforce the same password requirements used during registration.
     */
    protected function rules()
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9]/'],
        ];
    }

    protected function validationErrorMessages()
    {
        return [
            'password.min' => 'The password must be at least 8 characters long.',
            'password.regex' => 'The password must include at least one capital letter, one number, and one special character.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
