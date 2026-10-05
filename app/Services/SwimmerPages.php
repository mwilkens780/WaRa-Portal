<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\Record;
use App\Models\Season;
use App\Models\SwimmingTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Daten fuer die Seiten "Bestzeiten" und "Wettkaempfe" eines Schwimmers.
 *
 * Schwimmer sehen sich selbst, Eltern ein Kind - mit denselben Seiten. Frueher
 * hatten Eltern eigene, vereinfachte Kopien, die der Schwimmersicht
 * hinterherliefen (keine Saisonauswahl, keine Wettkampfergebnisse unter
 * "Zeiten", offene Anmeldungen vergangener Wettkaempfe als "Ausstehend").
 * Wer hier etwas aendert, aendert es fuer beide.
 */
class SwimmerPages
{
    /** @return array Variablen fuer die View swimmer.my-times */
    public function times(User $swimmer): array
    {
        $filter       = request('filter', 'all');
        $courseFilter = request('course', 'all'); // 'all', 'LB', 'KB'
        $yearVal      = (int) request('year', now()->year);
        $seasonId     = (int) request('season_id', 0);
        $seasons      = Season::orderByDesc('start_date')->get();

        $filterLabel     = 'Alle Zeiten';
        $seasonDateRange = null;

        if ($filter === 'year') {
            $filterLabel = (string) $yearVal;
        } elseif ($filter === 'season') {
            $season = $seasons->firstWhere('id', $seasonId) ?? Season::current();
            if ($season) {
                $filterLabel     = 'Saison ' . $season->name;
                $seasonId        = $season->id;
                $seasonDateRange = [$season->start_date, $season->end_date];
            }
        }

        // Training times (filtered) — Bahnlänge unbekannt, zählen als Langbahn
        $trainQuery = SwimmingTime::where('user_id', $swimmer->id)->with('trainingSession');
        if ($filter === 'year')   $trainQuery->whereYear('created_at', $yearVal);
        elseif ($seasonDateRange) $trainQuery->whereBetween('created_at', $seasonDateRange);
        $trainTimes = $trainQuery->get();

        // Competition results (filtered by competition date)
        $compQuery = CompetitionResult::where('user_id', $swimmer->id)->whereNull('exercise')->where('time_ms', '>', 0)
            ->with('competition:id,date,name,location,course');
        if ($filter === 'year')
            $compQuery->whereHas('competition', fn($q) => $q->whereYear('date', $yearVal));
        elseif ($seasonDateRange)
            $compQuery->whereHas('competition', fn($q) => $q->whereBetween('date', $seasonDateRange));
        $compResults = $compQuery->get();

        $discLabels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
        $discOrder  = ['F' => 0, 'B' => 1, 'R' => 2, 'S' => 3, 'L' => 4];
        $bestsByKey = [];

        // Trainingszeiten: Bahnlänge unbekannt → Langbahn-Bucket
        foreach ($trainTimes as $t) {
            $key = $t->discipline . '_' . $t->distance . '_LB';
            if (!isset($bestsByKey[$key]) || $t->time_ms < $bestsByKey[$key]->ms) {
                $bestsByKey[$key] = (object)[
                    'discipline'       => $t->discipline,
                    'discipline_label' => $discLabels[$t->discipline] ?? $t->discipline,
                    'discipline_order' => $discOrder[$t->discipline] ?? 99,
                    'distance'         => $t->distance,
                    'course'           => 'LB',
                    'course_label'     => null,
                    'ms'               => $t->time_ms,
                    'formatted'        => SwimmingTime::formatMs($t->time_ms),
                    'source'           => 'training',
                    'date'             => $t->trainingSession?->date ?? $t->created_at,
                    'label'            => $t->trainingSession?->title ?? 'Training',
                    // Ort der Trainingseinheit, damit auch hier "wann und wo" steht
                    'location'         => $t->trainingSession?->location,
                ];
            }
        }

        // Wettkampfergebnisse: Bahnlänge aus competition.course
        foreach ($compResults as $r) {
            $courseKey   = $r->competition?->course === 'Kurzbahn' ? 'KB' : 'LB';
            $courseLabel = $r->competition?->course; // 'Langbahn', 'Kurzbahn', null
            $key         = $r->discipline . '_' . $r->distance . '_' . $courseKey;

            if (!isset($bestsByKey[$key]) || $r->time_ms < $bestsByKey[$key]->ms) {
                $bestsByKey[$key] = (object)[
                    'discipline'       => $r->discipline,
                    'discipline_label' => $discLabels[$r->discipline] ?? $r->discipline,
                    'discipline_order' => $discOrder[$r->discipline] ?? 99,
                    'distance'         => $r->distance,
                    'course'           => $courseKey,
                    'course_label'     => $courseLabel,
                    'ms'               => $r->time_ms,
                    'formatted'        => SwimmingTime::formatMs($r->time_ms),
                    'source'           => 'competition',
                    'date'             => $r->competition?->date,
                    'label'            => $r->competition?->name ?? 'Wettkampf',
                    'location'         => $r->competition?->location,
                ];
            }
        }

        // Rudolph-Punkte (zuschaltbar): nur Langbahn-Wettkampfzeiten, Alter nach
        // Jahrgang. Gilt für die beste Langbahn-Wettkampfzeit der Strecke – ist die
        // angezeigte Bestzeit eine Trainingszeit (Bahn unbekannt), steht die
        // bewertete Wettkampfzeit mit dabei.
        $lbCompBest = [];
        foreach ($compResults as $r) {
            if ($r->competition?->course !== 'Langbahn') continue;
            $k = $r->discipline . '_' . $r->distance . '_LB';
            if (!isset($lbCompBest[$k]) || $r->time_ms < $lbCompBest[$k]->time_ms) $lbCompBest[$k] = $r;
        }
        foreach ($bestsByKey as $k => $b) {
            $r = $lbCompBest[$k] ?? null;
            $b->rudolph = $r && $r->competition?->date ? \App\Support\RudolphTable::score(
                $swimmer->gender, $swimmer->birth_date?->year, $r->competition->date->year,
                $r->discipline, (int) $r->distance, (int) $r->time_ms
            ) : null;
            if ($b->rudolph && $r->time_ms !== $b->ms) {
                $b->rudolph->time = SwimmingTime::formatMs($r->time_ms);
            }
        }

        $bests = collect($bestsByKey)
            ->sortBy(fn($b) => sprintf('%d_%05d_%s', $b->discipline_order, $b->distance, $b->course))
            ->values();

        // Bahnfilter anwenden
        if ($courseFilter !== 'all') {
            $bests = $bests->filter(fn($b) => $b->course === $courseFilter)->values();
        }

        $bestsByDisc = $bests->groupBy('discipline');

        // Jahre mit Trainingszeiten ODER Wettkampfergebnissen (vorher nur Training)
        $availableYears = SwimmingTime::where('user_id', $swimmer->id)
            ->selectRaw('YEAR(created_at) as y')->distinct()->pluck('y')
            ->merge(Competition::whereHas('results', fn($q) => $q->where('user_id', $swimmer->id))
                ->selectRaw('YEAR(date) as y')->distinct()->pluck('y'))
            ->unique()->sortDesc()->values();

        return compact(
            'filter', 'filterLabel', 'courseFilter', 'seasons', 'seasonId', 'yearVal', 'availableYears',
            'bests', 'bestsByDisc'
        );
    }

