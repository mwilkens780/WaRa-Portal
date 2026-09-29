<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\CompetitionSignupRequest;
use App\Models\Record;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingAttendance;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingGroupGoalEvaluation;
use App\Models\TrainingSession;
use App\Models\TrainingSessionSwimmer;
use App\Models\SwimmingTime;
use App\Models\GroupMottoWeek;
use App\Services\CompetitionResultGrouper;
use App\Support\TrainingParticipation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const DISC_LABELS = [
        'F' => 'Freistil',
        'B' => 'Brust',
        'R' => 'Rücken',
        'S' => 'Schmetterling',
        'L' => 'Lagen',
    ];

    /**
     * Sichtbarkeitsregel fuer Listen im Dashboard.
     *
     * Die Regel selbst steht im Modell (TrainingSession::scopeVisibleToSwimmer),
     * damit Dashboard, Kalender und Detailseite nicht auseinanderlaufen.
     */
    private function buildVisibilityFilter(int $swimmerId): \Closure
    {
        $swimmer = \App\Models\User::find($swimmerId);

        return fn($q) => $q->visibleToSwimmer($swimmer);
    }

    private function buildExclusionFilter(int $swimmerId): \Closure
    {
        $excluded      = SwimmerSeriesExclusion::where('user_id', $swimmerId)->pluck('recurrence_group_id');
        $individualIds = TrainingSessionSwimmer::where('user_id', $swimmerId)
            ->whereNotNull('training_session_id')->pluck('training_session_id');

        return function ($q) use ($excluded, $individualIds) {
            if ($excluded->isNotEmpty()) {
                $q->where(function ($inner) use ($excluded, $individualIds) {
                    $inner->whereNull('recurrence_group_id')
                          ->orWhereNotIn('recurrence_group_id', $excluded);
                    if ($individualIds->isNotEmpty()) {
                        // Explicit individual joins override a series exclusion
                        $inner->orWhereIn('id', $individualIds);
                    }
                });
            }
        };
    }

    public function index()
    {
        $swimmer = auth()->user();

        $swimmerGroupIds = $swimmer->trainingGroups()->pluck('training_groups.id');

        $relevantSessions = $this->buildVisibilityFilter($swimmer->id);

        $attendedTotal = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)->count();
        $attendedYear  = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereHas('session', fn($q) => $q->whereYear('date', now()->year))->count();

        $currentSeason = Season::current();

        // Beteiligung: eine Rechnung fuer Dashboard und Trainingsansicht,
        // siehe App\Support\TrainingParticipation.
        $participation  = TrainingParticipation::forSwimmer($swimmer);
        $attendedSeason = $participation['season']['attended'];

        // Einheiten dieser Woche - nur fuer die Kilometerkarte
        $weekStart    = now()->startOfWeek();
        $attendedWeek = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereHas('session', fn($q) => $q->whereDate('date', '>=', $weekStart)->whereDate('date', '<=', today()))->count();

        // km diese Woche: Summe der Trainingsplan-Distanzen aller Einheiten, bei denen der Schwimmer anwesend war
        $kmThisWeek = TrainingSession::whereBetween('date', [$weekStart, today()])
            ->whereHas('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('attended', true))
            ->with('trainingPlan.blocks')
            ->get()
            ->sum(function ($s) {
                return $s->trainingPlan
                    ? $s->trainingPlan->blocks->sum(fn($b) => $b->total_repetitions * ($b->distance ?? 0))
                    : 0;
            });

        // Bestzeiten: Trainingszeiten + Wettkampfergebnisse zusammengeführt
        [$allBests, $yearBests, $seasonBests] = $this->buildCombinedBests($swimmer->id);

        // Wettkaempfe der laufenden Saison - gezaehlt werden Veranstaltungen,
        // nicht Starts: zwoelf Ergebnisse an einem Wochenende sind ein Wettkampf.
        $competitionsSeason = $currentSeason
            ? CompetitionResult::where('user_id', $swimmer->id)
                ->where('time_ms', '>', 0)
                ->whereHas('competition', fn($q) => $q->whereBetween('date', [$currentSeason->start_date, $currentSeason->end_date]))
                ->distinct('competition_id')->count('competition_id')
            : 0;

        $stats = [
            'trainings_total'       => $attendedTotal,
            'trainings_this_year'   => $attendedYear,
            'trainings_season'      => $attendedSeason,
            'personal_bests'        => $allBests->count(),
            'personal_bests_season' => $seasonBests->count(),
            'goals_season'          => 0,   // wird unten gesetzt, sobald die Ziele geladen sind
            'competitions'          => CompetitionResult::where('user_id', $swimmer->id)
                ->where('time_ms', '>', 0)->distinct('competition_id')->count('competition_id'),
            'competitions_season'   => $competitionsSeason,
            'participation'         => $participation,
            'attended_week'         => $attendedWeek,
            'km_this_week'          => round($kmThisWeek / 1000, 2),
        ];

        // Letzte Trainings - letzte 2 Wochen. Nicht nur bestaetigte: Eine Einheit,
        // die gerade zu Ende ist, hat noch keine erfasste Anwesenheit, das
        // Tagebuch soll aber sofort offen sein. Wer abgesagt hat, bleibt aussen vor.
        $recent_sessions = TrainingSession::finished()
            ->whereDate('date', '>=', today()->subDays(13))
            ->tap($relevantSessions)
            ->whereDoesntHave('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('pre_absent', true))
            ->with([
                'coTrainers:id,firstname,lastname',
                'attendances' => fn($q) => $q->where('user_id', $swimmer->id),
                'diaries'     => fn($q) => $q->where('user_id', $swimmer->id),
            ])
            ->orderByDesc('date')->orderByDesc('start_time')
            ->get();

        // Letzte Wettkampfergebnisse (zusammengeführt)
        $recent_results = CompetitionResultGrouper::forSwimmer(
            CompetitionResult::with('competition')
                ->where('user_id', $swimmer->id)
                ->where('time_ms', '>', 0)
                ->get()
        )->take(5);

        // Nächster anstehender Wettkampf: Trainingsgruppen-Zuweisung ODER direkte Signup-Einladung
        // upcomingOrRunning: auch ein heute bzw. noch laufender (mehrtaegiger) Wettkampf
        $next_competition = Competition::upcomingOrRunning()
            ->where(function ($q) use ($swimmerGroupIds, $swimmer) {
                if ($swimmerGroupIds->isNotEmpty()) {
                    $q->whereHas('trainingGroups', fn($inner) =>
                        $inner->whereIn('training_groups.id', $swimmerGroupIds)
                    );
                }
                $q->orWhereHas('signupRequest.responses', fn($inner) =>
                    $inner->where('user_id', $swimmer->id)
                );
            })
            ->orderBy('date')->first();

        // Ziele der aktuellen Saison
        $goalsTotal    = $currentSeason ? SwimmerGoal::where('user_id', $swimmer->id)->where('season_id', $currentSeason->id)->count() : 0;
        $goalsAchieved = $currentSeason ? SwimmerGoal::where('user_id', $swimmer->id)->where('season_id', $currentSeason->id)->where('achieved', true)->count() : 0;
        $goalsUnnotified = $currentSeason ? SwimmerGoal::where('user_id', $swimmer->id)->where('season_id', $currentSeason->id)->where('notified', false)->where('achieved', true)->count() : 0;

        $stats['goals_season'] = $goalsTotal;

        // Geplante Trainings nächste 2 Wochen (Gruppen + individuelle Zuweisungen, ohne ausgeblendete Serien)
        $exclusionFilter = $this->buildExclusionFilter($swimmer->id);
        $upcoming_sessions = TrainingSession::upcomingOrRunning()
            ->whereDate('date', '<=', today()->addDays(14))
            ->tap($relevantSessions)
            ->tap($exclusionFilter)
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $my_pre_absences = TrainingAttendance::where('user_id', $swimmer->id)
            ->where('pre_absent', true)
            ->whereIn('training_session_id', $upcoming_sessions->pluck('id'))
            ->pluck('pre_absent_note', 'training_session_id');

        // Offene Anmeldeabfragen für diesen Schwimmer
        // Banner nur bis zum Ende des Wettkampfs - eine nicht geschlossene
        // Abfrage blieb sonst fuer immer auf dem Dashboard stehen
        $pendingSignups = CompetitionSignupRequest::where('status', 'active')
            ->whereHas('competition', fn($q) => $q->upcomingOrRunning())
            ->whereHas('responses', fn($q) => $q->where('user_id', $swimmer->id)->where('status', 'pending'))
            ->with('competition')
            ->get();

        // Aktive Abfragen mit Bus-Option, bei denen der Schwimmer bereits zugesagt hat
        $busSignups = CompetitionSignupRequest::where('status', 'active')
            ->where('bus_available', true)
            ->whereHas('competition', fn($q) => $q->upcomingOrRunning())
            ->whereHas('responses', fn($q) => $q->where('user_id', $swimmer->id)->where('status', 'attending'))
            ->with(['competition', 'responses' => fn($q) => $q->where('user_id', $swimmer->id)])
            ->get();

        $new_records = $this->loadNewRecords();

        // ── Motto der Woche ─────────────────────────────────────────────────────
        $mottoMonday    = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        // Auch Zyklen, in denen die eigene Gruppe als Partnergruppe mitlaeuft
        $mottoGroupIds  = app(\App\Services\MottoWeekService::class)->cycleGroupIdsFor($swimmer);

        // Current week's motto for the dashboard widget
        $dashboardMotto = GroupMottoWeek::whereIn('training_group_id', $mottoGroupIds)
            ->where('week_start', $mottoMonday->format('Y-m-d'))
            ->with(['group:id,name,color', 'user:id,firstname,lastname'])
            ->first();

        // Erinnerung an das eigene Motto - erst eine Woche vor der Faelligkeit.
        // Wer im Maerz sieht, dass er im Juni dran ist, vergisst es bis dahin
        // ohnehin; bis dahin steht der Hinweis nur im Weg.
        // whereDate statt eines Bereichsvergleichs: je nach Datenbank steht in
        // der Spalte "2026-09-28" oder "2026-09-28 00:00:00", und als Zeichen-
        // kette verglichen faellt die obere Grenze dann heraus.
        $mottoReminder = GroupMottoWeek::whereIn('training_group_id', $mottoGroupIds)
            ->where('user_id', $swimmer->id)
            ->whereDate('week_start', '>=', $mottoMonday->format('Y-m-d'))
            ->whereDate('week_start', '<=', $mottoMonday->copy()->addDays(7)->format('Y-m-d'))
            ->whereNull('motto')
            ->orderBy('week_start')
            ->with('group:id,name,color')
            ->first();

        return view('swimmer.dashboard', compact(
            'stats', 'allBests', 'yearBests', 'seasonBests',
            'recent_sessions', 'recent_results',
            'next_competition', 'upcoming_sessions', 'my_pre_absences',
            'goalsTotal', 'goalsAchieved', 'goalsUnnotified',
            'pendingSignups', 'busSignups', 'new_records',
            'dashboardMotto', 'mottoReminder'
        ));
    }

    private function loadNewRecords(): \Illuminate\Support\Collection
    {
        [$seasonStart, $seasonEnd] = $this->currentSeasonRange();
        return CompetitionResult::with(['user', 'competition'])
            ->where(fn($q) => $q->where('breaks_vereinsrekord', true)->orWhere('breaks_landesrekord', true))
            ->whereNull('age_group')
            ->whereHas('competition', fn($q) => $q->whereBetween('date', [$seasonStart, $seasonEnd]))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
    }

    private function currentSeasonRange(): array
    {
        $month = now()->month;
        $year  = now()->year;
        if ($month >= 4 && $month <= 9) {
            return ["{$year}-04-01", "{$year}-09-30"];
        }
        $start = $month >= 10 ? "{$year}-10-01" : ($year - 1) . "-10-01";
        $end   = $month >= 10 ? ($year + 1) . "-03-31" : "{$year}-03-31";
        return [$start, $end];
    }

    /**
     * Kombinierte Bestzeiten aus Trainingszeiten + Wettkampfergebnissen.
     * Gibt [allBests, yearBests, seasonBests] zurück.
     * Jedes Element: Collection keyed by "label" (z.B. "Freistil 100m")
     *   mit keys: label, discipline, distance, best_ms, source ('training'|'competition'|'both')
     */
    private function buildCombinedBests(int $userId): array
    {
        $now = now();
        $year = $now->year;
        $month = $now->month;

        // Saison-Grenzen
        if ($month >= 4 && $month <= 9) {
            $seasonStart = "{$year}-04-01";
            $seasonEnd   = "{$year}-09-30";
        } else {
            $seasonStart = $month >= 10 ? "{$year}-10-01" : ($year - 1) . "-10-01";
            $seasonEnd   = $month >= 10 ? ($year + 1) . "-03-31" : "{$year}-03-31";
        }

        // Einzelzeiten statt Aggregaten: Zu einer Bestzeit gehoert die Frage
        // "wann und wo?", und die beantwortet ein MIN() nicht. Die Menge ist je
        // Schwimmer ueberschaubar, deshalb wird hier in PHP zusammengefasst.
        $trainingszeiten = SwimmingTime::with('trainingSession:id,title,date,location')
            ->where('user_id', $userId)
            ->get()
            ->map(fn($t) => (object) [
                'key'      => $t->discipline . '_' . $t->distance,
                'time_ms'  => (int) $t->time_ms,
                'source'   => 'training',
                'date'     => $t->trainingSession?->date ?? $t->created_at,
                'place'    => $t->trainingSession?->location ?: 'Training',
            ]);

        $wettkampfzeiten = CompetitionResult::with('competition:id,name,date,location')
            ->where('user_id', $userId)->where('time_ms', '>', 0)
            ->get()
            ->map(fn($r) => (object) [
                'key'      => $r->discipline . '_' . $r->distance,
                'time_ms'  => (int) $r->time_ms,
                'source'   => 'competition',
                'date'     => $r->competition?->date,
                'place'    => $r->competition?->location ?: $r->competition?->name,
            ]);

        $alle = $trainingszeiten->concat($wettkampfzeiten)->filter(fn($z) => $z->time_ms > 0);

        $imZeitraum = fn($von, $bis) => $alle->filter(fn($z) => $z->date
            && $z->date->betweenIncluded(Carbon::parse($von), Carbon::parse($bis)));

        return [
            $this->bestenJeStrecke($alle),
            $this->bestenJeStrecke($imZeitraum("{$year}-01-01", "{$year}-12-31")),
            $this->bestenJeStrecke($imZeitraum($seasonStart, $seasonEnd)),
        ];
    }

    /**
     * Je Strecke die schnellste Zeit - mitsamt Datum und Ort.
     *
     * Bei gleicher Zeit gewinnt die frueher geschwommene: Wer eine Zeit
     * wiederholt, hat sie nicht neu aufgestellt.
     */
    private function bestenJeStrecke(\Illuminate\Support\Collection $zeiten): \Illuminate\Support\Collection
    {
        $beste = [];

        foreach ($zeiten as $z) {
            $vorhanden = $beste[$z->key] ?? null;
            $besser    = !$vorhanden
                || $z->time_ms < $vorhanden->time_ms
                || ($z->time_ms === $vorhanden->time_ms
                    && $z->date && $vorhanden->date && $z->date->lt($vorhanden->date));

            if ($besser) $beste[$z->key] = $z;
        }

        return collect($beste)->map(function ($z, $key) {
            [$disc, $dist] = explode('_', $key, 2);

            return (object) [
                'key'        => $key,
                'discipline' => $disc,
                'distance'   => (int) $dist,
                'best_ms'    => $z->time_ms,
                'label'      => (self::DISC_LABELS[$disc] ?? $disc) . ' ' . $dist . 'm',
                'source'     => $z->source,
                'formatted'  => SwimmingTime::formatMs($z->time_ms),
                'date'       => $z->date,
                'date_label' => $z->date?->format('d.m.Y'),
                'place'      => $z->place,
            ];
        })
        ->sortBy([['discipline', 'asc'], ['distance', 'asc']])
        ->values()
        ->keyBy('label');
    }

    public function myTrainings()
    {
        $swimmer          = auth()->user();
        $relevantSessions = $this->buildVisibilityFilter($swimmer->id);
        $exclusionFilter  = $this->buildExclusionFilter($swimmer->id);

        // ── Statistik ───────────────────────────────────────────────────────
        // Dieselbe Rechnung wie auf dem Dashboard, siehe
        // App\Support\TrainingParticipation: Saison und laufender Monat.
        $participation = TrainingParticipation::forSwimmer($swimmer);

        $diaryPendingCount = TrainingSession::finished()
            ->tap($relevantSessions)
            ->whereHas('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('attended', true))
            ->whereDoesntHave('diaries', fn($q) => $q->where('user_id', $swimmer->id))
            ->count();

        // ── Trainingsplanung: assigned series (grouped by recurrence_group_id) ──
        $swimmerGroupIds = $swimmer->trainingGroups()->pluck('training_groups.id');

        $groupSeriesIds = TrainingSession::whereNotNull('recurrence_group_id')
            ->whereHas('trainingGroups', fn($q) => $q->whereIn('training_groups.id', $swimmerGroupIds))
            ->distinct()->pluck('recurrence_group_id');

        $individualSeriesIds = TrainingSessionSwimmer::where('user_id', $swimmer->id)
            ->whereNotNull('recurrence_group_id')->pluck('recurrence_group_id');

        $allSeriesIds = $groupSeriesIds->merge($individualSeriesIds)->unique()->values();

        // Load exclusions as objects to access comment
        $exclusions        = SwimmerSeriesExclusion::where('user_id', $swimmer->id)->get()->keyBy('recurrence_group_id');

        $dayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

        $trainingSeries = collect();
        foreach ($allSeriesIds as $seriesId) {
            $rep = TrainingSession::where('recurrence_group_id', $seriesId)
                ->with(['trainingGroups:id,name', 'coTrainers:id,firstname,lastname'])
                ->orderBy('date')
                ->first();
            if ($rep) {
                $isExcluded = $exclusions->has($seriesId);
                $exclusion  = $exclusions->get($seriesId);

                // Load next upcoming sessions for excluded series (for punctual-join UI)
                $upcomingForSeries = $isExcluded
                    ? TrainingSession::where('recurrence_group_id', $seriesId)
                        ->where('date', '>', today())
                        ->orderBy('date')->orderBy('start_time')
                        ->take(6)->get()
                    : null;

                $trainingSeries->push((object)[
                    'recurrence_group_id' => $seriesId,
                    'title'               => $rep->title,
                    'type'                => $rep->type,
                    'type_color'          => $rep->type_color,
                    'groups'              => $rep->trainingGroups,
                    'trainer'             => $rep->trainer,
                    'start_time'          => $rep->start_time ? substr($rep->start_time, 0, 5) : null,
                    'end_time'            => $rep->end_time   ? substr($rep->end_time,   0, 5) : null,
                    'day_of_week_iso'     => $rep->date->dayOfWeekIso,
                    'day_label'           => $dayLabels[$rep->date->dayOfWeekIso - 1],
                    'is_excluded'         => $isExcluded,
                    'exclusion_comment'   => $exclusion?->comment,
                    'upcoming_sessions'   => $upcomingForSeries,
                ]);
            }
        }

        // Sort chronologically Mo (1) → So (7)
        $trainingSeries = $trainingSeries->sortBy('day_of_week_iso')->values();

        // Nur Absagen auf Serien zählen, die der Schwimmer noch hat - alte
        // Absagen auf Serien früherer Gruppen bleiben sonst ewig im Zähler
        $excludedSeriesIds = $trainingSeries->where('is_excluded', true)->pluck('recurrence_group_id');

        // ── Bevorstehend: heute bis zum Ende der Trainingszeit, dann 13 Tage ─
        $upcoming = TrainingSession::upcomingOrRunning()
            ->whereDate('date', '<=', today()->addDays(13))
            ->tap($relevantSessions)
            ->tap($exclusionFilter)
            ->with(['coTrainers:id,firstname,lastname', 'trainingGroups'])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $upcomingLaterCount = TrainingSession::whereDate('date', '>', today()->addDays(13))
            ->tap($relevantSessions)
            ->tap($exclusionFilter)
            ->count();

        $upcomingIds = $upcoming->pluck('id');

        $myRegistrations = \App\Models\TrainingSessionRegistration::where('user_id', $swimmer->id)
            ->whereIn('training_session_id', $upcomingIds)
            ->pluck('training_session_id');

        $preAbsenceMap = TrainingAttendance::where('user_id', $swimmer->id)
            ->where('pre_absent', true)
            ->whereIn('training_session_id', $upcomingIds)
            ->get()
            ->keyBy('training_session_id');

        // ── Guest training opportunities ─────────────────────────────────────
        $allGuestSessions = TrainingSession::upcomingOrRunning()
            ->whereIn('guest_group_id', $swimmerGroupIds)
            ->whereNotNull('max_participants')
            ->with(['trainingGroups.swimmers', 'attendances', 'trainingGroups:id,name'])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $myGuestBookingIds = \App\Models\TrainingSessionSwimmer::where('user_id', $swimmer->id)
            ->where('is_guest', true)
            ->whereIn('training_session_id', $allGuestSessions->pluck('id'))
            ->pluck('training_session_id')
            ->toArray();

        // Compute available spots per guest session
        $guestSessions = $allGuestSessions->map(function ($s) use ($myGuestBookingIds) {
            $expected   = $s->expectedParticipantCount();
            $preAbsent  = $s->attendances->where('pre_absent', true)->count();
            $effective  = $expected - $preAbsent;
            $spots      = max(0, $s->max_participants - $effective);
            return (object)[
                'session'   => $s,
                'spots'     => $spots,
                'booked'    => in_array($s->id, $myGuestBookingIds),
                'available' => $spots > 0,
            ];
        })->filter(fn($g) => $g->available || $g->booked)->values();

        // ── Past sessions: 14-day sliding window ──────────────────────────────
        // Voreinstellung "alle": Eine Einheit, die gerade zu Ende gegangen ist,
        // hat noch keine erfasste Anwesenheit - mit dem Filter "anwesend" waere
        // sie unsichtbar, und genau dort soll das Tagebuch geschrieben werden.
        $filter   = request('filter', 'all');
        $pastPage = max(1, (int) request('past_page', 1));
        $windowEnd   = today()->subDays(($pastPage - 1) * 14);
        $windowStart = today()->subDays($pastPage * 14 - 1);

        $pastQuery = TrainingSession::finished()
            ->whereDate('date', '<=', $windowEnd)
            ->whereDate('date', '>=', $windowStart)
            ->tap($relevantSessions)
            ->whereDoesntHave('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('pre_absent', true))
            ->with([
                'coTrainers:id,firstname,lastname',
                'attendances' => fn($q) => $q->where('user_id', $swimmer->id),
                'diaries'     => fn($q) => $q->where('user_id', $swimmer->id),
            ])
            ->orderByDesc('date')->orderByDesc('start_time');

        if ($filter === 'attended') {
            $pastQuery->whereHas('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('attended', true));
        }

        $pastSessions   = $pastQuery->get();
        $pastHasNewer   = $pastPage > 1;
        $pastHasOlder   = TrainingSession::whereDate('date', '<', $windowStart)
            ->tap($relevantSessions)
            ->whereDoesntHave('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('pre_absent', true))
            ->when($filter === 'attended', fn($q) => $q->whereHas('attendances', fn($a) => $a->where('user_id', $swimmer->id)->where('attended', true)))
            ->exists();
        $pastWindowLabel = $windowStart->format('d.m.') . ' – ' . $windowEnd->format('d.m.Y');

        return view('swimmer.my-trainings', compact(
            'participation', 'diaryPendingCount',
            'trainingSeries', 'excludedSeriesIds',
            'upcoming', 'upcomingLaterCount',
            'preAbsenceMap', 'myRegistrations',
            'pastSessions', 'filter', 'pastPage', 'pastHasNewer', 'pastHasOlder', 'pastWindowLabel',
            'guestSessions'
        ));
    }

    public function cancelSession(Request $request, TrainingSession $session)
    {
        // Bis zum Ende der Trainingszeit: Wer morgens merkt, dass es abends
        // nicht klappt, soll noch absagen koennen.
        if ($session->isOver()) {
            return back()->with('error', 'Vergangene Einheiten können nicht abgesagt werden.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance = TrainingAttendance::where('training_session_id', $session->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($attendance?->pre_absent) {
            $attendance->update(['pre_absent' => false, 'pre_absent_note' => null]);
            return back()->with('success', 'Absage zurückgenommen.');
        }

        TrainingAttendance::updateOrCreate(
            ['training_session_id' => $session->id, 'user_id' => auth()->id()],
            ['pre_absent' => true, 'pre_absent_note' => $data['note'] ?? null]
        );

        // Notify guest group if this cancellation opens a spot
        \App\Http\Controllers\Trainer\TrainingSessionController::notifyGuestGroupIfSpotAvailable($session);

        return back()->with('success', 'Absage gespeichert.');
    }

    public function bookGuestSlot(TrainingSession $session)
    {
        $swimmer = auth()->user();

        // Verify the swimmer's group is the session's guest group
        $isInGuestGroup = $session->guest_group_id &&
            $swimmer->trainingGroups()->where('training_groups.id', $session->guest_group_id)->exists();

        if (!$isInGuestGroup) {
            return back()->with('error', 'Du bist nicht in der Gastgruppe dieser Einheit.');
        }

        if ($session->isOver()) {
            return back()->with('error', 'Diese Trainingseinheit liegt in der Vergangenheit.');
        }

        // Check if already booked
        $existing = TrainingSessionSwimmer::where('training_session_id', $session->id)
            ->where('user_id', $swimmer->id)->exists();

        if ($existing) {
            return back()->with('info', 'Du hast bereits einen Platz gebucht.');
        }

        // Atomic check: spots still available
        $availableSpots = $session->availableSpotsForGuests();
        if ($availableSpots !== null && $availableSpots <= 0) {
            return back()->with('error', 'Leider sind keine freien Plätze mehr verfügbar.');
        }

        TrainingSessionSwimmer::create([
            'training_session_id' => $session->id,
            'user_id'             => $swimmer->id,
            'is_guest'            => true,
        ]);

        return back()->with('success', 'Platz gebucht! Du bist jetzt für dieses Gasttraining eingetragen.');
    }

    public function cancelGuestSlot(TrainingSession $session)
    {
        $swimmer = auth()->user();

        TrainingSessionSwimmer::where('training_session_id', $session->id)
            ->where('user_id', $swimmer->id)
            ->where('is_guest', true)
            ->delete();

        return back()->with('success', 'Gastbuchung storniert.');
    }

    // Bestzeiten und Wettkaempfe: gemeinsame Logik mit der Elternsicht (SwimmerPages)
    public function myTimes(\App\Services\SwimmerPages $pages)
    {
        return view('swimmer.my-times', $pages->times(auth()->user()));
    }

    public function myCompetitions(\App\Services\SwimmerPages $pages)
    {
        return view('swimmer.my-competitions', $pages->competitions(auth()->user()));
    }

    public function sessionDetail(\App\Models\TrainingSession $session)
    {
        $user = auth()->user();

        // Eine Regel fuer Kalender, Dashboard und diese Seite (siehe
        // TrainingSession::scopeVisibleToSwimmer) - sonst zeigt der Kalender
        // Einheiten an, deren Klick hier mit 403 endet.
        if (!$session->isVisibleToSwimmer($user)) {
            abort(403);
        }

        $session->load('coTrainers:id,firstname,lastname', 'diaries.user', 'trainingPlan.blocks');
        $diary = $session->diaryFor(auth()->id());

        $myAttendance = TrainingAttendance::where('training_session_id', $session->id)
            ->where('user_id', auth()->id())
            ->first();

        $myBlockTimes = [];
        if ($session->trainingPlan) {
            $blockIds = $session->trainingPlan->blocks->pluck('id');
            \App\Models\TrainingBlockTime::whereIn('training_plan_block_id', $blockIds)
                ->where('user_id', auth()->id())
                ->get()
                ->each(function ($t) use (&$myBlockTimes) {
                    $myBlockTimes[$t->training_plan_block_id][$t->repetition] = $t->time_cs;
                });
        }

        return view('swimmer.session-detail', compact('session', 'diary', 'myAttendance', 'myBlockTimes'));
    }

    // ── Gruppenziele ─────────────────────────────────────────────────────

    public function groupGoals()
    {
        $swimmer = auth()->user();

        // All active groups with active goals + self-evaluations of this swimmer
        $allGroups = TrainingGroup::where('active', true)
            ->with(['goals' => function ($q) {
                $q->where('active', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('name')
            ->get();

        // Swimmer's own group IDs
        $myGroupIds = $swimmer->trainingGroups()->pluck('training_groups.id')->toArray();

        // All goal IDs across all groups
        $allGoalIds = $allGroups->flatMap(fn($g) => $g->goals->pluck('id'));

        // Leistungskriterien gelten je Saison. Schwimmer sehen und bewerten
        // immer die laufende Saison - unabhaengig vom Saison-Umschalter.
        $season = Season::current();

        $evals = TrainingGroupGoalEvaluation::where('user_id', $swimmer->id)
            ->where('season_id', $season?->id)
            ->whereIn('training_group_goal_id', $allGoalIds)
            ->get();

        $selfEvals    = $evals->where('evaluation_type', 'self')->keyBy('training_group_goal_id');
        $trainerEvals = $evals->where('evaluation_type', 'trainer')->keyBy('training_group_goal_id');

        return view('swimmer.group-goals', compact(
            'allGroups', 'myGroupIds', 'selfEvals', 'trainerEvals', 'season'
        ));
    }

    public function storeGroupGoalEvaluation(Request $request, TrainingGroupGoal $goal)
    {
        $swimmer = auth()->user();

        // Swimmer must be in a group that either owns this goal OR is looking at it for qualification
        // (We allow all swimmers to self-evaluate on all group goals)

        $data = $request->validate([
            'achieved' => ['nullable', 'in:0,1'],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ]);

        $season = Season::current();
        if (!$season) {
            return back()->withErrors(['achieved' => 'Es ist keine laufende Saison angelegt.']);
        }

        TrainingGroupGoalEvaluation::record(
            $goal, $swimmer->id, 'self', $season->id,
            $data['achieved'] ?? null, $data['notes'] ?? null, $swimmer->id,
        );

        return back()->with('success', 'Eigenbewertung gespeichert.');
    }
}
