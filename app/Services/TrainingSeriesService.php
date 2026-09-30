<?php

namespace App\Services;

use App\Models\HallBooking;
use App\Models\Holiday;
use App\Models\Season;
use App\Models\TrainingSeries;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aenderungen an einer Trainingsserie (docs/konzept-trainingsserien.md).
 *
 * Grundsaetze: Die Serie ist die Quelle. Aenderungen gelten ab einem Datum,
 * Vergangenes bleibt. Einheiten mit bewusster Abweichung (overridden_fields)
 * behalten ihren Wert. Die Hallenbelegung gehoert der Serie.
 */
class TrainingSeriesService
{
    public function __construct(private SeriesHallBookings $lanes) {}

    /**
     * Stammdaten ab einem Datum aendern, Bahnen setzen.
     *
     * @param array $data  Serienwerte (title, type, day_of_week, start_time, end_time, location, notes,
     *                     max_participants, registration_open, guest_group_id, skip_holidays)
     * @return array{updated: int, moved: int, kept_overrides: int, lanes: array}
     * @throws ValidationException bei Bahnkonflikt ohne $forceLanes
     */
    public function update(TrainingSeries $series, array $data, Carbon $from, array $groupIds, array $trainerIds,
                           array $resourceIds, ?int $userId, bool $forceLanes = false): array
    {
        return DB::transaction(function () use ($series, $data, $from, $groupIds, $trainerIds, $resourceIds, $userId, $forceLanes) {
            $shift = (int) $data['day_of_week'] - $series->day_of_week;
            $series->update($data);
            $series->trainingGroups()->sync($groupIds);
            $series->trainers()->sync($trainerIds);

            $stats = ['updated' => 0, 'moved' => 0, 'kept_overrides' => 0];
            $inherited = collect(TrainingSeries::INHERITED)->mapWithKeys(fn($f) => [$f => $series->$f]);

            foreach ($series->sessions()->where('date', '>=', $from->toDateString())->get() as $session) {
                $over = $session->overridden_fields ?? [];
                $values = $inherited->except($over)->all();
                if ($shift !== 0 && !in_array('date', $over, true)) {
                    $values['date'] = $session->date->copy()->addDays($shift)->toDateString();
                    $stats['moved']++;
                }
                if ($over) $stats['kept_overrides']++;
                $session->update($values);
                if (!in_array('groups', $over, true))   $session->trainingGroups()->sync($groupIds);
                if (!in_array('trainers', $over, true)) $session->coTrainers()->sync($trainerIds);
                $stats['updated']++;
            }

            // Bahnen gegen die NEUEN Zeiten pruefen (Anker hat sie jetzt schon)
            $conflicts = $this->lanes->check($series->id, $resourceIds)['conflicts'];
            if ($conflicts->isNotEmpty() && !$forceLanes) {
                throw ValidationException::withMessages(['hall_resource_ids' => $this->conflictText($conflicts)]);
            }
            $stats['lanes'] = $this->lanes->sync($series->id, $resourceIds, $userId);

            return $stats;
        });
    }

