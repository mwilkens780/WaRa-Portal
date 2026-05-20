<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
use App\Models\SwimmingTime;
use App\Models\CompetitionResult;

class DashboardController extends Controller
{
    public function index()
    {
        $swimmer = auth()->user();

        $totalSessions   = TrainingSession::count();
        $attendedTotal   = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)->count();
        $attendedYear    = TrainingAttendance::where('user_id', $swimmer->id)->where('attended', true)
            ->whereYear('created_at', now()->year)->count();
        $sessionsYear    = TrainingSession::whereYear('date', now()->year)->count();

        $stats = [
            'trainings_total'      => $attendedTotal,
            'trainings_this_year'  => $attendedYear,
            'personal_bests'       => SwimmingTime::where('user_id', $swimmer->id)->where('is_personal_best', true)->count(),
            'competitions'         => CompetitionResult::where('user_id', $swimmer->id)->distinct('competition_id')->count(),
            'participation_pct'    => $totalSessions > 0 ? round($attendedTotal / $totalSessions * 100) : 0,
            'participation_year'   => $sessionsYear > 0 ? round($attendedYear / $sessionsYear * 100) : 0,
        ];

        // Persönliche Bestzeiten (alle Zeiten)
        $personal_bests = SwimmingTime::where('user_id', $swimmer->id)
            ->where('is_personal_best', true)
            ->orderBy('discipline')->orderBy('distance')
            ->get()->groupBy(fn($t) => $t->discipline_label . ' ' . $t->distance . 'm');

        // Saison-Bestzeiten
        $season_bests = SwimmingTime::where('user_id', $swimmer->id)
            ->thisSeason()
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')
            ->get()->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        // Jahres-Bestzeiten
        $year_bests = SwimmingTime::where('user_id', $swimmer->id)
            ->thisYear()
            ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')
            ->get()->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $recent_trainings = TrainingAttendance::with('session.trainer')
            ->where('user_id', $swimmer->id)->where('attended', true)
            ->orderByDesc('created_at')->limit(5)->get();

        $recent_results = CompetitionResult::with('competition')
            ->where('user_id', $swimmer->id)
            ->orderByDesc('created_at')->limit(5)->get();

        return view('swimmer.dashboard', compact(
            'stats', 'personal_bests', 'season_bests', 'year_bests',
            'recent_trainings', 'recent_results'
        ));
    }

    public function myTimes()
    {
        $swimmer = auth()->user();
        $filter  = request('filter', 'all'); // all | year | season

        $query = SwimmingTime::where('user_id', $swimmer->id)->with('trainingSession');

        $query = match($filter) {
            'year'   => $query->thisYear(),
            'season' => $query->thisSeason(),
            default  => $query,
        };

        $times = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        // Bestzeiten-Tabelle je nach Filter
        $bestsQuery = SwimmingTime::where('user_id', $swimmer->id);
        $bestsQuery = match($filter) {
            'year'   => $bestsQuery->thisYear(),
            'season' => $bestsQuery->thisSeason(),
            default  => $bestsQuery,
        };

        $bests = $bestsQuery->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
            ->groupBy('discipline', 'distance')
            ->get()->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

        $seasonLabel = SwimmingTime::currentSeasonLabel();

        return view('swimmer.my-times', compact('times', 'bests', 'filter', 'seasonLabel'));
    }

    public function myCompetitions()
    {
        $results = CompetitionResult::with('competition')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')->paginate(20);

        return view('swimmer.my-competitions', compact('results'));
    }

    public function sessionDetail(\App\Models\TrainingSession $session)
    {
        $session->load('trainer', 'diaries.user');
        $diary = $session->diaryFor(auth()->id());

        return view('swimmer.session-detail', compact('session', 'diary'));
    }
}