    /** @return array Variablen fuer die View swimmer.my-competitions */
    public function competitions(User $swimmer): array
    {
        $swimmerGroupIds = $swimmer->trainingGroups()->pluck('training_groups.id');

        // Wettkaempfe der eigenen Gruppen, eingeladene - und solche, bei denen
        // eigene Ergebnisse vorliegen. Letzteres fehlte: Importierte Wettkaempfe
        // kommen ohne Gruppenzuweisung, und wer die Gruppe wechselt, verliert
        // sonst seine abgeschlossenen Wettkaempfe aus dieser Liste.
        $allComps = Competition::where(function ($q) use ($swimmerGroupIds, $swimmer) {
                if ($swimmerGroupIds->isNotEmpty()) {
                    $q->whereHas('trainingGroups', fn($inner) =>
                        $inner->whereIn('training_groups.id', $swimmerGroupIds)
                    );
                }
                $q->orWhereHas('signupRequest.responses', fn($inner) =>
                    $inner->where('user_id', $swimmer->id)
                );
                $q->orWhereHas('results', fn($inner) => $inner->where('user_id', $swimmer->id));
            })
            ->with([
                'signupRequest' => fn($q) => $q->with([
                    'responses' => fn($q) => $q->where('user_id', $swimmer->id),
                ]),
                'entries'  => fn($q) => $q->where('user_id', $swimmer->id),
                'events',
            ])
            ->orderByDesc('date')
            ->get();

        // Load ALL results for accurate PB/SB detection across all competitions.
        // discrepancies = offene Widersprüche zwischen den Importquellen; sie
        // werden in der Ergebnisliste rot markiert.
        $raw = CompetitionResult::with(['competition', 'discrepancies'])
            ->where('user_id', $swimmer->id)
            ->get();

        $allTimeBests = [];
        $yearBests    = [];
        $seasonBests  = [];

        foreach ($raw as $result) {
            if (!$result->time_ms || $result->time_ms <= 0) continue;
            // Übungsformen (Beine, Kicks …) nur untereinander vergleichen
            $key  = $result->discipline . '_' . $result->distance . '_' . $result->exercise;
            $ms   = $result->time_ms;
            $date = $result->competition?->date;

            $allTimeBests[$key] = isset($allTimeBests[$key]) ? min($allTimeBests[$key], $ms) : $ms;
            if ($date) {
                $yr = $date->year;
                $sk = $this->seasonKey($date);
                $yearBests[$yr][$key]   = isset($yearBests[$yr][$key])   ? min($yearBests[$yr][$key], $ms)   : $ms;
                $seasonBests[$sk][$key] = isset($seasonBests[$sk][$key]) ? min($seasonBests[$sk][$key], $ms) : $ms;
            }
        }

        $resultAgeGroupMap = $raw->keyBy('id')->map(fn($r) => $r->age_group);
        $allRecords = Record::all()->groupBy(
            fn($r) => $r->type . '|' . $r->discipline . '|' . $r->distance . '|' . $r->gender . '|' . ($r->age_group ?? '') . '|' . $r->course
        )->map(fn($recs) => $recs->min('time_ms'));

        $allSwims = CompetitionResultGrouper::forSwimmer($raw);
        $allSwims = $allSwims->map(function ($swim) use ($allTimeBests, $yearBests, $seasonBests, $allRecords, $resultAgeGroupMap) {
            if ($swim->is_dns || !$swim->time_ms) {
                $swim->pb_badge       = null;
                $swim->beaten_records = [];
                return $swim;
            }
            $key  = $swim->discipline . '_' . $swim->distance . '_' . $swim->exercise;
            $date = $swim->competition?->date;
            $isBestEver   = $swim->time_ms === ($allTimeBests[$key] ?? PHP_INT_MAX);
            $isBestYear   = $date && $swim->time_ms === ($yearBests[$date->year][$key] ?? PHP_INT_MAX);
            $isBestSeason = $date && $swim->time_ms === ($seasonBests[$this->seasonKey($date)][$key] ?? PHP_INT_MAX);
            $swim->pb_badge = match(true) {
                $isBestEver   => 'PB',
                $isBestYear   => 'JB',
                $isBestSeason => 'SB',
                default       => null,
            };
            // Ohne bekannte Bahn keine Rekord-Badges - lieber keine als falsche
            $course = $swim->competition?->course;
            if (!$course || $swim->exercise) {
                $swim->beaten_records = [];
                return $swim;
            }
            $gender    = $swim->gender ?? '';
            $ageGroups = collect($swim->result_ids)->map(fn($id) => $resultAgeGroupMap->get($id))->unique()->values();
            $beatenRecords = [];
            foreach (['vereinsrekord', 'landesrekord'] as $type) {
                // Vereinsrekorde gibt es nur offen (ohne Altersklasse),
                // Landesrekorde je Altersklasse
                $groupsToCheck = $type === 'vereinsrekord' ? collect([null]) : $ageGroups;
                foreach ($groupsToCheck as $ag) {
                    $rKey     = $type . '|' . $swim->discipline . '|' . $swim->distance . '|' . $gender . '|' . ($ag ?? '') . '|' . $course;
                    $recordMs = $allRecords->get($rKey);
                    if ($recordMs !== null && $swim->time_ms <= $recordMs) {
                        $badge = $type === 'vereinsrekord' ? 'VR' : 'LR';
                        if (!in_array($badge, $beatenRecords)) $beatenRecords[] = $badge;
                    }
                }
            }
            $swim->beaten_records = $beatenRecords;
            return $swim;
        });

        $grouped = $allSwims->groupBy('competition_id');

        foreach ($allComps as $comp) {
            $comp->processedResults = $grouped->get($comp->id, collect());
        }

        $perPage   = 10;
        $page      = (int) request('page', 1);
        // Sprung aus dem Kalender (?wettkampf=ID): passende Seite waehlen
        $focusId   = (int) request('wettkampf');
        if ($focusId && !request()->has('page')) {
            $index = $allComps->search(fn($c) => $c->id === $focusId);
            if ($index !== false) {
                $page = intdiv($index, $perPage) + 1;
            }
        }
        $pageItems = $allComps->forPage($page, $perPage)->values();

        $competitions = new LengthAwarePaginator($pageItems, $allComps->count(), $perPage, $page, [
            'path' => request()->url(),
        ]);

        return compact('competitions', 'focusId');
    }

    private function seasonKey(Carbon $date): string
    {
        $month = $date->month;
        $year  = $date->year;
        if ($month >= 4 && $month <= 9) return 'S_' . $year;
        return 'W_' . ($month >= 10 ? $year : $year - 1);
    }
}
