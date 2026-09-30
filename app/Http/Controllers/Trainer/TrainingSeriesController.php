<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\HallResource;
use App\Models\Season;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingSeries;
use App\Models\TrainingSession;
use App\Models\TrainingSessionSwimmer;
use App\Models\User;
use App\Services\EventMailer;
use App\Services\SeriesHallBookings;
use App\Services\TrainingSeriesBackfill;
use App\Services\TrainingSeriesService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Eine Seite fuer alles Regelmaessige einer Trainingsserie
 * (Ueberblick, Termine, Teilnehmer, Saison) - docs/konzept-trainingsserien.md.
 */
class TrainingSeriesController extends Controller
{
    public const TYPES = [
        'kondition' => 'Kondition', 'technik' => 'Technik', 'wettkampf' => 'Wettkampfvorbereitung', 'ausdauer' => 'Ausdauer',
        'krafttraining' => 'Krafttraining', 'physio' => 'Physiotherapie', 'mentaltraining' => 'Mentaltraining', 'sonstiges' => 'Sonstiges',
    ];

    public function __construct(private TrainingSeriesService $service) {}

    public function show(Request $request, string $group, TrainingSeriesBackfill $backfill, SeriesHallBookings $lanes)
    {
        $series = $this->series($group, $backfill);
        $series->load(['trainingGroups.swimmers' => fn($q) => $q->where('active', true), 'trainers', 'season', 'guestGroup']);

        $sessions = $series->sessions()->withCount('exceptionLanes')->get();
        $future   = $sessions->filter(fn($s) => $s->date->gte(today()));

        $exclusions = SwimmerSeriesExclusion::where('recurrence_group_id', $group)->with('user:id,firstname,lastname')->get()->sortBy('user.lastname');
        $individual = TrainingSessionSwimmer::where('recurrence_group_id', $group)->whereNull('training_session_id')
            ->with('user:id,firstname,lastname')->get()->sortBy('user.lastname');
        $groupSwimmerIds = $series->trainingGroups->flatMap(fn($g) => $g->swimmers->pluck('id'))->unique();
        $expectedCount = $groupSwimmerIds->merge($individual->pluck('user_id'))->unique()->diff($exclusions->pluck('user_id'))->count();

        $ids = $sessions->pluck('id');
        $preAbsent = TrainingAttendance::whereIn('training_session_id', $ids)->where('pre_absent', true)
            ->groupBy('training_session_id')->selectRaw('training_session_id, count(*) as cnt')->pluck('cnt', 'training_session_id');
        $attended = TrainingAttendance::whereIn('training_session_id', $ids)->where('attended', true)
            ->groupBy('training_session_id')->selectRaw('training_session_id, count(*) as cnt')->pluck('cnt', 'training_session_id');

        // Vorschlag naechste Saison
        $nextSeason = ($series->season ? Season::after($series->season) : null) ?? Season::current();
        $suggestedStart = $nextSeason?->start_date && $nextSeason->start_date->gt($series->valid_until ?? today())
            ? $nextSeason->start_date->copy() : ($series->valid_until ?? today())->copy()->addWeek();
        while ($suggestedStart->dayOfWeekIso !== $series->day_of_week) $suggestedStart->addDay();

        return view('trainer.series.show', [
            'series'       => $series,
            'sessions'     => $sessions,
            'future'       => $future,
            'tab'          => $request->query('tab', session('tab', 'ueberblick')),
            'allGroups'    => TrainingGroup::where('active', true)->with('trainers:id,firstname,lastname')->orderBy('name')->get(),
            'allTrainers'  => User::whereIn('role', ['trainer', 'admin'])->where('active', true)->orderBy('lastname')->orderBy('firstname')->get(),
            'allResources' => HallResource::where('active', true)->orderBy('sort_order')->get(),
            'bookedResourceIds' => $lanes->bookingsOf($group)->pluck('hall_resource_id')->unique()->values()->all(),
            'exclusions'   => $exclusions,
            'individual'   => $individual,
            'allSwimmers'  => User::where('role', 'schwimmer')->where('active', true)->orderBy('lastname')->orderBy('firstname')->get(['id', 'firstname', 'lastname']),
            'expectedCount' => $expectedCount,
            'preAbsent'    => $preAbsent,
            'attended'     => $attended,
            'nextSeason'   => $nextSeason,
            'suggestedStart' => $suggestedStart,
            'suggestedEnd' => $nextSeason?->end_date && $nextSeason->end_date->gt($suggestedStart) ? $nextSeason->end_date : $suggestedStart->copy()->addMonths(10),
            'types'        => self::TYPES,
        ]);
    }

