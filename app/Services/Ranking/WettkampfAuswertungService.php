<?php

namespace App\Services\Ranking;

use App\Models\Competition;
use App\Models\CompetitionResult;

class WettkampfAuswertungService
{
    /**
     * Generate a club report for a single competition:
     * all results of our swimmers with PB-delta and highlights.
     */
    public function clubReport(Competition $competition): array
    {
        $results = CompetitionResult::with('user')
            ->where('competition_id', $competition->id)
            ->where('time_ms', '>', 0)
            ->get();

        $report   = [];
        $summary  = [
            'total_starts'   => 0,
            'podiums'        => 0,
            'finals'         => 0,
            'personal_bests' => 0,
            'club_records'   => 0,
        ];

        foreach ($results as $result) {
            // Previous best time before this competition
            $previousBest = CompetitionResult::where('user_id', $result->user_id)
                ->where('discipline', $result->discipline)
                ->where('distance', $result->distance)
                ->where('time_ms', '>', 0)
                ->where('competition_id', '!=', $competition->id)
                ->min('time_ms');

            $pbDelta = $previousBest ? ($result->time_ms - $previousBest) : null;

            $highlights = [];
            if ($result->placement && $result->placement <= 3) $highlights[] = 'podium';
            if ($result->is_final)      $highlights[] = 'final';
            if ($result->is_personal_best) $highlights[] = 'personal_best';
            if ($result->breaks_vereinsrekord) $highlights[] = 'club_record';
            if ($result->breaks_landesrekord)  $highlights[] = 'state_record';

            $summary['total_starts']++;
            if (in_array('podium', $highlights))       $summary['podiums']++;
            if (in_array('final', $highlights))        $summary['finals']++;
            if (in_array('personal_best', $highlights)) $summary['personal_bests']++;
            if (in_array('club_record', $highlights))   $summary['club_records']++;

            $report[] = [
                'athlete'     => ['id' => $result->user_id, 'name' => $result->user?->name],
                'event'       => ['distance' => $result->distance, 'discipline' => $result->discipline, 'gender' => $result->gender],
                'time'        => ['ms' => $result->time_ms, 'formatted' => $result->formatted_time ?? ''],
                'placement'   => $result->placement,
                'pb_delta_ms' => $pbDelta,
                'highlights'  => $highlights,
            ];
        }

        return [
            'competition' => ['id' => $competition->id, 'name' => $competition->name, 'level' => $competition->level],
            'results'     => $report,
            'summary'     => $summary,
        ];
    }
}
