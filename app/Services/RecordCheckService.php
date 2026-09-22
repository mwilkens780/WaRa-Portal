<?php

namespace App\Services;

use App\Models\CompetitionResult;
use App\Models\Record;
use App\Services\TimePlausibility;
use Illuminate\Support\Facades\DB;

class RecordCheckService
{
    public function checkResult(CompetitionResult $result): void
    {
        if ($result->time_ms <= 0) return;
        if (!$result->gender || $result->gender === 'X') return;

        // Ohne bekannte Bahnlaenge keine Rekord- oder Bestenlistenwertung.
        // Bisher wurde stillschweigend Langbahn angenommen - so landeten
        // Kurzbahnergebnisse in Langbahnrekorden, wenn die Bahn fehlte.
        $course = $result->competition?->course;
        if (!$course) return;

        // Ueber diese Strecke unmoegliche Zeit: immer ein Datenfehler, nie ein Rekord
        if (TimePlausibility::isImplausible($result->discipline, (int) $result->distance, (int) $result->time_ms)) {
            return;
        }

        $ageGroup = $result->age_group ?: null;

        $this->checkVr($result, $course);
        $this->checkLr($result, $course, $ageGroup);
        // Bestenlisten werden nicht mehr gespeichert, sondern bei der Anzeige
        // berechnet - siehe BestListService.
    }

    /**
     * Vereinsrekorde gibt es nur in der offenen Wertung: Jede Zeit wird
     * jahrgangsuebergreifend gegen den einen Rekord ihrer Strecke geprueft,
     * unabhaengig von der Altersklasse, in der sie geschwommen wurde.
     * Nur Strecken aus Record::VR_EVENTS (ab 50 m, 100 L nur Kurzbahn).
     */
    private function checkVr(CompetitionResult $result, string $course): void
    {
        if (!Record::isVrEvent($result->discipline, $result->distance, $course)) {
            return;
        }

        $vr = Record::where('type', 'vereinsrekord')
            ->where('discipline', $result->discipline)
            ->where('distance', $result->distance)
            ->where('gender', $result->gender)
            ->whereNull('age_group')
            ->where('course', $course)
            ->first();

        if (!$vr || $result->time_ms < $vr->time_ms) {
            Record::updateOrCreate(
                [
                    'type'       => 'vereinsrekord',
                    'discipline' => $result->discipline,
                    'distance'   => $result->distance,
                    'gender'     => $result->gender,
                    'age_group'  => null,
                    'course'     => $course,
                ],
                [
                    'swimmer_name'          => $result->user->name,
                    'user_id'               => $result->user_id,
                    'time_ms'               => $result->time_ms,
                    'set_date'              => $result->competition->date ?? null,
                    'location'              => $result->competition->location ?? null,
                    'competition_result_id' => $result->id,
                ]
            );

            // Der alte Rekordhalter der Strecke - egal aus welcher Altersklasse
            CompetitionResult::where('breaks_vereinsrekord', true)
                ->where('id', '!=', $result->id)
                ->where('discipline', $result->discipline)
                ->where('distance', $result->distance)
                ->where('gender', $result->gender)
                ->whereHas('competition', fn($q) => $q->where('course', $course))
                ->update(['breaks_vereinsrekord' => false]);

            $result->update(['breaks_vereinsrekord' => true]);
        }
    }

    private function checkLr(CompetitionResult $result, string $course, ?string $ageGroup): void
    {
        $lr = Record::where('type', 'landesrekord')
            ->where('discipline', $result->discipline)
            ->where('distance', $result->distance)
            ->where('gender', $result->gender)
            ->where('age_group', $ageGroup)
            ->where('course', $course)
            ->first();

        if ($lr && $result->time_ms < $lr->time_ms) {
            $result->update(['breaks_landesrekord' => true]);
        }
    }


