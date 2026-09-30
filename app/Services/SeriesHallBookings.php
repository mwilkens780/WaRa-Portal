<?php

namespace App\Services;

use App\Models\HallBooking;
use App\Models\TrainingSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hallenbelegung einer Trainingsserie - an EINER Stelle.
 *
 * Uebergangsloesung bis Serien eigene Datensaetze sind (docs/konzept-trainingsserien.md):
 * Eine Serie hat je Bahn genau EINE wochentliche Belegung. Sie haengt technisch an
 * einer Einheit der Serie ("Anker": die naechste kommende). Vorher legte "Serie
 * bearbeiten" je Einheit eine eigene Belegung an - im Hallenplan lagen dann Dutzende
 * gleiche Belegungen uebereinander und waren nicht mehr zu sehen.
 */
class SeriesHallBookings
{
    /** Einheit, an der die Serienbelegung haengt: naechste kommende, sonst die letzte */
    public function anchor(string $group): ?TrainingSession
    {
        return TrainingSession::where('recurrence_group_id', $group)->where('date', '>=', today())->orderBy('date')->first()
            ?? TrainingSession::where('recurrence_group_id', $group)->orderByDesc('date')->first();
    }

    /** Alle Belegungen, die an irgendeiner Einheit dieser Serie haengen */
    public function bookingsOf(string $group): Collection
    {
        return HallBooking::whereIn('training_session_id', TrainingSession::where('recurrence_group_id', $group)->select('id'))->get();
    }

    /**
     * Andere Belegungen, die mit der Serie kollidieren (ohne die der Serie selbst).
     * Unverknuepfte Belegungen mit exakt gleicher Zeit gelten als "uebernehmbar", nicht als Konflikt.
     *
     * @return array{conflicts: Collection, adoptable: Collection}
     */
    public function check(string $group, array $resourceIds): array
    {
        $anchor = $this->anchor($group);
        if (!$anchor || !$anchor->end_time) return ['conflicts' => collect(), 'adoptable' => collect()];

        [$day, $start, $end] = [$anchor->date->dayOfWeekIso, substr($anchor->start_time, 0, 5), substr($anchor->end_time, 0, 5)];
        $own = $this->bookingsOf($group)->pluck('id');

        $overlapping = HallBooking::with('resource')
            ->whereIn('hall_resource_id', $resourceIds)
            ->where('day_of_week', $day)
            ->where('start_time', '<', $end)->where('end_time', '>', $start)
            ->whereNotIn('id', $own)
            ->get();

        // Gleiche Zeit allein reicht nicht - parallel trainieren oft mehrere Gruppen.
        // Belegung einer anderen Gruppe ist ein echter Konflikt, keine Kopie.
        $adoptable = $overlapping->filter(fn($b) => $b->training_session_id === null
            && substr($b->start_time, 0, 5) === $start && substr($b->end_time ?? '', 0, 5) === $end
            && $this->belongsToSeries($b, $anchor));

        return ['conflicts' => $overlapping->diff($adoptable)->values(), 'adoptable' => $adoptable->values()];
    }

    /**
     * Wer belegt welche Bahn zur Zeit DIESES Termins? Woechentliche Belegungen
     * (ohne die eigene Serie) und Ausnahme-Bahnen anderer Termine am selben Tag.
     * Fuer Ausnahme-Bahnen nur ein Hinweis - speichern geht trotzdem.
     *
     * @return array<int, string[]> hall_resource_id => Belegt-von-Texte
     */
    public function occupancy(TrainingSession $session): array
    {
        if (!$session->end_time) return [];
        [$start, $end] = [substr($session->start_time, 0, 5), substr($session->end_time, 0, 5)];
        $own = $session->recurrence_group_id ? $this->bookingsOf($session->recurrence_group_id)->pluck('id') : $session->hallBookings()->pluck('id');

        $out = [];
        HallBooking::with('trainingGroup:id,name')->where('day_of_week', $session->date->dayOfWeekIso)
            ->where('start_time', '<', $end)->where('end_time', '>', $start)->whereNotIn('id', $own)->get()
            ->each(function ($b) use (&$out) {
                $out[$b->hall_resource_id][] = ($b->trainingGroup?->name ?? $b->label) . ' ' . substr($b->start_time, 0, 5) . '–' . substr($b->end_time, 0, 5);
            });
        TrainingSession::with('exceptionLanes:id')->whereDate('date', $session->date)->where('id', '!=', $session->id)
            ->where('status', '!=', 'cancelled')->where('start_time', '<', $end)->where('end_time', '>', $start)->get()
            ->each(function ($s) use (&$out) {
                foreach ($s->exceptionLanes as $r) $out[$r->id][] = $s->title . ' (Ausnahme) ' . substr($s->start_time, 0, 5);
            });

        return $out;
    }

