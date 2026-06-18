<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSignupRequest;
use App\Models\Record;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
use App\Models\TrainingSessionSwimmer;
use App\Models\SwimmingTime;
use App\Models\CompetitionResult;
use App\Services\CompetitionResultGrouper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    private const DISC_LABELS = [
        'F' => 'Freistil',
        'B' => 'Brust',
        'R' => 'Rücken',
        'S' => 'Schmetterling',
        'L' => 'Lagen',
    ];

    private function buildVisibilityFilter(int $swimmerId): \Closure
    {
        $groupIds        = \App\Models\User::find($swimmerId)->trainingGroups()->pluck('training_groups.id');
        $individualIds   = TrainingSessionSwimmer::where('user_id', $swimmerId)->whereNotNull('training_session_id')->pluck('training_session_id');
        $seriesGroupIds  = TrainingSessionSwimmer::where('user_id', $swimmerId)->whereNotNull('recurrence_group_id')->pluck('recurrence_group_id');

        return function ($q) use ($groupIds, $individualIds, $seriesGroupIds) {
            $q->where(function ($inner) use ($groupIds, $individualIds, $seriesGroupIds) {
                // Sessions belonging to one of swimmer's training groups
                if ($groupIds->isNotEmpty()) {
                    $inner->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $groupIds));
                }
                // Individually assigned sessions
                if ($individualIds->isNotEmpty()) {
                    $inner->orWhereIn('id', $individualIds);
                }
                // Sessions belonging to an individually assigned series
                if ($seriesGroupIds->isNotEmpty()) {
                    $inner->orWhereIn('recurrence_group_id', $seriesGroupIds);
                }
            });
        };
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
        $totalSessions = TrainingSession::where('date', '<=', today())->tap($relevantSessions)->count();
        $attendedYear  = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereHas('session', fn($q) => $q->whereYear('date', now()->year))->count();

        // Saison-Beteiligung
        $currentSeason = Season::current();
        if ($currentSeason) {
            $sessionsSeason = TrainingSession::where('date', '<=', today())
                ->whereBetween('date', [$currentSeason->start_date, $currentSeason->end_date])
                ->tap($relevantSessions)->count();
            $attendedSeason = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
                ->whereHas('session', fn($q) => $q->where('date', '<=', today())
                    ->whereBetween('date', [$currentSeason->start_date, $currentSeason->end_date]))->count();
        } else {
            $sessionsSeason = $attendedSeason = 0;
        }

        // Wochen-Beteiligung
        $weekStart    = now()->startOfWeek();
        $sessionsWeek = TrainingSession::whereBetween('date', [$weekStart, today()])->tap($relevantSessions)->count();
        $attendedWeek = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereHas('session', fn($q) => $q->whereBetween('date', [$weekStart, today()]))->count();

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

        $stats = [
            'trainings_total'       => $attendedTotal,
            'trainings_this_year'   => $attendedYear,
            'personal_bests'        => $allBests->count(),
            'competitions'          => CompetitionResult::where('user_id', $swimmer->id)
                ->where('time_ms', '>', 0)->distinct('competition_id')->count('competition_id'),
            'participation_season'  => $sessionsSeason > 0 ? round($attendedSeason / $sessionsSeason * 100) : 0,
            'participation_week'    => $sessionsWeek > 0 ? round($attendedWeek / $sessionsWeek * 100) : 0,
            'attended_season'       => $attendedSeason,
            'sessions_season'       => $sessionsSeason,
            'attended_week'         => $attendedWeek,
            'sessions_week'         => $sessionsWeek,
            'km_this_week'          => round($kmThisWeek / 1000, 2),
            'season_label'          => $currentSeason?->name ?? SwimmingTime::currentSeasonLabel(),
        ];

        // Letzte absolvierte Trainings (mit Tagebucheintrag-Info)
        $recent_sessions = TrainingSession::where('date', '<=', today())
            ->whereHas('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('attended', true))
            ->with(['coTrainers:id,firstname,lastname', 'diaries' => fn($q) => $q->where('user_id', $swimmer->id)])
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        // Letzte Wettkampfergebnisse (zusammengeführt)
        $recent_results = CompetitionResultGrouper::forSwimmer(
            CompetitionResult::with('competition')
                ->where('user_id', $swimmer->id)
                ->where('time_ms', '>', 0)
                ->get()
        )->take(5);

        // Nächster anstehender Wettkampf: Trainingsgruppen-Zuweisung ODER direkte Signup-Einladung
        $next_competition = Competition::where('date', '>=', today())
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

        // Geplante Trainings nächste 2 Wochen (Gruppen + individuelle Zuweisungen, ohne ausgeblendete Serien)
        $exclusionFilter = $this->buildExclusionFilter($swimmer->id);
        $upcoming_sessions = TrainingSession::where('date', '>', today())
            ->where('date', '<=', today()->addDays(14))
            ->tap($relevantSessions)
            ->tap($exclusionFilter)
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $my_pre_absences = TrainingAttendance::where('user_id', $swimmer->id)
            ->where('pre_absent', true)
            ->whereIn('training_session_id', $upcoming_sessions->pluck('id'))
            ->pluck('pre_absent_note', 'training_session_id');

        // Offene Anmeldeabfragen für diesen Schwimmer
        $pendingSignups = CompetitionSignupRequest::where('status', 'active')
            ->whereHas('responses', fn($q) => $q->where('user_id', $swimmer->id)->where('status', 'pending'))
            ->with('competition')
            ->get();

        // Aktive Abfragen mit Bus-Option, bei denen der Schwimmer bereits zugesagt hat
        $busSignups = CompetitionSignupRequest::where('status', 'active')
            ->where('bus_available', true)
            ->whereHas('responses', fn($q) => $q->where('user_id', $swimmer->id)->where('status', 'attending'))
            ->with(['competition', 'responses' => fn($q) => $q->where('user_id', $swimmer->id)])
            ->get();

        return view('swimmer.dashboard', compact(
            'stats', 'allBests', 'yearBests', 'seasonBests',
            'recent_sessions', 'recent_results',
            'next_competition', 'upcoming_sessions', 'my_pre_absences',
            'goalsTotal', 'goalsAchieved', 'goalsUnnotified',
            'pendingSignups', 'busSignups'
        ));
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

        // Training-Zeiten per discipline+distance
        $trainAll    = SwimmingTime::where('user_id', $userId)
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $trainYear   = SwimmingTime::where('user_id', $userId)
            ->whereYear('created_at', $year)
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $trainSeason = SwimmingTime::where('user_id', $userId)
            ->whereBetween('created_at', [$seasonStart, $seasonEnd])
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        // Wettkampfergebnisse per discipline+distance (nur gültige Zeiten)
        $compAll    = CompetitionResult::where('user_id', $userId)->where('time_ms', '>', 0)
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $compYear   = CompetitionResult::where('user_id', $userId)->where('time_ms', '>', 0)
            ->whereHas('competition', fn($q) => $q->whereYear('date', $year))
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $compSeason = CompetitionResult::where('user_id', $userId)->where('time_ms', '>', 0)
            ->whereHas('competition', fn($q) => $q->whereBetween('date', [$seasonStart, $seasonEnd]))
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')->get()
            ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        return [
            $this->mergeBests($trainAll, $compAll),
            $this->mergeBests($trainYear, $compYear),
            $this->mergeBests($trainSeason, $compSeason),
        ];
    }

    private function mergeBests($trainData, $compData): \Illuminate\Support\Collection
    {
        $keys = $trainData->keys()->merge($compData->keys())->unique();

        return $keys->map(function ($key) use ($trainData, $compData) {
            $tMs = $trainData->get($key)?->best_ms;
            $cMs = $compData->get($key)?->best_ms;

            if ($tMs !== null && $cMs !== null) {
                $bestMs = min($tMs, $cMs);
                $source = $tMs < $cMs ? 'training' : ($cMs < $tMs ? 'competition' : 'both');
            } elseif ($tMs !== null) {
                $bestMs = $tMs;
                $source = 'training';
            } else {
                $bestMs = $cMs;
                $source = 'competition';
            }

            [$disc, $dist] = explode('_', $key, 2);
            return (object)[
                'key'        => $key,
                'discipline' => $disc,
                'distance'   => (int) $dist,
                'best_ms'    => $bestMs,
                'label'      => (self::DISC_LABELS[$disc] ?? $disc) . ' ' . $dist . 'm',
                'source'     => $source,
                'formatted'  => SwimmingTime::formatMs($bestMs),
            ];
        })
        ->sortBy([['discipline', 'asc'], ['distance', 'asc']])
        ->keyBy('label')
        ->values()
        ->keyBy('label');
    }

    public function myTrainings()
    {
        $swimmer          = auth()->user();
        $relevantSessions = $this->buildVisibilityFilter($swimmer->id);
        $exclusionFilter  = $this->buildExclusionFilter($swimmer->id);

        // ── Statistics ──────────────────────────────────────────────────────
        $totalRelevant = TrainingSession::where('date', '<=', today())->tap($relevantSessions)->count();
        $totalAttended = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereHas('session', fn($q) => $q->where('date', '<=', today())->tap($relevantSessions))
            ->count();
        $pct = $totalRelevant > 0 ? round($totalAttended / $totalRelevant * 100) : 0;

        $diaryPendingCount = TrainingSession::where('date', '<=', today())
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
        $excludedSeriesIds = $exclusions->keys();

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

        // ── Upcoming sessions (strictly future, exclusions applied) ──────────
        $upcoming = TrainingSession::where('date', '>', today())
            ->tap($relevantSessions)
            ->tap($exclusionFilter)
            ->with(['coTrainers:id,firstname,lastname', 'trainingGroups'])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $myRegistrations = \App\Models\TrainingSessionRegistration::where('user_id', $swimmer->id)
            ->whereIn('training_session_id', $upcoming->pluck('id'))
            ->pluck('training_session_id');

        $preAbsenceMap = TrainingAttendance::where('user_id', $swimmer->id)
            ->where('pre_absent', true)
            ->whereIn('training_session_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('training_session_id');

        // ── Past sessions: exclude swimmer-cancelled ones, group by month ────
        $filter    = request('filter', 'attended');
        $pastQuery = TrainingSession::where('date', '<=', today())
            ->tap($relevantSessions)
            // Never show sessions the swimmer pre-cancelled
            ->whereDoesntHave('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('pre_absent', true))
            ->with([
                'coTrainers:id,firstname,lastname',
                'attendances' => fn($q) => $q->where('user_id', $swimmer->id),
                'diaries'     => fn($q) => $q->where('user_id', $swimmer->id),
            ])
            ->orderByDesc('date');

        if ($filter === 'attended') {
            $pastQuery->whereHas('attendances', fn($q) => $q->where('user_id', $swimmer->id)->where('attended', true));
        }

        $pastSessions = $pastQuery->paginate(20)->withQueryString();

        return view('swimmer.my-trainings', compact(
            'totalRelevant', 'totalAttended', 'pct', 'diaryPendingCount',
            'trainingSeries', 'excludedSeriesIds',
            'upcoming', 'preAbsenceMap', 'myRegistrations',
            'pastSessions', 'filter'
        ));
    }

    public function cancelSession(Request $request, TrainingSession $session)
    {
        if ($session->date->lte(today())) {
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

        return back()->with('success', 'Absage gespeichert.');
    }

    public function myTimes()
    {
        $swimmer  = auth()->user();
        $filter   = request('filter', 'all');
        $yearVal  = (int) request('year', now()->year);
        $seasonId = (int) request('season_id', 0);
        $seasons  = Season::orderByDesc('start_date')->get();

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

        // Training times (filtered)
        $trainQuery = SwimmingTime::where('user_id', $swimmer->id)->with('trainingSession');
        if ($filter === 'year')   $trainQuery->whereYear('created_at', $yearVal);
        elseif ($seasonDateRange) $trainQuery->whereBetween('created_at', $seasonDateRange);
        $trainTimes = $trainQuery->get();

        // Competition results (filtered by competition date)
        $compQuery = CompetitionResult::where('user_id', $swimmer->id)->where('time_ms', '>', 0)->with('competition');
        if ($filter === 'year')
            $compQuery->whereHas('competition', fn($q) => $q->whereYear('date', $yearVal));
        elseif ($seasonDateRange)
            $compQuery->whereHas('competition', fn($q) => $q->whereBetween('date', $seasonDateRange));
        $compResults = $compQuery->get();

        // Build best per discipline+distance from both sources
        $discLabels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
        $discOrder  = ['F' => 0, 'B' => 1, 'R' => 2, 'S' => 3, 'L' => 4];
        $bestsByKey = [];

        foreach ($trainTimes as $t) {
            $key = $t->discipline . '_' . $t->distance;
            if (!isset($bestsByKey[$key]) || $t->time_ms < $bestsByKey[$key]->ms) {
                $bestsByKey[$key] = (object)[
                    'discipline'       => $t->discipline,
                    'discipline_label' => $discLabels[$t->discipline] ?? $t->discipline,
                    'discipline_order' => $discOrder[$t->discipline] ?? 99,
                    'distance'         => $t->distance,
                    'ms'               => $t->time_ms,
                    'formatted'        => SwimmingTime::formatMs($t->time_ms),
                    'source'           => 'training',
                    'date'             => $t->trainingSession?->date,
                    'label'            => $t->trainingSession?->title ?? 'Training',
                    'location'         => null,
                ];
            }
        }

        foreach ($compResults as $r) {
            $key = $r->discipline . '_' . $r->distance;
            if (!isset($bestsByKey[$key]) || $r->time_ms < $bestsByKey[$key]->ms) {
                $bestsByKey[$key] = (object)[
                    'discipline'       => $r->discipline,
                    'discipline_label' => $discLabels[$r->discipline] ?? $r->discipline,
                    'discipline_order' => $discOrder[$r->discipline] ?? 99,
                    'distance'         => $r->distance,
                    'ms'               => $r->time_ms,
                    'formatted'        => SwimmingTime::formatMs($r->time_ms),
                    'source'           => 'competition',
                    'date'             => $r->competition?->date,
                    'label'            => $r->competition?->name ?? 'Wettkampf',
                    'location'         => $r->competition?->location,
                ];
            }
        }

        $bests = collect($bestsByKey)
            ->sortBy(fn($b) => sprintf('%d_%05d', $b->discipline_order, $b->distance))
            ->values();
        $bestsByDisc = $bests->groupBy('discipline');

        $availableYears = SwimmingTime::where('user_id', $swimmer->id)
            ->selectRaw('YEAR(created_at) as y')->distinct()->pluck('y')->sortDesc();

        return view('swimmer.my-times', compact(
            'filter', 'filterLabel', 'seasons', 'seasonId', 'yearVal', 'availableYears',
            'bests', 'bestsByDisc'
        ));
    }

    public function myCompetitions()
    {
        $swimmer         = auth()->user();
        $swimmerGroupIds = $swimmer->trainingGroups()->pluck('training_groups.id');

        // All competitions assigned to swimmer's training groups OR via direct signup invitation
        $allComps = Competition::where(function ($q) use ($swimmerGroupIds, $swimmer) {
                if ($swimmerGroupIds->isNotEmpty()) {
                    $q->whereHas('trainingGroups', fn($inner) =>
                        $inner->whereIn('training_groups.id', $swimmerGroupIds)
                    );
                }
                $q->orWhereHas('signupRequest.responses', fn($inner) =>
                    $inner->where('user_id', $swimmer->id)
                );
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

        // Load ALL results for accurate PB/SB detection across all competitions
        $raw = CompetitionResult::with('competition')
            ->where('user_id', $swimmer->id)
            ->get();

        $allTimeBests = [];
        $yearBests    = [];
        $seasonBests  = [];

        foreach ($raw as $result) {
            if (!$result->time_ms || $result->time_ms <= 0) continue;
            $key  = $result->discipline . '_' . $result->distance;
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
            $key  = $swim->discipline . '_' . $swim->distance;
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
            $course    = $swim->competition?->course ?? 'Langbahn';
            $gender    = $swim->gender ?? '';
            $ageGroups = collect($swim->result_ids)->map(fn($id) => $resultAgeGroupMap->get($id))->unique()->values();
            $beatenRecords = [];
            foreach (['vereinsrekord', 'landesrekord'] as $type) {
                foreach ($ageGroups as $ag) {
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
        $pageItems = $allComps->forPage($page, $perPage)->values();

        $competitions = new LengthAwarePaginator($pageItems, $allComps->count(), $perPage, $page, [
            'path' => request()->url(),
        ]);

        return view('swimmer.my-competitions', compact('competitions'));
    }

    private function seasonKey(Carbon $date): string
    {
        $month = $date->month;
        $year  = $date->year;
        if ($month >= 4 && $month <= 9) return 'S_' . $year;
        return 'W_' . ($month >= 10 ? $year : $year - 1);
    }

    public function sessionDetail(\App\Models\TrainingSession $session)
    {
        $user            = auth()->user();
        $swimmerGroupIds = $user->trainingGroups()->pluck('training_groups.id');
        $hasGroupAccess  = $swimmerGroupIds->isNotEmpty() && $session->trainingGroups()->whereIn('training_groups.id', $swimmerGroupIds)->exists();
        $hasIndividual   = TrainingSessionSwimmer::where('user_id', $user->id)
            ->where(function ($q) use ($session) {
                $q->where('training_session_id', $session->id);
                if ($session->recurrence_group_id) {
                    $q->orWhere('recurrence_group_id', $session->recurrence_group_id);
                }
            })->exists();

        if (!$hasGroupAccess && !$hasIndividual) {
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
}
