<?php

namespace App\Services\Ranking;

use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\SeasonScore;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaisonAuswertungService
{
    private const LEVEL_WEIGHTS = [
        'dsv_dm'    => 100,
        'dsv_djm'   => 90,
        'nsv'       => 70,
        'shsv_lm'   => 50,
        'shsv_open' => 30,
        'vereins'   => 10,
    ];

    private const PLACE_BONUS = [1 => 50, 2 => 30, 3 => 20];
    private const FINAL_BONUS  = 10;
    private const PB_BONUS     = 15;

    /**
     * Return season score ranking sorted by total_score descending.
     */
    public function score(int $seasonYear): Collection
    {
        return SeasonScore::with(['athlete', 'user'])
            ->where('season_year', $seasonYear)
            ->orderByDesc('total_score')
            ->get();
    }

    /**
     * Return curated highlight list for a season.
     */
    public function highlights(int $seasonYear): array
    {
        $results = CompetitionResult::with(['user', 'competition'])
            ->whereHas('competition', fn($q) => $q->whereYear('date', $seasonYear))
            ->where('time_ms', '>', 0)
            ->get();

        $highlights = [];

        foreach ($results as $r) {
            if ($r->placement && $r->placement <= 3) {
                $weight = self::LEVEL_WEIGHTS[$r->competition->level ?? 'vereins'] ?? 10;
                $highlights[] = [
                    'type'         => 'podium',
                    'level_weight' => $weight,
                    'athlete'      => $r->user?->name,
                    'detail'       => "Platz {$r->placement} über {$r->distance}m {$r->discipline} bei {$r->competition->name}",
                    'date'         => $r->competition->date?->format('Y-m-d'),
                ];
            }
            if ($r->breaks_vereinsrekord) {
                $highlights[] = [
                    'type'    => 'club_record',
                    'athlete' => $r->user?->name,
                    'detail'  => "Vereinsrekord {$r->distance}m {$r->discipline}",
                    'date'    => $r->competition->date?->format('Y-m-d'),
                ];
            }
        }

        usort($highlights, fn($a, $b) => ($b['level_weight'] ?? 0) <=> ($a['level_weight'] ?? 0));

        return $highlights;
    }

    /**
     * Recalculate and persist season_scores for all swimmers in a given year.
     */
    public function recalculate(int $seasonYear): void
    {
        $results = CompetitionResult::with(['user', 'competition'])
            ->whereHas('competition', fn($q) => $q->whereYear('date', $seasonYear))
            ->where('time_ms', '>', 0)
            ->get()
            ->groupBy('user_id');

        DB::transaction(function () use ($results, $seasonYear) {
            SeasonScore::where('season_year', $seasonYear)->delete();

            foreach ($results as $userId => $userResults) {
                $user = User::find($userId);
                if (!$user) continue;

                $score = $podiums = $finals = $pbs = $records = 0;

                foreach ($userResults as $r) {
                    $weight = self::LEVEL_WEIGHTS[$r->competition->level ?? 'vereins'] ?? 10;
                    $score += $r->dsv_points ?? 0;
                    $score += ($r->placement && isset(self::PLACE_BONUS[$r->placement])
                        ? self::PLACE_BONUS[$r->placement] * $weight / 10 : 0);
                    if ($r->is_final)          { $score += self::FINAL_BONUS; $finals++; }
                    if ($r->is_personal_best)  { $score += self::PB_BONUS;    $pbs++; }
                    if ($r->placement <= 3)    $podiums++;
                    if ($r->breaks_vereinsrekord) $records++;
                }

                SeasonScore::create([
                    'athlete_id'     => $userId,
                    'user_id'        => $userId,
                    'season_year'    => $seasonYear,
                    'total_score'    => (int) $score,
                    'podiums'        => $podiums,
                    'finals'         => $finals,
                    'personal_bests' => $pbs,
                    'club_records'   => $records,
                    'recalculated_at'=> now(),
                ]);
            }
        });
    }
}
