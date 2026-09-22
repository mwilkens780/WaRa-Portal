<?php

namespace App\Services;

use App\Models\BestListEntry;
use App\Models\CompetitionResult;
use App\Models\Record;
use App\Services\TimePlausibility;
use Illuminate\Support\Collection;

/**
 * Bestenlisten: die schnellsten 10 Schwimmer je Strecke, Bahn und Geschlecht -
 * jahrgangsuebergreifend und ueber alle Veranstaltungen.
 *
 * Zwei Quellen:
 *  - Wettkampfergebnisse des Portals (nur Strecken der Vereinsrekordliste,
 *    nur Wettkaempfe mit bekannter Bahnlaenge)
 *  - historische und von Hand gepflegte Eintraege aus best_list_entries
 *
 * Je Schwimmer zaehlt nur die beste Zeit. Gleiche Zeiten teilen sich den
 * Platz; die frueher geschwommene Zeit steht oben.
 */
class BestListService
{
    public const TOP_N = 10;

    /**
     * Fertige Listen einer Bahn.
     *
     * @param  int|null $year  nur Leistungen dieses Jahres (Jahresbestenliste)
     * @return array<string, array<string, array>>  [Geschlecht][Disziplin_Distanz] => Zeilen
     */
    public function lists(string $course, ?int $year = null): array
    {
        $rows = $this->portalRows($course, $year)->concat($this->manualRows($course, $year));

        $out = [];
        foreach (['F', 'M'] as $gender) {
            foreach (Record::VR_EVENTS[$course] ?? [] as $discipline => $distances) {
                foreach ($distances as $distance) {
                    $key = $discipline . '_' . $distance;
                    $out[$gender][$key] = $this->rank(
                        $rows->filter(fn($r) => $r['gender'] === $gender
                            && $r['discipline'] === $discipline
                            && $r['distance'] === $distance)
                    );
                }
            }
        }

        return $out;
    }

    /** Jahre, für die es überhaupt Leistungen gibt - für die Jahresauswahl */
    public function availableYears(): Collection
    {
        // Jahr in PHP bilden statt per YEAR() - das gibt es nicht in jeder Datenbank
        $fromResults = \App\Models\Competition::whereNotNull('date')
            ->whereHas('results')
            ->pluck('date')
            ->map(fn($d) => (int) substr((string) $d, 0, 4));

        $fromEntries = BestListEntry::whereNull('competition_result_id')
            ->whereNotNull('set_year')->distinct()->pluck('set_year');

        return $fromResults->concat($fromEntries)->map(fn($y) => (int) $y)
            ->filter()->unique()->sortDesc()->values();
    }

    /**
     * Beste Zeit je Schwimmer, dann Top 10 mit geteilten Plaetzen.
     * Bei Gleichstand auf Platz 10 bleiben alle Gleichplatzierten stehen.
     */
    private function rank(Collection $rows): array
    {
        $best = [];
        foreach ($rows as $row) {
            $key = $row['swimmer_key'];
            $old = $best[$key] ?? null;
            // Schnellere Zeit gewinnt; bei gleicher Zeit die fruehere Leistung
            if (!$old
                || $row['time_ms'] < $old['time_ms']
                || ($row['time_ms'] === $old['time_ms'] && ($row['year'] ?? PHP_INT_MAX) < ($old['year'] ?? PHP_INT_MAX))) {
                $best[$key] = $row;
            }
        }

        $sorted = collect($best)->sort(fn($a, $b) =>
            [$a['time_ms'], $a['year'] ?? PHP_INT_MAX] <=> [$b['time_ms'], $b['year'] ?? PHP_INT_MAX]
        )->values();

        $out = [];
        $rank = 0;
        $lastTime = null;
        foreach ($sorted as $i => $row) {
            if ($row['time_ms'] !== $lastTime) {
                $rank = $i + 1;              // geteilte Plaetze: 1,2,2,4 …
                $lastTime = $row['time_ms'];
            }
            if ($rank > self::TOP_N) break;
            $out[] = ['rank' => $rank] + $row;
        }

        return $out;
    }

