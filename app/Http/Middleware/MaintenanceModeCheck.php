<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Setting::getBool('maintenance_mode')) {
            return $next($request);
        }

        // Login, Passwortstrecke und System-Routen immer zugänglich lassen.
        // Die Passwortstrecke gehört dazu, weil sich der Ablauf sonst während
        // der Wartung nicht testen lässt - und genau dafür ist sie gedacht.
        if ($request->routeIs(
            'login', 'login.post', 'maintenance', 'cron.run', 'api.webclub-import',
            'password.request', 'password.email',
            'password.setup', 'password.setup.store',
            'password.reset', 'password.reset.store',
        )) {
            return $next($request);
        }

        if ($request->user()) {
            // Admins bypassen immer
            if ($request->user()->role === 'admin') {
                return $next($request);
            }
            // Explizit freigegebene User
            if (in_array($request->user()->id, Setting::getBypassUserIds())) {
                return $next($request);
            }
        }

        $message = Setting::getCached('maintenance_message',
            'Das Portal wird gerade gewartet. Bitte versuche es später erneut.');

        return response()->view('maintenance', compact('message'), 503);
    }
}
