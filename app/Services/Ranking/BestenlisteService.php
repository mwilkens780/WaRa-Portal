<?php

namespace App\Services\Ranking;

use App\Models\Competition;
use App\Models\ExtCompetitionResult;
use Illuminate\Support\Collection;

class BestenlisteService
{
    /**
     * Return the top-N results for a given discipline/distance/gender combination.
     * Searches ext_competition_results (all clubs).
     */
    public function get(
        string  $discipline,
        int     $distance,
        string  $gender,
        ?string $course  = null,
        ?int    $year    = null,
        int     $limit   = 25
    ): Collection {
        return ExtCompetitionResult::with(['athlete', 'competition'])
            ->where('discipline', $discipline)
            ->where('distance', $distance)
            ->where('gender', $gender)
            ->where('status', 'OK')
            ->whereNotNull('time_ms')
            ->where('time_ms', '>', 0)
            ->when($course, fn($q) =>
                $q->whereHas('competition', fn($q) => $q->where('course', $course))
            )
            ->when($year, fn($q) =>
                $q->whereHas('competition', fn($q) => $q->whereYear('date', $year))
            )
            ->orderBy('time_ms')
            ->limit($limit)
            ->get();
    }

    /**
     * Return own-club best times per discipline/distance (from competition_results).
     * Used for club-internal rankings.
     */
    public function getClubBestTimes(string $gender, ?string $course = null, ?int $year = null): Collection
    {
        return \App\Models\CompetitionResult::with(['user', 'competition'])
            ->where('gender', $gender)
            ->where('time_ms', '>', 0)
            ->where('is_personal_best', true)
            ->when($course, fn($q) =>
                $q->whereHas('competition', fn($q) => $q->where('course', $course))
            )
            ->when($year, fn($q) =>
                $q->whereHas('competition', fn($q) => $q->whereYear('date', $year))
            )
            ->orderBy('discipline')->orderBy('distance')->orderBy('time_ms')
            ->get()
            ->groupBy(fn($r) => $r->discipline . '_' . $r->distance);
    }

    /**
     * Keyword-based competition level detection from name string.
     */
    public function detectLevel(string $name): string
    {
        $n = mb_strtolower($name);
        if (str_contains($n, ' dm ')  || str_contains($n, 'deutsche meisterschaft')) return 'dsv_dm';
        if (str_contains($n, ' djm ') || str_contains($n, 'jahrgangs'))              return 'dsv_djm';
        if (str_contains($n, 'ndm')   || str_contains($n, 'norddeutsch'))            return 'nsv';
        if (str_contains($n, ' lm ')  || str_contains($n, 'landesmeisterschaft'))    return 'shsv_lm';
        if (str_contains($n, 'shsv')  || str_contains($n, 'schleswig'))              return 'shsv_open';
        return 'vereins';
    }
}
