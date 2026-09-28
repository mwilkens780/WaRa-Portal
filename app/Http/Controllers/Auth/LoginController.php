<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anmeldung.
 *
 * Gegen das Durchprobieren von Passwoertern gibt es eine Bremse: nach fuenf
 * Fehlversuchen ist die Kombination aus Adresse und Absender fuer eine
 * Viertelstunde gesperrt. Ohne sie kann ein Angreifer beliebig oft raten -
 * bei einem Portal mit Mitgliederdaten ist das keine Kleinigkeit.
 *
 * Die Fehlermeldung bleibt dabei absichtlich unspezifisch: Sie verraet nie,
 * ob es die Adresse ueberhaupt gibt.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 900;   // 15 Minuten

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = $this->throttleKey($request, $credentials['email']);
        $this->ensureNotLocked($key);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (!$user->active) {
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages([
                    'email' => 'Dein Konto ist nicht mehr aktiv (ehemaliges Mitglied).',
                ]);
            }

            // Der Portal-Zugang haengt nicht an der Mitgliedschaft: ein Konto
            // kann gesperrt sein, waehrend die Person Mitglied bleibt.
            //
            // ?? true: Waehrend einer Auslieferung ist der Code einen Moment
            // vor der Migration auf dem Server. Fehlt die Spalte noch, gilt der
            // Zugang als offen - eine Auslieferung darf niemanden aussperren.
            if (!($user->portal_active ?? true)) {
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages([
                    'email' => 'Dein Portal-Zugang ist deaktiviert. Bitte wende dich an einen Administrator.',
                ]);
            }

            if (!$user->role) {
                Auth::logout();
                $request->session()->invalidate();
                throw ValidationException::withMessages([
                    'email' => 'Deinem Konto ist kein Portal-Zugang zugewiesen. Bitte wende dich an einen Administrator.',
                ]);
            }

            // Neue Sitzungs-ID nach erfolgreicher Anmeldung (gegen Session Fixation)
            $request->session()->regenerate();
            RateLimiter::clear($key);

            // Nur der Zeitpunkt, keine IP - siehe Datenschutzerklaerung
            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            // Wer sein eigenes Passwort gesetzt hat und sich jetzt anmeldet,
            // hat die Einrichtung abgeschlossen.
            $user->maybeMarkPortalActivated();

            return redirect()->intended($user->homeUrl());
        }

        RateLimiter::hit($key, self::LOCK_SECONDS);

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

    private function throttleKey(Request $request, string $email): string
    {
        return 'login:' . Str::lower($email) . '|' . $request->ip();
    }

    private function ensureNotLocked(string $key): void
    {
        if (!RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $minuten = max(1, (int) ceil($seconds / 60));

        throw ValidationException::withMessages([
            'email' => "Zu viele Anmeldeversuche. Bitte versuche es in {$minuten} Minute(n) erneut "
                     . 'oder setze dein Passwort über „Passwort vergessen" neu.',
        ]);
    }
}