    /**
     * Gehoert eine (unverknuepfte) Belegung inhaltlich zu dieser Serie?
     * Ja, wenn ihre Gruppe eine Gruppe der Serie ist - oder, ohne Gruppe,
     * wenn die Bezeichnung einem Gruppennamen oder dem Serientitel entspricht.
     */
    public function belongsToSeries(HallBooking $booking, TrainingSession $anchor): bool
    {
        $groups = $anchor->trainingGroups;
        if ($booking->training_group_id) return $groups->contains('id', $booking->training_group_id);
        $names = $groups->pluck('name')->push($anchor->title)->map(fn($n) => mb_strtolower(trim((string) $n)))->filter();
        return $names->contains(mb_strtolower(trim((string) $booking->label)));
    }

    /**
     * Bahnen der Serie setzen: je Bahn genau eine Belegung, am Anker.
     * Passende unverknuepfte Belegungen werden uebernommen statt verdoppelt,
     * doppelte Belegungen der Serie zusammengelegt.
     *
     * @return array{kept: int, adopted: int, created: int, removed: int}
     */
    public function sync(string $group, array $resourceIds, ?int $userId = null): array
    {
        $anchor = $this->anchor($group);
        $stats = ['kept' => 0, 'adopted' => 0, 'created' => 0, 'removed' => 0];
        if (!$anchor) return $stats;

        $anchor->loadMissing('trainingGroups', 'coTrainers');
        $day   = $anchor->date->dayOfWeekIso;
        $start = substr($anchor->start_time, 0, 5);
        $end   = $anchor->end_time ? substr($anchor->end_time, 0, 5) : null;
        $werte = [
            'training_session_id' => $anchor->id,
            'training_series_id'  => \App\Models\TrainingSeries::whereKey($group)->value('id'),
            'day_of_week'         => $day,
            'start_time'          => $start,
            'end_time'            => $end,
            'label'               => $anchor->title,
            'type'                => 'training',
            'training_group_id'   => $anchor->trainingGroups->first()?->id,
            'trainer_id'          => $anchor->coTrainers->first()?->id,
        ];
        $adoptable = $this->check($group, $resourceIds)['adoptable']->groupBy('hall_resource_id');

        DB::transaction(function () use ($group, $resourceIds, $werte, $adoptable, $userId, &$stats) {
            $existing = $this->bookingsOf($group)->groupBy('hall_resource_id');

            foreach ($existing as $resourceId => $list) {
                // Bahn nicht mehr gewuenscht: alle Belegungen der Serie darauf weg
                if (!in_array((int) $resourceId, array_map('intval', $resourceIds), true)) {
                    $stats['removed'] += $list->count();
                    HallBooking::whereIn('id', $list->pluck('id'))->delete();
                    continue;
                }
                // Eine behalten (auf Serienwerte bringen), Duplikate entfernen
                $keep = $list->sortBy('id')->first();
                $keep->update($werte);
                $stats['kept']++;
                // Duplikate: weitere Belegungen der Serie und unverknuepfte mit exakt gleicher Zeit (z. B. aus dem Import)
                $dupes = $list->where('id', '!=', $keep->id)->pluck('id')
                    ->merge(($adoptable->get($resourceId) ?? collect())->pluck('id'));
                $stats['removed'] += $dupes->count();
                HallBooking::whereIn('id', $dupes)->delete();
            }

            foreach ($resourceIds as $resourceId) {
                if (isset($existing[$resourceId])) continue;
                if ($fremd = $adoptable->get($resourceId)?->first()) {
                    $fremd->update($werte);       // vorhandene Belegung verbinden statt verdoppeln
                    $stats['adopted']++;
                } else {
                    HallBooking::create($werte + ['hall_resource_id' => $resourceId, 'created_by_id' => $userId]);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * Einheit wird geloescht oder einzeln verschoben: Serienbelegungen, die an ihr
     * haengen, an die naechste kommende Einheit der Serie umhaengen - statt sie zu
     * loeschen (sonst verschwand die Belegung der ganzen Serie aus dem Hallenplan).
     *
     * @return int Anzahl umgehaengter Belegungen
     */
    public function moveAwayFrom(TrainingSession $session): int
    {
        if (!$session->recurrence_group_id) return 0;
        $next = TrainingSession::where('recurrence_group_id', $session->recurrence_group_id)
            ->where('id', '!=', $session->id)
            ->where('date', '>=', today())
            ->orderBy('date')->first();
        if (!$next) return 0;

        return HallBooking::where('training_session_id', $session->id)->update(['training_session_id' => $next->id]);
    }
}
