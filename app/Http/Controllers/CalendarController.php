<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Competition;
use App\Models\Season;
use App\Models\TrainingSession;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $seasons       = Season::orderByDesc('start_date')->get();
        $currentSeason = Season::current() ?? $seasons->first();

        $mode = $request->get('mode', 'season');
        $view = $request->get('view', 'month');

        if ($mode === 'season') {
            $activeSeason = Season::find($request->get('season_id')) ?? $currentSeason;
            $activeYear   = null;

            // Season picker changed (season_id present, no explicit date) → jump to season start
            // First load (no season_id, no year) → stay on today's date (no merge needed)
            if ($request->has('season_id') && !$request->has('year') && $activeSeason) {
                $request->merge([
                    'year'  => $activeSeason->start_date->year,
                    'month' => $activeSeason->start_date->month,
                    'week'  => $activeSeason->start_date->isoWeek(),
                ]);
            }
        } else {
            $activeYear   = (int) $request->get('year', now()->year);
            $activeSeason = null;
        }

        if ($view === 'week') {
            return $this->weekView($request, $seasons, $activeSeason, $activeYear, $mode);
        }
        if ($view === 'month') {
            return $this->monthView($request, $seasons, $activeSeason, $activeYear, $mode);
        }
        return $this->overviewView($request, $seasons, $activeSeason, $activeYear, $mode);
    }

    // ── Week view ─────────────────────────────────────────────────────────

    private function weekView(Request $request, $seasons, $activeSeason, $activeYear, string $mode)
    {
        $view = 'week';
        $year = (int) $request->get('year', now()->year);
        $week = (int) $request->get('week', now()->isoWeek());

        $weekStart = Carbon::now()->setISODate($year, $week)->startOfDay();
        $weekEnd   = $weekStart->copy()->addDays(6);

        if ($mode === 'season') {
            $activeSeason = Season::forDate($weekStart) ?? $activeSeason;
        }

        $prevWeekStart = $weekStart->copy()->subWeek();
        $nextWeekStart = $weekStart->copy()->addWeek();

        $eventMap   = $this->buildEventMap($weekStart, $weekEnd);
        $holidayMap = HolidayService::buildMap($weekStart, $weekEnd);

        $days = [];
        $cursor = $weekStart->copy();
        for ($d = 0; $d < 7; $d++) {
            $key    = $cursor->format('Y-m-d');
            $days[] = [
                'date'      => $cursor->copy(),
                'isToday'   => $cursor->isToday(),
                'isWeekend' => in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]),
                'events'    => $eventMap[$key] ?? [],
                'holiday'   => $holidayMap[$key]['holiday']     ?? null,
                'vacSH'     => $holidayMap[$key]['vacation_sh'] ?? null,
                'vacHH'     => $holidayMap[$key]['vacation_hh'] ?? null,
            ];
            $cursor->addDay();
        }

        return view('calendar.index', compact(
            'view', 'mode', 'seasons', 'activeSeason', 'activeYear',
            'year', 'week', 'weekStart', 'weekEnd', 'days',
            'prevWeekStart', 'nextWeekStart'
        ));
    }

    // ── Month view ────────────────────────────────────────────────────────

    private function monthView(Request $request, $seasons, $activeSeason, $activeYear, string $mode)
    {
        $view  = 'month';
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $firstOfMonth = Carbon::create($year, $month, 1);
        $gridStart    = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd      = $firstOfMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        if ($mode === 'season') {
            $activeSeason = Season::forDate($firstOfMonth) ?? $activeSeason;
        }

        $eventMap   = $this->buildEventMap($gridStart, $gridEnd);
        $holidayMap = HolidayService::buildMap($gridStart, $gridEnd);

        $weeks  = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $wk = [];
            for ($d = 0; $d < 7; $d++) {
                $key  = $cursor->format('Y-m-d');
                $wk[] = [
                    'date'    => $cursor->copy(),
                    'inMonth' => $cursor->month === $month,
                    'isToday' => $cursor->isToday(),
                    'events'  => $eventMap[$key] ?? [],
                    'holiday' => $holidayMap[$key]['holiday']     ?? null,
                    'vacSH'   => $holidayMap[$key]['vacation_sh'] ?? null,
                    'vacHH'   => $holidayMap[$key]['vacation_hh'] ?? null,
                ];
                $cursor->addDay();
            }
            $weeks[] = $wk;
        }

        $prevMonth = $firstOfMonth->copy()->subMonth();
        $nextMonth = $firstOfMonth->copy()->addMonth();

        return view('calendar.index', compact(
            'view', 'mode', 'seasons', 'activeSeason', 'activeYear',
            'year', 'month', 'firstOfMonth', 'weeks', 'prevMonth', 'nextMonth'
        ));
    }

    // ── Overview ──────────────────────────────────────────────────────────

    private function overviewView(Request $request, $seasons, $activeSeason, $activeYear, string $mode)
    {
        $view = 'overview';
        if ($mode === 'season' && $activeSeason) {
            $months     = $activeSeason->months();
            $rangeStart = $activeSeason->start_date;
            $rangeEnd   = $activeSeason->end_date;
        } else {
            $y      = $activeYear ?? now()->year;
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[] = Carbon::create($y, $m, 1);
            }
            $rangeStart = Carbon::create($y, 1, 1);
            $rangeEnd   = Carbon::create($y, 12, 31);
        }

        $eventMap = $this->buildEventMap($rangeStart, $rangeEnd);

        $miniMonths = [];
        foreach ($months as $firstOfMonth) {
            $gridStart = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd   = $firstOfMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $wks       = [];
            $cursor    = $gridStart->copy();
            while ($cursor->lte($gridEnd)) {
                $wk = [];
                for ($d = 0; $d < 7; $d++) {
                    $wk[] = [
                        'date'    => $cursor->copy(),
                        'inMonth' => $cursor->month === $firstOfMonth->month,
                        'isToday' => $cursor->isToday(),
                        'count'   => count($eventMap[$cursor->format('Y-m-d')] ?? []),
                        'types'   => collect($eventMap[$cursor->format('Y-m-d')] ?? [])->pluck('color')->unique()->values()->toArray(),
                    ];
                    $cursor->addDay();
                }
                $wks[] = $wk;
            }
            $miniMonths[] = ['month' => $firstOfMonth, 'weeks' => $wks];
        }

        return view('calendar.index', compact(
            'view', 'mode', 'seasons', 'activeSeason', 'activeYear',
            'miniMonths'
        ));
    }

    // ── Build event map: date → [events] ─────────────────────────────────

    private function buildEventMap(Carbon $from, Carbon $to): array
    {
        $map    = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $map[$cursor->format('Y-m-d')] = [];
            $cursor->addDay();
        }

        $isAdmin   = auth()->user()->isAdmin();
        $isTrainer = in_array(auth()->user()->role, ['trainer', 'admin']);

        if ($isTrainer) {
            $sessions = TrainingSession::with(['coTrainers:id,firstname,lastname', 'trainingGroups:id,name,color'])
                ->whereBetween('date', [$from, $to])
                ->when(!$isAdmin, fn($q) => $q->whereHas('coTrainers', fn($q2) => $q2->where('users.id', auth()->id())))
                ->orderBy('date')->orderBy('start_time')
                ->get();

            foreach ($sessions as $s) {
                $key = $s->date->format('Y-m-d');
                if (!isset($map[$key])) continue;
                $groups = $s->trainingGroups->pluck('name')->implode(', ');
                $map[$key][] = [
                    'type'    => 'training',
                    'color'   => 'blue',
                    'time'    => $s->start_time ? substr($s->start_time, 0, 5) : null,
                    'title'   => $s->title,
                    'sub'     => $groups,
                    'trainer' => $s->trainer ? $s->trainer->firstname . ' ' . $s->trainer->lastname : null,
                    'url'     => route('trainer.sessions.show', $s),
                ];
            }
        }

        $competitions = Competition::whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        foreach ($competitions as $c) {
            $cStart = max($c->date, $from->copy()->startOfDay());
            $cEnd   = $c->date_end ? min($c->date_end, $to->copy()->endOfDay()) : $c->date;
            $cur    = $cStart->copy();
            while ($cur->lte($cEnd)) {
                $key = $cur->format('Y-m-d');
                if (isset($map[$key])) {
                    $map[$key][] = [
                        'type'  => 'competition',
                        'color' => 'red',
                        'time'  => null,
                        'title' => $c->name,
                        'sub'   => $c->location,
                        'url'   => route('admin.competitions.show', $c),
                    ];
                }
                $cur->addDay();
            }
        }

        $calEvents = CalendarEvent::whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')->orderBy('start_time')
            ->get();

        foreach ($calEvents as $e) {
            $eStart = max($e->start_date, $from->copy()->startOfDay());
            $eEnd   = $e->end_date ? min($e->end_date, $to->copy()->endOfDay()) : $e->start_date;
            $cur    = $eStart->copy();
            while ($cur->lte($eEnd)) {
                $key = $cur->format('Y-m-d');
                if (isset($map[$key])) {
                    $map[$key][] = [
                        'type'  => 'event',
                        'color' => $e->type_color,
                        'time'  => $e->start_time ? substr($e->start_time, 0, 5) : null,
                        'title' => $e->title,
                        'sub'   => $e->type_label,
                        'url'   => null,
                        'id'    => $e->id,
                    ];
                }
                $cur->addDay();
            }
        }

        foreach ($map as &$dayEvents) {
            usort($dayEvents, fn($a, $b) => ($a['time'] ?? '99:99') <=> ($b['time'] ?? '99:99'));
        }

        return $map;
    }
}
