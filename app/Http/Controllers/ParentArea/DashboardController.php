<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSignupRequest;
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
            $pendingSignups = CompetitionSignupRequest::where('status', 'active')
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
        $parent        = auth()->user();
        $child         = $parent->children()->findOrFail($childId);
        $childGroupIds = $child->trainingGroups()->pluck('training_groups.id');

        $allComps = \App\Models\Competition::where(function ($q) use ($childGroupIds, $child) {
                if ($childGroupIds->isNotEmpty()) {
                    $q->whereHas('trainingGroups', fn($inner) =>
                        $inner->whereIn('training_groups.id', $childGroupIds)
                    );
                }
                $q->orWhereHas('signupRequest.responses', fn($inner) =>
                    $inner->where('user_id', $child->id)
                );
            })
            ->with([
                'signupRequest' => fn($q) => $q->with([
                    'responses' => fn($q) => $q->where('user_id', $child->id),
                ]),
                'entries'  => fn($q) => $q->where('user_id', $child->id),
                'events',
            ])
            ->orderByDesc('date')
            ->get();

        $raw      = CompetitionResult::with('competition')->where('user_id', $child->id)->get();
        $allSwims = CompetitionResultGrouper::forSwimmer($raw);
        $grouped  = $allSwims->groupBy('competition_id');

        foreach ($allComps as $comp) {
            $comp->processedResults = $grouped->get($comp->id, collect());
        }

        $perPage   = 10;
        $page      = (int) request('page', 1);
        $pageItems = $allComps->forPage($page, $perPage)->values();

        $competitions = new LengthAwarePaginator($pageItems, $allComps->count(), $perPage, $page, [
            'path' => request()->url(),
        ]);

        return view('parent.child-competitions', compact('child', 'competitions'));
    }
}
