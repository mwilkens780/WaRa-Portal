<?php

namespace App\Services\Ranking;

use App\Models\Competition;
use App\Models\RelayResult;
use Illuminate\Support\Collection;

/**
 * Mannschaftswertung aus Staffeln (DMS-J und ähnliche Mannschaftswettbewerbe).
 *
 * Regeln nach den Allgemeinen Durchführungsbestimmungen des DSV für den DMSJ:
 *  - Wertung je Altersklasse und Geschlecht, je Vereinsmannschaft (1., 2. …)
 *  - Gesamtzeit = Summe der Zeiten aller Staffeln der Altersklasse
 *  - Disqualifizierte Staffel darf einmal nachschwimmen (Wettkampfart N);
 *    gilt dann die Zeit des Nachschwimmens. Ohne gültige Zeit in einer Staffel
 *    (zweite Disqualifikation, abgemeldet, nicht angetreten) fällt die
 *    Mannschaft aus der Gesamtwertung ("ohne Gesamtzeit").
 *  - Bei gleicher Gesamtzeit entscheidet die Lagenstaffel, dann Rücken,
 *    Brust, Schmetterling, Freistil.
 */
class TeamRelayRanking
{
    /** Schwimmreihenfolge im DMSJ und Spaltenfolge der Anzeige */
    const ORDER = ['F', 'B', 'R', 'S', 'L'];
    const TIEBREAK = ['L', 'R', 'B', 'S', 'F'];
    const CLASS_ORDER = ['Jugend E', 'Jugend D', 'Jugend C', 'Jugend B', 'Jugend A', 'Junioren'];

    /** Gilt der Wettkampf als Mannschaftswettbewerb aus Staffeln? */
    public static function applies(Competition $competition): bool
    {
        if ($competition->type === 'dms' || preg_match('/\bDMS/u', (string) $competition->name)) return true;

        // Reiner Staffelwettkampf mit mehreren Lagen je Klasse
        $hasIndividual = $competition->results()->where('relay_leadoff', false)->exists();
        if ($hasIndividual) return false;

        return RelayResult::where('competition_id', $competition->id)
            ->selectRaw('age_group, gender, COUNT(DISTINCT discipline) n')
            ->groupBy('age_group', 'gender')->get()->max('n') >= 3;
    }

    /**
     * @return Collection<int, array{class: string, gender: ?string, disciplines: list<string>, teams: Collection}>
     */
    public function forCompetition(Competition $competition): Collection
    {
        $relays = RelayResult::where('competition_id', $competition->id)
            ->with('members.athlete')
            ->get();

        return $relays->groupBy(fn($r) => ($r->age_group ?? '') . '|' . ($r->gender ?? ''))
            ->map(fn(Collection $classRelays) => $this->rankClass($classRelays))
            ->sortBy(fn($c) => [
                ($i = array_search($c['class'], self::CLASS_ORDER, true)) === false ? 99 : $i,
                $c['class'],
                ['F' => 0, 'M' => 1][$c['gender']] ?? 2,
            ])
            ->values();
    }

    private function rankClass(Collection $relays): array
    {
        $first = $relays->first();
        $disciplines = array_values(array_intersect(self::ORDER, $relays->pluck('discipline')->unique()->all()));

        $teams = $relays->groupBy(fn($r) => $r->club_name . '|' . ($r->team_number ?? 1))
            ->map(function (Collection $teamRelays) use ($disciplines) {
                $legs = [];
                $missing = [];
                foreach ($disciplines as $d) {
                    $forD  = $teamRelays->where('discipline', $d);
                    // Gültige Zeit: Entscheidung, sonst Nachschwimmen
                    $valid = $forD->where('status', 'OK')->filter(fn($r) => $r->time_ms > 0)
                        ->sortBy(fn($r) => $r->round === 'N' ? 1 : 0)->first();
                    $legs[$d] = [
                        'relay'   => $valid ?? $forD->first(),
                        'valid'   => (bool) $valid,
                        'renatation' => $valid?->round === 'N',
                        'status'  => $valid ? null : ($forD->first()?->status ?? 'fehlt'),
                    ];
                    if (!$valid) $missing[] = $d;
                }
                $any = $teamRelays->first();

                return [
                    'club'      => $any->club_name,
                    'team'      => $any->team_number ?? 1,
                    'label'     => $any->team_label,
                    'legs'      => $legs,
                    'total_ms'  => $missing ? null : collect($legs)->sum(fn($l) => $l['relay']->time_ms),
                    'missing'   => $missing,
                    'dq_count'  => $teamRelays->where('status', 'DQ')->count(),
                ];
            })
            ->values();

        // Rangfolge: Gesamtzeit, bei Gleichstand Lagen, Rücken, Brust, Schmetterling, Freistil
        $ranked = $teams->filter(fn($t) => $t['total_ms'] !== null)
            ->sort(function ($a, $b) {
                if ($a['total_ms'] !== $b['total_ms']) return $a['total_ms'] <=> $b['total_ms'];
                foreach (self::TIEBREAK as $d) {
                    $ta = $a['legs'][$d]['relay']?->time_ms ?? PHP_INT_MAX;
                    $tb = $b['legs'][$d]['relay']?->time_ms ?? PHP_INT_MAX;
                    if ($ta !== $tb) return $ta <=> $tb;
                }
                return 0;
            })->values();

        $place = 0; $prev = null;
        $ranked = $ranked->map(function ($t, $i) use (&$place, &$prev) {
            $place = $prev !== null && $prev === $t['total_ms'] ? $place : $i + 1;
            $prev  = $t['total_ms'];
            return $t + ['place' => $place];
        });

        $out = $teams->filter(fn($t) => $t['total_ms'] === null)
            ->sortBy('label')->map(fn($t) => $t + ['place' => null])->values();

        return [
            'class'       => $first->age_group ?: 'Ohne Altersklasse',
            'gender'      => $first->gender,
            'disciplines' => $disciplines,
            'teams'       => $ranked->concat($out)->values(),
        ];
    }
}
