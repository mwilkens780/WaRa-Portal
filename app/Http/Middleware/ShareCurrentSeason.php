<?php

namespace App\Http\Middleware;

use App\Models\Season;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ShareCurrentSeason
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check()) {
            try {
                View::share('appCurrentSeason', Season::current());
            } catch (\Throwable) {
                View::share('appCurrentSeason', null);
            }
        } else {
            View::share('appCurrentSeason', null);
        }

        return $next($request);
    }
}