    public function recheckAll(): void
    {
        DB::transaction(function () {
            CompetitionResult::query()->update([
                'breaks_vereinsrekord' => false,
                'breaks_landesrekord'  => false,
            ]);

            // Bisherige Rekordhalter merken, bevor die Verknuepfungen geloescht
            // werden: Bei Gleichstand bleibt der Rekord beim bisherigen Halter.
            $previousHolder = Record::where('type', 'vereinsrekord')
                ->whereNotNull('competition_result_id')
                ->pluck('competition_result_id', 'id');

            Record::where('type', 'vereinsrekord')->update(['competition_result_id' => null]);

            // Nur Ergebnisse aus Wettkaempfen mit bekannter Bahnlaenge, und
            // keine ueber ihre Strecke unmoeglichen Zeiten
            $results = CompetitionResult::with(['user', 'competition'])
                ->where('time_ms', '>', 0)
                ->whereNotNull('gender')
                ->whereIn('gender', ['M', 'F'])
                ->whereHas('competition', fn($q) => $q->whereIn('course', ['Kurzbahn', 'Langbahn']))
                ->whereRaw('NOT (' . TimePlausibility::sqlCondition('competition_results') . ')')
                ->get();

            // ── Vereinsrekorde: offene Wertung, eine Bestzeit je Strecke ──────
            $vrGroups = $results
                ->filter(fn($r) => Record::isVrEvent($r->discipline, $r->distance, $r->competition->course))
                ->groupBy(fn($r) => implode('§', [
                    $r->discipline,
                    $r->distance,
                    $r->gender,
                    $r->competition->course,
                ]));

            foreach ($vrGroups as $key => $group) {
                // Schnellste Zeit; bei gleicher Zeit die fruehere - wer eine
                // Rekordzeit nur einstellt, uebernimmt den Rekord nicht.
                $best = $group->sort(fn($a, $b) =>
                    [(int) $a->time_ms, $a->competition?->date?->timestamp ?? PHP_INT_MAX, (int) $a->id]
                    <=> [(int) $b->time_ms, $b->competition?->date?->timestamp ?? PHP_INT_MAX, (int) $b->id]
                )->first();
                [$discipline, $distance, $gender, $course] = explode('§', $key, 4);

                $vr = Record::where('type', 'vereinsrekord')
                    ->where('discipline', $discipline)
                    ->where('distance', (int)$distance)
                    ->where('gender', $gender)
                    ->whereNull('age_group')
                    ->where('course', $course)
                    ->first();

                if ($vr) {
                    // Gleichstand: Der Rekord bleibt beim bisherigen Halter. War
                    // das ein Portal-Ergebnis, bekommt es seine Verknuepfung und
                    // Markierung zurueck; ein externer Halter bleibt unveraendert.
                    // (int): je nach DB-Treiber kommen Zahlen als String zurueck
                    if ((int) $best->time_ms === (int) $vr->time_ms) {
                        $holder = $group->firstWhere('id', $previousHolder[$vr->id] ?? null);
                        if ($holder && (int) $holder->time_ms === (int) $vr->time_ms) {
                            $vr->update(['competition_result_id' => $holder->id]);
                            $holder->update(['breaks_vereinsrekord' => true]);
                        }
                    } elseif ((int) $best->time_ms < (int) $vr->time_ms) {
                        $vr->update([
                            'swimmer_name'          => $best->user?->name ?? $vr->swimmer_name,
                            'user_id'               => $best->user_id,
                            'time_ms'               => $best->time_ms,
                            'set_date'              => $best->competition?->date ?? $vr->set_date,
                            'location'              => $best->competition?->location ?? $vr->location,
                            'competition_result_id' => $best->id,
                        ]);
                        $best->update(['breaks_vereinsrekord' => true]);
                    }
                } else {
                    Record::create([
                        'type'                  => 'vereinsrekord',
                        'discipline'            => $discipline,
                        'distance'              => (int)$distance,
                        'gender'                => $gender,
                        'age_group'             => null,
                        'course'                => $course,
                        'swimmer_name'          => $best->user?->name ?? '–',
                        'user_id'               => $best->user_id,
                        'time_ms'               => $best->time_ms,
                        'set_date'              => $best->competition?->date,
                        'location'              => $best->competition?->location,
                        'competition_result_id' => $best->id,
                    ]);
                    $best->update(['breaks_vereinsrekord' => true]);
                }
            }

            // ── Landesrekorde: unveraendert je Altersklasse ───────────────────
            $lrGroups = $results->groupBy(fn($r) => implode('§', [
                $r->discipline,
                $r->distance,
                $r->gender,
                $r->age_group ?? '',
                $r->competition->course,
            ]));

            foreach ($lrGroups as $key => $group) {
                $best = $group->sortBy('time_ms')->first();
                [$discipline, $distance, $gender, $ag, $course] = explode('§', $key, 5);
                $ageGroup = $ag === '' ? null : $ag;

                $lr = Record::where('type', 'landesrekord')
                    ->where('discipline', $discipline)
                    ->where('distance', (int)$distance)
                    ->where('gender', $gender)
                    ->where('age_group', $ageGroup)
                    ->where('course', $course)
                    ->first();

                if ($lr && $best->time_ms < $lr->time_ms) {
                    $best->update(['breaks_landesrekord' => true]);
                }
            }

            // Bestenlisten brauchen keine Neuberechnung mehr - sie entstehen
            // bei der Anzeige aus Ergebnissen und historischen Eintraegen.
        });
    }

}
