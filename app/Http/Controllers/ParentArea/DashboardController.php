<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\TrainingAttendance;
use App\Models\SwimmingTime;
use App\Models\CompetitionResult;
use App\Services\CompetitionResultGrouper;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    public function index()
    {
        $parent = auth()->user();
        $children = $parent->children()->where('active', true)->get();

        $childData = [];
        foreach ($children as $child) {
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
            ];
        }

        return view('parent.dashboard', compact('children', 'childData'));
    }

    public function childTimes(int $childId)
    {
        $parent = auth()->user();
        $child = $parent->children()->findOrFail($childId);

        $times = SwimmingTime::where('user_id', $child->id)
            ->with('trainingSession')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('parent.child-times', compact('child', 'times'));
    }

    public function childCompetitions(int $childId)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        $raw      = CompetitionResult::with('competition')->where('user_id', $child->id)->get();
        $allSwims = CompetitionResultGrouper::forSwimmer($raw);
        $grouped  = $allSwims->groupBy('competition_id');

        $competitionIds = $grouped->keys()->toArray();
        $perPage  = 10;
        $page     = (int) request('page', 1);

        $allComps  = \App\Models\Competition::whereIn('id', $competitionIds)->orderByDesc('date')->get();
        $pageItems = $allComps->forPage($page, $perPage)->values();
        foreach ($pageItems as $comp) {
            $comp->processedResults = $grouped->get($comp->id, collect());
        }

        $competitions = new LengthAwarePaginator($pageItems, $allComps->count(), $perPage, $page, [
            'path' => request()->url(),
        ]);

        return view('parent.child-competitions', compact('child', 'competitions'));
    }
}
