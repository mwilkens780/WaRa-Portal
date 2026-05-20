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

        if (!empty($roles) && !$request->user()->hasRole($roles)) {
            abort(403, 'Zugriff verweigert. Du hast nicht die erforderliche Berechtigung.');
        }

        return $next($request);
    }
}
