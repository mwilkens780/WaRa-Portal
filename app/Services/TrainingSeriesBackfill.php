<?php

namespace App\Services;

use App\Models\HallBooking;
use App\Models\Holiday;
use App\Models\Season;
use App\Models\TrainingSeries;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Uebernahme des Bestands in training_series (docs/konzept-trainingsserien.md, Schritt 3).
 *
 * Je recurrence_group_id entsteht eine Serie. Serienwerte = naechste kommende Einheit
 * (sonst die letzte). Kommende Einheiten, die davon abweichen, bekommen ihre
 * Abweichungen in overridden_fields. Hallenbelegungen der Serie werden an die Serie
 * gehaengt; unverknuepfte Belegungen mit gleicher Zeit UND passender Gruppe werden
 * verbunden, identische Kopien zusammengelegt.
 *
 * Probelauf: alles laeuft in einer Transaktion, die am Ende zurueckgerollt wird.
 * Vorhandene Serien bleiben unangetastet (Befehl kann wiederholt werden).
 */
class TrainingSeriesBackfill
{
    private const COMPARE = ['title', 'type', 'start_time', 'end_time', 'location'];

    public function __construct(private SeriesHallBookings $lanes) {}

    /** @return array{rows: array<int, array<string, mixed>>, totals: array<string, int>, applied: bool} */
    public function run(bool $apply = false): array
    {
        $rows   = [];
        $totals = ['serien' => 0, 'vorhanden' => 0, 'abweichungen' => 0, 'belegungen' => 0, 'verbunden' => 0, 'entfernt' => 0];
        $userId = User::where('role', 'admin')->orderBy('id')->value('id');

        DB::beginTransaction();
        try {
            $ids = TrainingSession::whereNotNull('recurrence_group_id')->distinct()->pluck('recurrence_group_id');
            foreach ($ids as $id) {
                if (TrainingSeries::whereKey($id)->exists()) { $totals['vorhanden']++; continue; }
                $rows[] = $this->one($id, $userId, $totals);
            }
            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['rows' => $rows, 'totals' => $totals, 'applied' => $apply];
    }

    /**
     * Serie zu einer recurrence_group_id - bei Bedarf aus den Einheiten anlegen
     * (Serien, die nach der Uebernahme noch auf altem Weg entstanden sind).
     */
    public function ensure(string $id): ?TrainingSeries
    {
        if ($series = TrainingSeries::find($id)) return $series;
        if (!TrainingSession::where('recurrence_group_id', $id)->exists()) return null;

        $totals = array_fill_keys(['serien', 'abweichungen', 'belegungen', 'verbunden', 'entfernt'], 0);
        DB::transaction(fn() => $this->one($id, auth()->id() ?? User::where('role', 'admin')->orderBy('id')->value('id'), $totals));

        return TrainingSeries::find($id);
    }

    private function one(string $id, ?int $userId, array &$totals): array
    {
        $sessions = TrainingSession::with(['trainingGroups', 'coTrainers'])
            ->where('recurrence_group_id', $id)->orderBy('date')->get();
        $future = $sessions->filter(fn($s) => $s->date->gte(today()));
        $rep    = $future->first() ?? $sessions->last();

        // Liegt bisher kein Termin in den Ferien, hat die Serie sie ausgelassen
        $holidays = Holiday::intersecting($sessions->first()->date, $sessions->last()->date);
        $inHolidays = $sessions->filter(fn($s) => $holidays->contains(fn($h) => $h->containsDate($s->date)))->count();

        $series = TrainingSeries::create([
            'id'                => $id,
            'season_id'         => Season::forDate($rep->date)?->id,
            'title'             => $rep->title,
            'type'              => $rep->type,
            'day_of_week'       => $rep->date->dayOfWeekIso,
            'start_time'        => $rep->start_time,
            'end_time'          => $rep->end_time,
            'location'          => $rep->location,
            'recurrence_type'   => in_array($rep->recurrence_type, ['weekly', 'biweekly', 'monthly'], true) ? $rep->recurrence_type : 'weekly',
            'valid_from'        => $sessions->first()->date,
            'valid_until'       => $sessions->last()->date,
            'skip_holidays'     => $inHolidays === 0,
            'max_participants'  => $rep->max_participants,
            'registration_open' => (bool) $rep->registration_open,
            'guest_group_id'    => $rep->guest_group_id,
            'notes'             => $rep->notes,
        ]);
        $series->trainingGroups()->sync($rep->trainingGroups->pluck('id'));
        $series->trainers()->sync($rep->coTrainers->pluck('id'));
        $totals['serien']++;

        // Kommende Einheiten mit bewusst anderen Werten: Abweichung festhalten
        $abweichend = 0;
        foreach ($future as $s) {
            if ($s->overridden_fields) continue;
            $diff = collect(self::COMPARE)->filter(fn($f) => $this->norm($f, $s->$f) !== $this->norm($f, $rep->$f))->values()->all();
            if ($s->date->dayOfWeekIso !== $rep->date->dayOfWeekIso) $diff[] = 'date';
            if ($diff) {
                $s->forceFill(['overridden_fields' => $diff])->saveQuietly();
                $abweichend++;
            }
        }
        $totals['abweichungen'] += $abweichend;

        // Hallenbelegung an die Serie
        $own = $this->lanes->bookingsOf($id);
        $stats = ['kept' => 0, 'adopted' => 0, 'removed' => 0];
        $kandidaten = collect();
        if ($future->isNotEmpty() && $rep->end_time) {
            $kandidaten = HallBooking::whereNull('training_session_id')->whereNull('training_series_id')
                ->where('day_of_week', $rep->date->dayOfWeekIso)
                ->where('start_time', substr($rep->start_time, 0, 5))->where('end_time', substr($rep->end_time, 0, 5))
                ->get()->filter(fn($b) => $this->lanes->belongsToSeries($b, $rep));
            $resources = $own->pluck('hall_resource_id')->merge($kandidaten->pluck('hall_resource_id'))->unique()->values()->all();
            if ($resources) $stats = $this->lanes->sync($id, $resources, $userId);
        } else {
            // Beendete Serie: nichts verschieben oder loeschen, nur zuordnen
            HallBooking::whereIn('id', $own->pluck('id'))->update(['training_series_id' => $id]);
            $stats['kept'] = $own->count();
        }
        $totals['belegungen'] += $stats['kept'];
        $totals['verbunden']  += $stats['adopted'];
        $totals['entfernt']   += $stats['removed'];

        return [
            'serie'      => $rep->title,
            'tag_zeit'   => (HallBooking::DAY_NAMES[$rep->date->dayOfWeekIso] ?? '') . ' ' . substr($rep->start_time, 0, 5) . '–' . substr($rep->end_time ?? '', 0, 5),
            'einheiten'  => $sessions->count() . ' (' . $future->count() . ' kommend)',
            'saison'     => $series->season?->name ?? '–',
            'gruppen'    => $rep->trainingGroups->pluck('name')->implode(', ') ?: '–',
            'ferien_auslassen' => $series->skip_holidays ? 'ja' : "nein ($inHolidays in Ferien)",
            'abweichende_einheiten' => $abweichend,
            'belegungen' => $stats['kept'] . ' behalten, ' . $stats['adopted'] . ' verbunden, ' . $stats['removed'] . ' doppelte entfernt',
        ];
    }

    private function norm(string $field, $value): string
    {
        return in_array($field, ['start_time', 'end_time'], true) ? substr((string) $value, 0, 5) : (string) $value;
    }
}
