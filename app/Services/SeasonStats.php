<?php

namespace App\Services;

use App\Models\CompetitionResult;
use App\Models\Season;
use App\Models\SwimmingTime;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Persönliche Wettkampf-Kennzahlen je Saison für Sportler und Eltern
 * (Martin, 15.10.2026): Starts, Wettkämpfe, Podestplätze (jede Platzierung 1–3,
 * wie im Ergebnis), Finals, Bestzeiten, Vereinsrekorde, beste WA-Punkte –
 * jeweils mit Vorsaison. Bewusst KEINE Gesamtpunktzahl und kein Vergleich mit
 * anderen Sportlern.
 *
 * Gezählt werden Einzelergebnisse mit Zeit (auch Startschwimmer-Zeiten aus
 * Staffeln, die als offizielle Einzelzeit gelten); keine Übungsformen.
 */
class SeasonStats
{
    public function __construct(private WaScoringService $wa) {}

    /** @return array{season: ?Season, previous: ?Season, now: array, before: ?array, highlights: Collection} */
    public function forUser(User $swimmer, ?Season $season = null): array
    {
        $season ??= Season::current();
        $previous = $season
            // Vorsaison = die letzte, die vor Beginn dieser endet (keine überlappenden Einträge)
            ? Season::where('end_date', '<', $season->start_date)->orderByDesc('start_date')->first()
            : null;

        $results = $season ? $this->results($swimmer, $season) : collect();

        return [
            'season'     => $season,
            'previous'   => $previous,
            'now'        => $this->figures($swimmer, $results),
            'before'     => $previous ? $this->figures($swimmer, $this->results($swimmer, $previous)) : null,
            'highlights' => $this->highlights($results),
        ];
    }

    /** Kompakte Werte für Dashboard-Kacheln */
    public function summary(User $swimmer): array
    {
        $s = $this->forUser($swimmer);
        return ['season' => $s['season'], 'now' => $s['now'], 'before' => $s['before']];
    }

    private function results(User $swimmer, Season $season): Collection
    {
        return CompetitionResult::with('competition:id,name,date,location,course')
            ->where('user_id', $swimmer->id)
            ->where('time_ms', '>', 0)
            ->whereNull('exercise')
            ->whereHas('competition', fn($q) => $q->whereBetween('date', [$season->start_date, $season->end_date]))
            ->get();
    }

    private function figures(User $swimmer, Collection $results): array
    {
        $podium = $results->filter(fn($r) => $r->placement && $r->placement <= 3);

        return [
            'starts'         => $results->count(),
            'competitions'   => $results->pluck('competition_id')->unique()->count(),
            'podiums'        => $podium->count(),
            'gold'           => $podium->where('placement', 1)->count(),
            'silver'         => $podium->where('placement', 2)->count(),
            'bronze'         => $podium->where('placement', 3)->count(),
            'finals'         => $results->where('is_final', true)->count(),
            'personal_bests' => $results->where('is_personal_best', true)->count(),
            'club_records'   => $results->where('breaks_vereinsrekord', true)->count(),
            'best_wa'        => $this->bestWa($swimmer, $results),
        ];
    }

    /** Bestes Ergebnis nach World-Aquatics-Punkten (gespeichert oder aus der Punktetabelle des Jahres) */
    private function bestWa(User $swimmer, Collection $results): ?array
    {
        $best = null;
        foreach ($results as $r) {
            $points = $r->wa_points;
            $pool   = match ($r->competition?->course) { 'Langbahn' => 50, 'Kurzbahn' => 25, default => null };
            $gender = in_array($r->gender, ['M', 'F'], true) ? $r->gender : $swimmer->gender;
            if (!$points && $pool && in_array($gender, ['M', 'F'], true) && $r->competition?->date) {
                $points = $this->wa->calculatePoints($r->discipline, (int) $r->distance, $gender, (int) $r->time_ms,
                    (int) $r->competition->date->year, $pool)
                    ?? $this->wa->calculatePoints($r->discipline, (int) $r->distance, $gender, (int) $r->time_ms,
                        (int) ($this->wa->latestYear($pool) ?? 0), $pool);
            }
            if ($points && (!$best || $points > $best['points'])) {
                $best = ['points' => (int) $points, 'result' => $r];
            }
        }
        return $best;
    }

    /** Podestplätze, Finals, Bestzeiten und Rekorde der Saison, neueste zuerst */
    private function highlights(Collection $results): Collection
    {
        return $results
            ->filter(fn($r) => ($r->placement && $r->placement <= 3) || $r->is_final || $r->is_personal_best || $r->breaks_vereinsrekord)
            ->sortByDesc(fn($r) => [$r->competition?->date?->timestamp ?? 0, -($r->placement ?? 99)])
            ->values()
            ->map(fn($r) => (object) [
                'date'        => $r->competition?->date,
                'competition' => $r->competition?->name,
                'event'       => $r->distance . ' m ' . $r->discipline_label,
                'time'        => SwimmingTime::formatMs((int) $r->time_ms),
                'placement'   => $r->placement && $r->placement <= 3 ? $r->placement : null,
                'final'       => (bool) $r->is_final,
                'pb'          => (bool) $r->is_personal_best,
                'record'      => (bool) $r->breaks_vereinsrekord,
                'leadoff'     => (bool) $r->relay_leadoff,
            ]);
    }
}
