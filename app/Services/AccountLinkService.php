<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Einmallinks fuer Passwort-Einrichtung und -Zuruecksetzung.
 *
 * Die Links tragen ein Token aus Laravels Passwort-Broker: gehasht in der
 * Datenbank, mit Ablaufdatum, und beim Einloesen verbraucht. Deshalb steht in
 * keiner Mail ein Passwort im Klartext - wer die Mail spaeter in die Finger
 * bekommt, findet darin nichts mehr, was noch funktioniert.
 */
class AccountLinkService
{
    /** Link aus der Willkommensmail - laengere Frist (siehe config/auth.php). */
    public function setupUrl(User $user): string
    {
        return $this->url('password.setup', Password::broker('welcome')->createToken($user), $user);
    }

    /** Link zum Zuruecksetzen - kurze Frist. */
    public function resetUrl(User $user): string
    {
        return $this->url('password.reset', Password::broker()->createToken($user), $user);
    }

    private function url(string $route, string $token, User $user): string
    {
        return route($route, ['token' => $token]) . '?email=' . urlencode($user->email);
    }
}
