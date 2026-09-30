<?php

namespace App\Services\Import;

use App\Models\HallBooking;
use App\Models\HallResource;
use App\Models\Season;
use App\Models\TrainingSeries;
use App\Services\SeriesHallBookings;
use App\Services\TrainingSeriesService;
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
     * Serien: Zeilen mit gleichem Wochentag, gleicher Zeit und gleichen Gruppen
     * sind EIN Training auf mehreren Bahnen - also eine Serie mit mehreren
     * Belegungen (vorher entstand je Bahn eine eigene Serie). Gibt es fuer die
     * Gruppe in diesem Zeitfenster schon eine laufende Serie, haengt die Belegung
     * daran statt eine zweite anzulegen. Jede Belegung ist sofort mit ihrer
     * Serie verbunden (docs/konzept-trainingsserien.md, Entscheidung 9).
     *
     * @param  list<array> $entries  bereits abgeglichen und analysiert
     * @param  bool        $dryRun   true = nur zaehlen, nichts schreiben
     * @return array{bookings:int, sessions:int, series:int, linked:int, skipped:int, details:list<string>}
     */
    public function import(array $entries, int $userId, bool $dryRun = false): array
    {
        $season = Season::current();
        if (!$season) {
            return ['bookings' => 0, 'sessions' => 0, 'series' => 0, 'linked' => 0, 'skipped' => count($entries),
                    'details' => ['Keine aktuelle Saison gefunden – Import abgebrochen.']];
        }

        $from = $season->start_date->copy();
        $to   = $season->end_date->copy();

        $bookings = $sessions = $series = $linked = $skipped = 0;
        $details  = [];
        $slots    = [];   // Serien-Zeitfenster: Tag|Beginn|Ende|Gruppen => Zeilen

        foreach ($entries as $entry) {
            if (!empty($entry['conflict'])) {
                $skipped++;
                $details[] = sprintf('%s %s %s–%s: %s',
                    $entry['day_name'], $entry['resource'], $entry['start'], $entry['end'],
                    $entry['conflict']['reason'] ?? 'übersprungen');
                continue;
            }
            if (empty($entry['resource_id'])) { $skipped++; continue; }

            if (!empty($entry['session_ready'])) {
                $groups = array_values(array_unique(array_map('intval', $entry['group_ids'] ?? [])));
                sort($groups);
                $slots[implode('|', [$entry['day'], $entry['start'], $entry['end'], implode(',', $groups)])][] = $entry;
                continue;
            }

            // Belegung ohne Serie (Kurs, Schule, fremder Verein ...)
            $bookings++;
            if (!$dryRun) HallBooking::create($this->bookingValues($entry, $userId));
        }

        foreach ($slots as $rows) {
            $first    = $rows[0];
            $groupIds = array_values(array_unique(array_merge(...array_map(fn($r) => $r['group_ids'] ?? [], $rows))));
            $existing = $this->runningSeries((int) $first['day'], $first['start'], $first['end'], $groupIds, $from);
            $bookings += count($rows);

            if ($existing) {
                $linked++;
                if ($dryRun) continue;
                DB::transaction(function () use ($rows, $existing, $userId) {
                    $anchor = $this->lanes()->anchor($existing->id);
                    foreach ($rows as $row) {
                        HallBooking::create($this->bookingValues($row, $userId) + [
                            'training_series_id' => $existing->id, 'training_session_id' => $anchor?->id,
                        ]);
                    }
                });
                continue;
            }

            $dates = $this->seriesService()->dates($this->firstOn((int) $first['day'], $from), $to, 'weekly', true);
            $series++;
            $sessions += count($dates);
            if ($dryRun || !$dates) continue;

            DB::transaction(function () use ($rows, $first, $groupIds, $dates, $from, $to, $season, $userId) {
                $trainerIds = array_values(array_unique(array_merge(...array_map(fn($r) => $r['trainer_ids'] ?? [], $rows))));
                $new = TrainingSeries::create([
                    'id'              => (string) Str::uuid(),
                    'season_id'       => $season->id,
                    'title'           => $first['group_raw'] ?: $first['category_label'],
                    'type'            => self::SESSION_TYPES[$first['category']] ?? 'technik',
                    'day_of_week'     => $first['day'],
                    'start_time'      => $first['start'],
                    'end_time'        => $first['end'],
                    'location'        => null,
                    'recurrence_type' => 'weekly',
                    'valid_from'      => $dates[0],
                    'valid_until'     => $to,
                    'skip_holidays'   => true,
                    'notes'           => 'Aus Hallenbelegungsplan importiert',
                    'created_by_id'   => $userId,
                ]);
                $new->trainingGroups()->sync($groupIds);
                $new->trainers()->sync($trainerIds);
                $this->seriesService()->createSessions($new, $dates, $groupIds, $trainerIds);

                $anchor = $this->lanes()->anchor($new->id);
                foreach ($rows as $row) {
                    HallBooking::create($this->bookingValues($row, $userId) + [
                        'training_series_id' => $new->id, 'training_session_id' => $anchor?->id,
                    ]);
                }
            });
        }

        return compact('bookings', 'sessions', 'series', 'linked', 'skipped', 'details');
    }

    /** Laufende Serie derselben Gruppe im selben Zeitfenster (z. B. schon von Hand angelegt) */
    private function runningSeries(int $day, string $start, string $end, array $groupIds, Carbon $from): ?TrainingSeries
    {
        return TrainingSeries::where('day_of_week', $day)
            ->where('start_time', $start)->where('end_time', $end)
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $from))
            ->whereHas('trainingGroups', fn($q) => $q->whereIn('training_groups.id', $groupIds))
            ->first();
    }

    private function bookingValues(array $entry, int $userId): array
    {
        return [
            'hall_resource_id'  => $entry['resource_id'],
            'day_of_week'       => $entry['day'],
            'start_time'        => $entry['start'],
            'end_time'          => $entry['end'],
            'label'             => $entry['group_raw'] ?: $entry['category_label'],
            'type'              => $entry['booking_type'],
            'training_group_id' => $entry['group_ids'][0] ?? null,
            'trainer_id'        => $entry['trainer_ids'][0] ?? null,
            'notes'             => 'Aus Hallenbelegungsplan importiert',
            'created_by_id'     => $userId,
        ];
    }

    private function firstOn(int $dayOfWeek, Carbon $from): Carbon
    {
        $d = $from->copy();
        while ($d->dayOfWeekIso !== $dayOfWeek) $d->addDay();
        return $d;
    }

    private function seriesService(): TrainingSeriesService
    {
        return app(TrainingSeriesService::class);
    }

    private function lanes(): SeriesHallBookings
    {
        return app(SeriesHallBookings::class);
    }
}
