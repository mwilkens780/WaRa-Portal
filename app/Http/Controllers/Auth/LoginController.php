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
                    'email' => 'Dein Konto ist nicht mehr aktiv (ehemaliges Mitglied).',
                ]);
            }

            if (!$user->role) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'Deinem Konto ist kein Portal-Zugang zugewiesen. Bitte wende dich an einen Administrator.',
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
            'admin'        => route('admin.dashboard'),
            'trainer'      => route('trainer.dashboard'),
            'vorstand'     => route('admin.dashboard'),
            'schwimmer'    => route('swimmer.dashboard'),
            'elternteil'   => route('parent.dashboard'),
            'kampfrichter' => route('swimmer.dashboard'),
            default        => route('login'),
        };
    }
}
