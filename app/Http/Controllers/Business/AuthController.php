<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('business')->check()) {
            return redirect()->route('business.dashboard');
        }

        return view('business.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login_email' => ['required', 'email'],
            'password'    => ['required'],
        ]);

        if (Auth::guard('business')->attempt([
            'login_email' => $credentials['login_email'],
            'password'    => $credentials['password'],
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('business.dashboard'));
        }

        return back()->withErrors([
            'login_email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('login_email');
    }

    public function logout(Request $request)
    {
        Auth::guard('business')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('business.login');
    }
}
