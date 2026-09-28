<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->active) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Dein Konto wurde deaktiviert. Bitte wende dich an einen Administrator.']);
        }

        // Sperrt ein Administrator den Zugang, endet auch die laufende Sitzung -
        // sonst wirkt die Sperre erst nach dem naechsten Abmelden.
        if (!($request->user()->portal_active ?? true)) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Dein Portal-Zugang wurde deaktiviert. Bitte wende dich an einen Administrator.']);
        }

        if (!empty($roles) && !$request->user()->hasRole($roles)) {
            abort(403, 'Zugriff verweigert. Du hast nicht die erforderliche Berechtigung.');
        }

        return $next($request);
    }
}