    public function update(Request $request, string $group, TrainingSeriesBackfill $backfill)
    {
        $series = $this->series($group, $backfill);
        $data = $request->validate([
            'valid_from'        => ['required', 'date', 'after_or_equal:today'],
            'title'             => ['required', 'string', 'max:255'],
            'type'              => ['required', 'in:' . implode(',', array_keys(self::TYPES))],
            'day_of_week'       => ['required', 'integer', 'between:1,7'],
            'start_time'        => ['required', 'date_format:H:i'],
            'end_time'          => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'          => ['required', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'max_participants'  => ['nullable', 'integer', 'min:1', 'max:999'],
            'registration_open' => ['boolean'],
            'guest_group_id'    => ['nullable', 'exists:training_groups,id'],
            'skip_holidays'     => ['boolean'],
            'groups'            => ['array'], 'groups.*' => ['integer', 'exists:training_groups,id'],
            'trainers'          => ['array'], 'trainers.*' => ['integer', 'exists:users,id'],
            'hall_resource_ids' => ['array'], 'hall_resource_ids.*' => ['integer', 'exists:hall_resources,id'],
        ]);

        $from = Carbon::parse($data['valid_from']);
        $values = collect($data)->except(['valid_from', 'groups', 'trainers', 'hall_resource_ids'])->all();
        $values['registration_open'] = $request->boolean('registration_open');
        $values['skip_holidays']     = $request->boolean('skip_holidays');

        $stats = $this->service->update($series, $values, $from, $data['groups'] ?? [], $data['trainers'] ?? [],
            $data['hall_resource_ids'] ?? [], auth()->id(), $request->boolean('force_lanes'));

        $msg = "Serie gespeichert – gilt ab {$from->format('d.m.Y')} für {$stats['updated']} Termine.";
        if ($stats['moved'])          $msg .= " {$stats['moved']} auf den neuen Wochentag verlegt.";
        if ($stats['kept_overrides']) $msg .= " {$stats['kept_overrides']} Termine mit eigener Abweichung behalten diese.";
        if ($stats['lanes']['removed']) $msg .= " {$stats['lanes']['removed']} doppelte Belegung(en) entfernt.";

        return redirect()->route('trainer.sessions.series.show', $group)->with('success', $msg);
    }

    public function storeSeason(Request $request, string $group, TrainingSeriesBackfill $backfill)
    {
        $series = $this->series($group, $backfill);
        $data = $request->validate([
            'start_date'      => ['required', 'date', 'after_or_equal:today'],
            'end_date'        => ['required', 'date', 'after:start_date'],
            'recurrence_type' => ['required', 'in:weekly,biweekly,monthly'],
        ]);

        $new = $this->service->planNextSeason($series, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']),
            $data['recurrence_type'], $request->boolean('skip_holidays'), auth()->id());

        return redirect()->route('trainer.sessions.series.show', $new->id)
            ->with('success', "Neue Serie für die Saison angelegt ({$new->sessions()->count()} Termine). Gruppen, Trainer und Zeiten bitte prüfen.");
    }

    /** Termin faellt aus (bleibt stehen - Anwesenheit und Statistik bleiben stimmig) */
    public function cancelSession(Request $request, TrainingSession $session, EventMailer $mailer)
    {
        abort_unless($session->isManageableBy(auth()->user()), 403);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $session->update(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason'] ?? null, 'cancelled_at' => now()]);
        $sent = $request->boolean('notify') && $session->date->gte(today()) ? $mailer->trainingCancelled($session) : 0;

        return back()->with('success', 'Termin am ' . $session->date->format('d.m.Y') . ' fällt aus.'
            . ($sent ? " {$sent} Benachrichtigung(en) werden verschickt." : ''));
    }

    public function reactivateSession(TrainingSession $session)
    {
        abort_unless($session->isManageableBy(auth()->user()), 403);
        $session->update(['status' => 'planned', 'cancel_reason' => null, 'cancelled_at' => null]);

        return back()->with('success', 'Termin am ' . $session->date->format('d.m.Y') . ' findet wieder statt.');
    }

    private function series(string $group, TrainingSeriesBackfill $backfill): TrainingSeries
    {
        $series = $backfill->ensure($group);
        abort_unless($series, 404);
        $any = $series->sessions()->first();
        abort_unless($any && $any->isManageableBy(auth()->user()), 403,
            'Diese Serie gehört zu keiner deiner Trainingsgruppen und du bist nicht als Trainer eingetragen.');

        return $series;
    }
}
