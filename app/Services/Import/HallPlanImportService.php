<?php

namespace App\Services\Import;

use App\Models\Holiday;
use App\Models\HallBooking;
use App\Models\HallResource;
use App\Models\Season;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legt aus den abgeglichenen Eintraegen Hallenbelegungen und Trainingsserien an.
 *
 * Zwei Vorgaben bestimmen das Verhalten:
 *
 *  - Es wird ausschliesslich ergaenzt, was komplett fehlt. Ueberschneidet sich
 *    ein Eintrag zeitlich mit einer bestehenden Belegung derselben Ressource am
 *    selben Wochentag, wird er uebersprungen und gemeldet. Bestehendes wird nie
 *    veraendert oder geloescht.
 *  - Eine Trainingsserie entsteht nur, wenn Gruppe UND Trainer zugeordnet sind.
 *
 * Belegungen werden je Zeitfenster EINMAL angelegt, nicht je Einheit: der
 * Hallenbelegungsplan ist eine Wochenansicht und gruppiert lediglich nach
 * Wochentag. Eine Belegung je Einheit wuerde denselben Slot dutzendfach zeigen.
 */
class HallPlanImportService
{
    /** Kategorie → training_sessions.type */
    private const SESSION_TYPES = [
        'leistungssport' => 'technik',
        'breitensport'   => 'technik',
        'masters'        => 'ausdauer',
        'triathlon'      => 'ausdauer',
        'nachwuchs'      => 'technik',
        'kraftraum'      => 'krafttraining',
    ];

    /**
     * Ermittelt je Eintrag, ob eine bestehende Belegung im Weg ist.
     *
     * @param  list<array> $entries
     * @return list<array> Eintraege mit 'resource_id' und 'conflict'
     */
    public function analyse(array $entries): array
    {
        $resources = HallResource::pluck('id', 'name');
        $existing  = HallBooking::with('resource')->get();

        foreach ($entries as &$entry) {
            $resourceId = $resources[$entry['resource']] ?? null;
            $entry['resource_id'] = $resourceId;

            if (!$resourceId) {
                $entry['conflict'] = ['reason' => 'Ressource "' . $entry['resource'] . '" existiert nicht.'];
                continue;
            }

            $clash = $existing->first(fn($b) =>
                (int) $b->hall_resource_id === (int) $resourceId
                && (int) $b->day_of_week === (int) $entry['day']
                && substr($b->start_time, 0, 5) < $entry['end']
                && substr($b->end_time, 0, 5)   > $entry['start']
            );

            $entry['conflict'] = $clash ? [
                'reason' => 'Belegung vorhanden',
                'label'  => $clash->label,
                'time'   => substr($clash->start_time, 0, 5) . '–' . substr($clash->end_time, 0, 5),
            ] : null;
        }

        return $entries;
    }

    /**
     * Fuehrt den Import aus.
     *
     * @param  list<array> $entries  bereits abgeglichen und analysiert
     * @param  bool        $dryRun   true = nur zaehlen, nichts schreiben
     * @return array{bookings:int, sessions:int, skipped:int, details:list<string>}
     */
    public function import(array $entries, int $userId, bool $dryRun = false): array
    {
        $season = Season::current();
        if (!$season) {
            return ['bookings' => 0, 'sessions' => 0, 'skipped' => count($entries),
                    'details' => ['Keine aktuelle Saison gefunden – Import abgebrochen.']];
        }

        $from = $season->start_date->copy();
        $to   = $season->end_date->copy();

        // Ferien einmal laden statt je Serie
        $holidays  = Holiday::intersecting($from, $to);
        $inHoliday = fn(Carbon $d) => $holidays->contains(fn($h) => $h->containsDate($d));

        $bookings = $sessions = $skipped = 0;
        $details  = [];

        foreach ($entries as $entry) {
            if (!empty($entry['conflict'])) {
                $skipped++;
                $details[] = sprintf('%s %s %s–%s: %s',
                    $entry['day_name'], $entry['resource'], $entry['start'], $entry['end'],
                    $entry['conflict']['reason'] ?? 'übersprungen');
                continue;
            }
            if (empty($entry['resource_id'])) { $skipped++; continue; }

            if ($dryRun) {
                $bookings++;
                if (!empty($entry['session_ready'])) {
                    $sessions += count($this->seriesDates($entry['day'], $from, $to, $inHoliday));
                }
                continue;
            }

            DB::transaction(function () use ($entry, $userId, $from, $to, $inHoliday, &$bookings, &$sessions) {
                $groupId   = $entry['group_ids'][0]   ?? null;
                $trainerId = $entry['trainer_ids'][0] ?? null;

                HallBooking::create([
                    'hall_resource_id'  => $entry['resource_id'],
                    'day_of_week'       => $entry['day'],
                    'start_time'        => $entry['start'],
                    'end_time'          => $entry['end'],
                    'label'             => $entry['group_raw'] ?: $entry['category_label'],
                    'type'              => $entry['booking_type'],
                    'training_group_id' => $groupId,
                    'trainer_id'        => $trainerId,
                    'notes'             => 'Aus Hallenbelegungsplan importiert',
                    'created_by_id'     => $userId,
                ]);
                $bookings++;

                if (empty($entry['session_ready'])) return;

                $dates   = $this->seriesDates($entry['day'], $from, $to, $inHoliday);
                $groupUuid = (string) Str::uuid();
                $title   = $entry['group_raw'] ?: $entry['category_label'];

                foreach ($dates as $date) {
                    $session = new TrainingSession([
                        'title'               => $title,
                        'date'                => $date->format('Y-m-d'),
                        'start_time'          => $entry['start'],
                        'end_time'            => $entry['end'],
                        'type'                => self::SESSION_TYPES[$entry['category']] ?? 'technik',
                        'recurrence_type'     => 'weekly',
                        'recurrence_group_id' => $groupUuid,
                        'recurrence_until'    => $to->format('Y-m-d'),
                        'notes'               => 'Aus Hallenbelegungsplan importiert',
                    ]);

                    $session->save();

                    // Trainer haengen ausschliesslich am Pivot: die Spalte
                    // trainer_id gibt es in training_sessions seit Juni 2026
                    // nicht mehr (Migration 000026). Wurde sie hier gesetzt,
                    // brach der Insert mit "Unknown column 'trainer_id'" ab.
                    //
                    // Alle Gruppen und alle Trainer der Zeile - bei "LG/WG" oder
                    // zwei Trainern fehlte sonst die zweite Gruppe bzw. der zweite
                    // Trainer hatte keinen Zugriff auf die Einheit.
                    $session->trainingGroups()->sync(array_values(array_unique($entry['group_ids'] ?? [])));
                    $session->coTrainers()->sync(array_values(array_unique($entry['trainer_ids'] ?? [])));
                    $sessions++;
                }
            });
        }

        return compact('bookings', 'sessions', 'skipped', 'details');
    }

    /**
     * Wochentermine im Saisonzeitraum, Ferien ausgespart – wie bei manuell
     * angelegten Serien.
     *
     * @return list<Carbon>
     */
    private function seriesDates(int $dayOfWeek, Carbon $from, Carbon $to, callable $inHoliday): array
    {
        $cursor = $from->copy();
        while ($cursor->dayOfWeekIso !== $dayOfWeek) $cursor->addDay();

        $dates = [];
        while ($cursor->lte($to)) {
            if (!$inHoliday($cursor)) $dates[] = $cursor->copy();
            $cursor->addWeek();
        }
        return $dates;
    }
}
