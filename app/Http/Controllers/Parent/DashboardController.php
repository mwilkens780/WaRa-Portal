<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\TrainingAttendance;
use App\Models\SwimmingTime;
use App\Models\CompetitionResult;

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
                'recent_results' => CompetitionResult::with('competition')
                    ->where('user_id', $child->id)
                    ->orderByDesc('created_at')
                    ->limit(3)
                    ->get(),
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
        $child = $parent->children()->findOrFail($childId);

        $results = CompetitionResult::with('competition')
            ->where('user_id', $child->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('parent.child-competitions', compact('child', 'results'));
    }
}
