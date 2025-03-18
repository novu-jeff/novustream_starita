<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{

    public function index()
    {
        return view('auth.login');
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('admins')->attempt($credentials)) {
            return redirect()->route('dashboard');
        }

        if (Auth::guard('web')->attempt($credentials)) {
            return redirect()->route('account-overview.index');
        }

        return redirect()->back()->withErrors(['error' => 'Invalid credentials']);
    }

    public function logout()
    {
        Auth::guard('admins')->logout();
        Auth::guard('web')->logout();

        return redirect()->route('auth.login');
    }
}