    /** Ergebnisse aus dem Portal */
    private function portalRows(string $course, ?int $year): Collection
    {
        $q = CompetitionResult::query()
            ->join('competitions', 'competitions.id', '=', 'competition_results.competition_id')
            ->leftJoin('users', 'users.id', '=', 'competition_results.user_id')
            ->where('competitions.course', $course)
            // Nicht angetreten (DNS) hat keine eigene Spalte, sondern time_ms = 0
            ->where('competition_results.time_ms', '>', 0)
            // Ueber ihre Strecke unmoegliche Zeiten gehoeren in keine Bestenliste
            ->whereRaw('NOT (' . TimePlausibility::sqlCondition('competition_results') . ')')
            ->whereIn('competition_results.gender', ['M', 'F'])
            ->selectRaw('competition_results.id, competition_results.user_id, competition_results.discipline,
                         competition_results.distance, competition_results.gender, competition_results.time_ms,
                         competitions.date as comp_date, competitions.name as comp_name,
                         users.firstname, users.lastname, users.birth_date');

        if ($year) {
            $q->whereYear('competitions.date', $year);
        }

        return $q->get()
            ->filter(fn($r) => Record::isVrEvent($r->discipline, (int) $r->distance, $course))
            ->map(function ($r) {
                $name = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                return [
                    // Immer ueber den Namen buendeln, nie ueber die Benutzer-ID:
                    // historische Eintraege haben keine ID, sollen aber mit den
                    // Portal-Zeiten derselben Person zusammenfallen.
                    'swimmer_key' => $this->nameKey($name !== '' ? $name : 'ergebnis-' . $r->id),
                    'name'        => $name !== '' ? $name : '–',
                    'birth_year'  => $r->birth_date ? (int) substr((string) $r->birth_date, 0, 4) : null,
                    'discipline'  => $r->discipline,
                    'distance'    => (int) $r->distance,
                    'gender'      => $r->gender,
                    'time_ms'     => (int) $r->time_ms,
                    'year'        => $r->comp_date ? (int) substr((string) $r->comp_date, 0, 4) : null,
                    'event'       => $r->comp_name,
                    'source'      => 'portal',
                    'result_id'   => $r->id,
                    'entry_id'    => null,
                ];
            })->values();
    }

    /** Historische und von Hand gepflegte Eintraege */
    private function manualRows(string $course, ?int $year): Collection
    {
        return BestListEntry::whereNull('competition_result_id')
            ->where('course', $course)
            ->when($year, fn($q) => $q->where('set_year', $year))
            ->get()
            ->filter(fn($e) => Record::isVrEvent($e->discipline, (int) $e->distance, $course)
                && !TimePlausibility::isImplausible($e->discipline, (int) $e->distance, (int) $e->time_ms))
            ->map(fn($e) => [
                'swimmer_key' => $this->nameKey($e->swimmer_name),
                'name'        => $e->swimmer_name,
                'birth_year'  => $e->birth_year ? (int) $e->birth_year : null,
                'discipline'  => $e->discipline,
                'distance'    => (int) $e->distance,
                'gender'      => $e->gender,
                'time_ms'     => (int) $e->time_ms,
                'year'        => $e->set_year ? (int) $e->set_year : $e->set_date?->year,
                'event'       => $e->location,
                'source'      => $e->source ?: 'import',
                'result_id'   => null,
                'entry_id'    => $e->id,
            ])->values();
    }

    /**
     * Schluessel zum Zusammenfuehren derselben Person aus beiden Quellen.
     * Reihenfolge der Namensteile egal ("Maike Janssen" = "Janssen, Maike"),
     * Gross-/Kleinschreibung und Umlaute vereinheitlicht.
     */
    private function nameKey(string $name): string
    {
        $n = mb_strtolower(strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));
        $parts = preg_split('/[^a-z0-9]+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($parts);

        return 'n' . implode('_', $parts);
    }
}