    /**
     * Naechste Saison: NEUE Serie, vorbefuellt aus dieser (Gruppen und Zeiten koennen sich aendern).
     * Die woechentliche Hallenbelegung wandert zur neuen Serie.
     */
    public function planNextSeason(TrainingSeries $old, Carbon $from, Carbon $until, string $recurrence, bool $skipHolidays, ?int $userId): TrainingSeries
    {
        // Start auf den Wochentag der Serie legen
        while ($from->dayOfWeekIso !== $old->day_of_week) $from->addDay();

        $dates = $this->dates($from, $until, $recurrence, $skipHolidays);
        if (!$dates) {
            throw ValidationException::withMessages(['start_date' => 'Im gewählten Zeitraum liegt kein Termin (alle in den Ferien?).']);
        }

        return DB::transaction(function () use ($old, $dates, $until, $recurrence, $skipHolidays, $userId) {
            $new = TrainingSeries::create(array_merge(
                $old->only(['title', 'type', 'day_of_week', 'start_time', 'end_time', 'location', 'notes', 'max_participants', 'registration_open', 'guest_group_id']),
                [
                    'id'              => (string) Str::uuid(),
                    'season_id'       => Season::forDate($dates[0])?->id,
                    'recurrence_type' => $recurrence,
                    'valid_from'      => $dates[0],
                    'valid_until'     => $until,
                    'skip_holidays'   => $skipHolidays,
                    'created_by_id'   => $userId,
                ]
            ));
            $groupIds   = $old->trainingGroups()->pluck('training_groups.id');
            $trainerIds = $old->trainers()->pluck('users.id');
            $new->trainingGroups()->sync($groupIds);
            $new->trainers()->sync($trainerIds);

            $this->createSessions($new, $dates, $groupIds->all(), $trainerIds->all());

            // Belegung zur neuen Serie: der Hallenplan kennt nur eine Belegung je Zeitfenster
            $resources = $this->lanes->bookingsOf($old->id)->pluck('hall_resource_id')->unique()->values()->all();
            if ($resources) {
                $this->lanes->bookingsOf($old->id)->each(fn($b) => $b->update([
                    'training_session_id' => $new->sessions()->value('id'),
                    'training_series_id'  => $new->id,
                ]));
                $this->lanes->sync($new->id, $resources, $userId);
            }

            return $new;
        });
    }

    /** Einheiten zu einer Serie anlegen (Werte aus der Serie) */
    public function createSessions(TrainingSeries $series, array $dates, array $groupIds, array $trainerIds): int
    {
        foreach ($dates as $date) {
            $session = TrainingSession::create([
                'title'               => $series->title,
                'date'                => $date->toDateString(),
                'start_time'          => $series->start_time,
                'end_time'            => $series->end_time,
                'location'            => $series->location,
                'type'                => $series->type,
                'notes'               => $series->notes,
                'max_participants'    => $series->max_participants,
                'registration_open'   => $series->registration_open,
                'guest_group_id'      => $series->guest_group_id,
                'recurrence_type'     => $series->recurrence_type,
                'recurrence_until'    => $series->valid_until?->toDateString(),
                'recurrence_group_id' => $series->id,
            ]);
            $session->trainingGroups()->sync($groupIds);
            $session->coTrainers()->sync($trainerIds);
        }

        return count($dates);
    }

    /** @return Carbon[] Termine im Rhythmus, auf Wunsch ohne Schulferien (Schleswig-Holstein) */
    public function dates(Carbon $from, Carbon $until, string $recurrence, bool $skipHolidays): array
    {
        $holidays = $skipHolidays ? Holiday::intersecting($from, $until) : collect();
        $dates = [];
        for ($d = $from->copy(); $d->lte($until); ) {
            if (!$holidays->contains(fn($h) => $h->containsDate($d))) $dates[] = $d->copy();
            match ($recurrence) {
                'biweekly' => $d->addWeeks(2),
                'monthly'  => $d->addMonth(),
                default    => $d->addWeek(),
            };
        }

        return $dates;
    }

    /**
     * Einheiten einer Serie werden geloescht (ab Datum oder alle): Hallenbelegung
     * an eine verbleibende kommende Einheit haengen - oder mit loeschen, wenn keine bleibt.
     */
    public function releaseBookings(string $group, Collection $deletedIds): void
    {
        $bookings = HallBooking::where(fn($q) => $q->where('training_series_id', $group)
            ->orWhereIn('training_session_id', TrainingSession::where('recurrence_group_id', $group)->select('id')))->get();
        if ($bookings->isEmpty()) return;

        $next = TrainingSession::where('recurrence_group_id', $group)->whereNotIn('id', $deletedIds)
            ->where('date', '>=', today())->orderBy('date')->first();

        $next
            ? HallBooking::whereIn('id', $bookings->pluck('id'))->whereIn('training_session_id', $deletedIds)->update(['training_session_id' => $next->id])
            : HallBooking::whereIn('id', $bookings->pluck('id'))->delete();
    }

    public function conflictText(Collection $conflicts): string
    {
        return 'Belegt: ' . $conflicts->map(fn($b) => $b->resource?->name . ' – ' . ($b->trainingGroup?->name ?? $b->label)
            . ' (' . substr($b->start_time, 0, 5) . '–' . substr($b->end_time, 0, 5) . ')')->implode('; ');
    }
}
