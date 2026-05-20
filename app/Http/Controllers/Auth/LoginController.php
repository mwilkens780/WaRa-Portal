<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (!$user->active) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'Dein Konto wurde deaktiviert.',
                ]);
            }

            return redirect()->intended($this->redirectTo($user->role));
        }

        throw ValidationException::withMessages([
            'email' => 'Die eingegebenen Zugangsdaten sind nicht korrekt.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function redirectTo(string $role): string
    {
        return match($role) {
            'admin' => route('admin.dashboard'),
            'trainer' => route('trainer.dashboard'),
            'schwimmer' => route('swimmer.dashboard'),
            'elternteil' => route('parent.dashboard'),
            default => route('login'),
        };
    }
}
