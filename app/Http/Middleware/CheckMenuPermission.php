<?php

namespace App\Http\Middleware;

use App\Models\MenuPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die Berechtigungs-Matrix auf Routenebene durch.
 *
 * Die Matrix hat bisher nur die Links in der Seitenleiste ein- und ausgeblendet.
 * Wer die Adresse kannte, kam trotzdem an den Bereich. Dieses Middleware macht
 * aus der Sichtbarkeit eine echte Berechtigung: gleicher Schluessel, gleiche
 * Entscheidung.
 *
 * Verwendung: ->middleware('menu:records') – der Schluessel muss einer der
 * Eintraege aus MenuPermission::MENU_ITEMS sein.
 */
class CheckMenuPermission
{
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!MenuPermission::can($user->role, $menuKey)) {
            abort(403, 'Zugriff verweigert. Dieser Bereich ist für deine Rolle nicht freigegeben.');
        }

        return $next($request);
    }
}
