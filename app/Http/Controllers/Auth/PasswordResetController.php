<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\Mailer;
use App\Support\MailTopic;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Passwort vergessen, neu setzen und erstmalig einrichten.
 *
 * Zwei Grundsaetze:
 *
 *  - Die Anforderung verraet nie, ob es ein Konto zu der Adresse gibt. Sonst
 *    laesst sich das Formular benutzen, um Mitgliedsadressen zu erraten.
 *  - Die Links tragen ein Token aus Laravels Passwort-Broker: gehasht
 *    gespeichert, mit Ablaufdatum, beim Einloesen verbraucht.
 */
class PasswordResetController extends Controller
{
    public function __construct(private Mailer $mailer) {}

    // ── Passwort vergessen ───────────────────────────────────────────────────

    public function showRequestForm()
    {
        return view('auth.password-forgot');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Bremse gegen Massenanforderungen - je Adresse und je Absender-IP
        foreach (["pwreset:" . Str::lower($data['email']), 'pwreset-ip:' . $request->ip()] as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                throw ValidationException::withMessages([
                    'email' => "Zu viele Versuche. Bitte in {$minutes} Minute(n) noch einmal probieren.",
                ]);
            }
            RateLimiter::hit($key, 3600);
        }

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->active) {
            $this->mailer->send($user, MailTopic::ACCOUNT, new PasswordResetMail($user),
                (new PasswordResetMail($user))->defaultSubject());
        }

        // Immer dieselbe Antwort, unabhaengig davon, ob es das Konto gibt
        return back()->with('success',
            'Wenn es zu dieser Adresse einen Zugang gibt, ist eine Mail mit einem Link unterwegs. '
            . 'Schau auch im Spam-Ordner nach.');
    }

    // ── Formular aus dem Link ────────────────────────────────────────────────

    /** Erstmalige Einrichtung aus der Willkommensmail (langer Link). */
    public function showSetupForm(Request $request, string $token)
    {
        return view('auth.password-set', [
            'token'   => $token,
            'email'   => $request->query('email'),
            'isSetup' => true,
        ]);
    }

    /** Zuruecksetzen (kurzer Link). */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.password-set', [
            'token'   => $token,
            'email'   => $request->query('email'),
            'isSetup' => false,
        ]);
    }

    public function setup(Request $request)
    {
        return $this->store($request, 'welcome');
    }

    public function reset(Request $request)
    {
        return $this->store($request, 'users');
    }

    // ── gemeinsame Verarbeitung ──────────────────────────────────────────────

    private function store(Request $request, string $broker)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], [], ['password' => 'Passwort']);

        $isSetup = $broker === 'welcome';

        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($isSetup) {
                $user->forceFill([
                    'password'         => $password,     // Cast 'hashed' erledigt das Hashen
                    'initial_password' => null,          // ab jetzt gilt das eigene Passwort
                    'remember_token'   => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                // Beim erstmaligen Einrichten waere eine "Passwort geaendert"-Mail
                // nur Laerm - die Willkommensmail kam gerade erst.
                if (!$isSetup) {
                    $this->mailer->send($user, MailTopic::ACCOUNT, new PasswordChangedMail($user),
                        (new PasswordChangedMail($user))->defaultSubject());
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $this->message($status, $isSetup),
            ]);
        }

        return redirect()->route('login')->with('success', $isSetup
            ? 'Dein Passwort ist gesetzt. Du kannst dich jetzt anmelden.'
            : 'Dein neues Passwort ist gespeichert. Du kannst dich jetzt anmelden.');
    }

    private function message(string $status, bool $isSetup): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => $isSetup
                ? 'Dieser Einrichtungslink ist abgelaufen oder wurde bereits benutzt. '
                  . 'Fordere über „Passwort vergessen" einen neuen an.'
                : 'Dieser Link ist abgelaufen oder wurde bereits benutzt. Bitte fordere einen neuen an.',
            Password::INVALID_USER  => 'Zu dieser E-Mail-Adresse gibt es keinen Zugang.',
            Password::RESET_THROTTLED => 'Zu viele Versuche. Bitte warte einen Moment.',
            default => 'Das Passwort konnte nicht gesetzt werden. Bitte versuche es erneut.',
        };
    }
}
