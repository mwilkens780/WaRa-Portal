<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class VerifyWebClubToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configured = Setting::getCached('crawler.webclub.import_token', '');

        if (!$configured) {
            return response()->json(['error' => 'Import-Token nicht konfiguriert.'], 503);
        }

        $provided = $request->bearerToken();

        if (!$provided || !hash_equals($configured, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
