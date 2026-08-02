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
        if (!auth()->check()) {
            View::share('appCurrentSeason', null);
            View::share('appAllSeasons', collect());
            return $next($request);
        }

        try {
            $allSeasons = Season::orderByDesc('start_date')->get();

            // URL param → update session (e.g. from page-specific season selectors)
            if ($request->filled('season_id')) {
                $picked = $allSeasons->firstWhere('id', (int) $request->input('season_id'));
                if ($picked) {
                    session(['app_season_id' => $picked->id]);
                }
            }

            // Session preference → fall back to Season::current()
            $seasonId = session('app_season_id');
            $season   = $seasonId ? $allSeasons->firstWhere('id', $seasonId) : null;
            $season   = $season ?? Season::current();

            View::share('appCurrentSeason', $season);
            View::share('appAllSeasons', $allSeasons);
        } catch (\Throwable) {
            View::share('appCurrentSeason', null);
            View::share('appAllSeasons', collect());
        }

        return $next($request);
    }
}
