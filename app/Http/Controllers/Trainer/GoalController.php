<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\GroupGoal;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmerGoalComment;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $trainer  = auth()->user();
        $seasons  = Season::orderByDesc('start_date')->get();

        $activeSeason = $request->filled('season_id')
            ? $seasons->firstWhere('id', $request->get('season_id'))
            : (Season::current() ?? $seasons->first());

        $groups = $trainer->isAdmin()
            ? TrainingGroup::with(['swimmers' => fn($q) => $q->where('active', true)->orderBy('lastname')->orderBy('firstname')])
                ->orderBy('name')->get()
            : TrainingGroup::whereHas('trainers', fn($q) => $q->where('users.id', $trainer->id))
                ->with(['swimmers' => fn($q) => $q->where('active', true)->orderBy('lastname')->orderBy('firstname')])
                ->orderBy('name')->get();

        $swimmerIds = $groups->flatMap(fn($g) => $g->swimmers->pluck('id'))->unique();

        $goalsBySwimmer = SwimmerGoal::whereIn('user_id', $swimmerIds)
            ->where('season_id', $activeSeason?->id)
            ->with(['user', 'comments.trainer'])
            ->orderByRaw("FIELD(type,'time','qualification','free')")
            ->orderBy('achieved')
            ->get()
            ->groupBy('user_id');

        $groupGoals = GroupGoal::whereIn('training_group_id', $groups->pluck('id'))
            ->where('season_id', $activeSeason?->id)
            ->with('createdBy')
            ->orderBy('achieved')
            ->orderBy('created_at')
            ->get()
            ->groupBy('training_group_id');

        $trainingGroupGoals = TrainingGroupGoal::whereIn('training_group_id', $groups->pluck('id'))
            ->where('active', true)
            ->with(['evaluations' => function ($q) use ($swimmerIds) {
                $q->whereIn('user_id', $swimmerIds)->with('user:id,firstname,lastname');
            }])
            ->orderBy('training_group_id')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('training_group_id');

        return view('trainer.goals', compact(
            'groups', 'goalsBySwimmer', 'groupGoals', 'trainingGroupGoals', 'seasons', 'activeSeason'
        ));
    }

    public function storeComment(Request $request, SwimmerGoal $goal)
    {
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);

        SwimmerGoalComment::updateOrCreate(
            ['swimmer_goal_id' => $goal->id, 'trainer_id' => auth()->id()],
            ['comment' => $data['comment']]
        );

        return back()->with('success', 'Kommentar gespeichert.');
    }

    public function storeGroupGoal(Request $request)
    {
        $data = $request->validate([
            'training_group_id' => ['required', 'exists:training_groups,id'],
            'season_id'         => ['required', 'exists:seasons,id'],
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'target_count'      => ['nullable', 'integer', 'min:1'],
        ]);

        GroupGoal::create([...$data, 'created_by_id' => auth()->id()]);

        return back()->with('success', 'Gruppenziel gespeichert.');
    }

    public function updateGroupGoal(Request $request, GroupGoal $groupGoal)
    {
        $data = $request->validate([
            'achieved_count' => ['nullable', 'integer', 'min:0'],
            'achieved'       => ['nullable', 'boolean'],
        ]);

        $groupGoal->update([
            'achieved_count' => $data['achieved_count'] ?? $groupGoal->achieved_count,
            'achieved'       => isset($data['achieved']) ? (bool)$data['achieved'] : $groupGoal->achieved,
        ]);

        return back()->with('success', 'Gruppenziel aktualisiert.');
    }

    public function destroyGroupGoal(GroupGoal $groupGoal)
    {
        $groupGoal->delete();
        return back()->with('success', 'Gruppenziel gelöscht.');
    }
}
