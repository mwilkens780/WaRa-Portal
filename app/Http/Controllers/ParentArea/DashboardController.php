<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\CompetitionResult;
use App\Models\CompetitionSignupRequest;
use App\Models\SwimmingTime;
use App\Models\TrainingAttendance;
use App\Services\CompetitionResultGrouper;
use App\Services\SwimmerPages;

class DashboardController extends Controller
{
    public function index()
    {
        $parent = auth()->user();
        $children = $parent->children()->where('active', true)->get();

        $childData = [];
        foreach ($children as $child) {
            $pendingSignups = CompetitionSignupRequest::where('status', 'active')
                ->whereHas('competition', fn($q) => $q->upcomingOrRunning())
                ->whereHas('responses', fn($q) => $q->where('user_id', $child->id)->where('status', 'pending'))
                ->count();

            $childData[$child->id] = [
                'user' => $child,
                'trainings_this_month' => TrainingAttendance::where('user_id', $child->id)
                    ->where('attended', true)
                    ->whereMonth('created_at', now()->month)
                    ->count(),
                'recent_bests' => SwimmingTime::where('user_id', $child->id)
                    ->where('is_personal_best', true)
                    ->orderByDesc('created_at')
                    ->limit(3)
                    ->get(),
                'recent_results' => CompetitionResultGrouper::forSwimmer(
                    CompetitionResult::with('competition')
                        ->where('user_id', $child->id)
                        ->where('time_ms', '>', 0)
                        ->get()
                )->take(3),
                'pending_signups' => $pendingSignups,
            ];
        }

        $new_records = $this->loadNewRecords();

        return view('parent.dashboard', compact('children', 'childData', 'new_records'));
    }

    private function loadNewRecords(): \Illuminate\Support\Collection
    {
        $month = now()->month;
        $year  = now()->year;
        if ($month >= 4 && $month <= 9) {
            [$seasonStart, $seasonEnd] = ["{$year}-04-01", "{$year}-09-30"];
        } else {
            $seasonStart = $month >= 10 ? "{$year}-10-01" : ($year - 1) . "-10-01";
            $seasonEnd   = $month >= 10 ? ($year + 1) . "-03-31" : "{$year}-03-31";
        }
        return CompetitionResult::with(['user', 'competition'])
            ->where(fn($q) => $q->where('breaks_vereinsrekord', true)->orWhere('breaks_landesrekord', true))
            ->whereNull('age_group')
            ->whereHas('competition', fn($q) => $q->whereBetween('date', [$seasonStart, $seasonEnd]))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
    }

    // Zeiten und Wettkaempfe: dieselben Seiten wie fuer den Schwimmer selbst,
    // nur mit dem Kind als Betrachtetem (SwimmerPages). Fruehere eigene
    // Eltern-Kopien zeigten nur Trainingszeiten und keine Saisonauswahl.
    public function childTimes(int $childId, SwimmerPages $pages)
    {
        $child = auth()->user()->children()->findOrFail($childId);

        return view('swimmer.my-times', $pages->times($child) + [
            'subject'    => $child,
            'asParent'   => true,
            'timesRoute' => ['parent.child.times', ['childId' => $child->id]],
        ]);
    }

    public function childCompetitions(int $childId, SwimmerPages $pages)
    {
        $child = auth()->user()->children()->findOrFail($childId);

        return view('swimmer.my-competitions', $pages->competitions($child) + [
            'subject'  => $child,
            'asParent' => true,
        ]);
    }
}
