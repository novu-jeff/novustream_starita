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

        try {
            
            if (Auth::guard('admins')->attempt($credentials)) {
                return redirect()->route('dashboard');
            }
    
            if (Auth::guard('web')->attempt($credentials)) {
                return redirect()->route('account-overview.index');
            }


        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['password' => 'Email or password is incorrect']);            
        }

        return redirect()->back()
            ->withInput()
            ->withErrors(['password' => 'Email or password is incorrect']);
    }

    public function logout()
    {
        Auth::guard('admins')->logout();
        Auth::guard('web')->logout();

        return redirect()->route('auth.login');
    }
}
