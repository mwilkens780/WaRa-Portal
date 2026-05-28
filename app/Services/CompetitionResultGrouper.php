<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Merges CompetitionResult rows that represent the same physical swim but were
 * scored in multiple categories (e.g. AK14 + Offene Klasse).
 *
 * Detection: same (user_id, competition_id, discipline, distance, time_ms) = same swim.
 * Different time_ms for the same (user, competition, discipline, distance) = separate starts
 * (heat vs. final). Finals are explicitly flagged when is_final=true on any row in the group.
 */
class CompetitionResultGrouper
{
    /**
     * For the competition show view.
     * Returns: Collection keyed by "discipline_distance" → Collection of merged swim objects.
     */
    public static function forCompetition(Collection $results): Collection
    {
        return $results
            ->groupBy(fn($r) => $r->discipline . '_' . $r->distance)
            ->map(fn($discGroup) => static::mergeSwims($discGroup)
                ->sortBy(fn($s) => $s->is_dns ? PHP_INT_MAX : $s->time_ms)
                ->values()
            );
    }

    /**
     * For the swimmer competitions list.
     * Returns: flat Collection of merged swim objects, sorted newest competition first.
     */
    public static function forSwimmer(Collection $results): Collection
    {
        return $results
            ->groupBy(fn($r) => $r->competition_id . '_' . $r->user_id . '_' . $r->discipline . '_' . $r->distance)
            ->map(fn($group) => static::mergeSwims($group)->values())
            ->flatten(1)
            ->sortByDesc(fn($s) => $s->competition?->date?->timestamp ?? 0)
            ->values();
    }

    /**
     * Within a (competition, user, discipline, distance) group:
     * group further by time_ms → same time = same swim in multiple categories.
     */
    private static function mergeSwims(Collection $results): Collection
    {
        return $results
            ->groupBy('time_ms')
            ->map(function (Collection $sameTimeGroup) {
                $first = $sameTimeGroup->first();

                // Collect all age_group/placement pairs (skip if both null)
                $placements = $sameTimeGroup
                    ->map(fn($r) => (object)[
                        'age_group'  => $r->age_group,
                        'placement'  => $r->placement,
                    ])
                    ->filter(fn($p) => $p->placement > 0 || $p->age_group !== null)
                    ->unique(fn($p) => ($p->age_group ?? '') . '_' . ($p->placement ?? ''))
                    ->sortBy(fn($p) => $p->age_group ?? 'zzz')
                    ->values();

                $bestPlacement = $sameTimeGroup
                    ->where('placement', '>', 0)
                    ->min('placement');

                return (object)[
                    'id'                   => $first->id,
                    'result_ids'           => $sameTimeGroup->pluck('id')->toArray(),
                    'user_id'              => $first->user_id,
                    'user'                 => $first->relationLoaded('user') ? $first->user : null,
                    'competition_id'       => $first->competition_id,
                    'competition'          => $first->relationLoaded('competition') ? $first->competition : null,
                    'discipline'           => $first->discipline,
                    'discipline_label'     => $first->discipline_label,
                    'distance'             => $first->distance,
                    'time_ms'              => $first->time_ms,
                    'formatted_time'       => $first->formatted_time,
                    'is_personal_best'     => $sameTimeGroup->contains('is_personal_best', true),
                    'is_final'             => $sameTimeGroup->contains('is_final', true),
                    'breaks_vereinsrekord' => $sameTimeGroup->contains('breaks_vereinsrekord', true),
                    'breaks_landesrekord'  => $sameTimeGroup->contains('breaks_landesrekord', true),
                    'notes'                => $first->notes,
                    'gender'               => $first->gender,
                    'best_placement'       => $bestPlacement,
                    'placements'           => $placements,
                    'is_dns'               => $first->time_ms <= 0,
                ];
            });
    }
}
