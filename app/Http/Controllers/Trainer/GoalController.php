<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmerGoalComment;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingGroupGoalEvaluation;
use App\Models\User;
use App\Services\GroupRoster;
use Illuminate\Http\Request;

/**
 * Ziele-Seite der Trainer.
 *
 * Zwei Kategorien je Trainingsgruppe:
 *  - Leistungskriterien: von der Gruppe festgelegt, je Schwimmer und Saison
 *    mit erreicht / nicht erreicht bewertet. Entscheiden ueber Verbleib oder
 *    Wechsel der Gruppe.
 *  - Persoenliche Ziele: legt jeder Schwimmer selbst an, Trainer kommentieren.
 *
 * Beides ist saisonbezogen; eine neue Saison beginnt ohne Bewertungen.
 */
class GoalController extends Controller
{
    public function __construct(private GroupRoster $roster) {}

    public function index(Request $request)
    {
        $trainer  = auth()->user();
        $seasons  = Season::orderByDesc('start_date')->get();

        $activeSeason = $request->filled('season_id')
            ? $seasons->firstWhere('id', $request->get('season_id'))
            : (view()->shared('appCurrentSeason') ?? Season::current() ?? $seasons->first());

        $groups = $trainer->isAdmin()
            ? TrainingGroup::with('swimmers:id')->orderBy('name')->get()
            : TrainingGroup::whereHas('trainers', fn($q) => $q->where('users.id', $trainer->id))
                ->with('swimmers:id')
                ->orderBy('name')->get();

        // Wer in der gewaehlten Saison zur Gruppe gehoerte. Fuer vergangene
        // Saisons der eingefrorene Stand zum Saisonende - nicht die heutige Gruppe.
        $rosters = $groups->mapWithKeys(fn($g) => [$g->id => $this->roster->swimmersFor($g, $activeSeason)]);
        $isPast  = $this->roster->isPast($activeSeason);

        $swimmerIds = $rosters->flatMap(fn($r) => $r['swimmers']->pluck('id'))->unique();

        $goalsBySwimmer = SwimmerGoal::whereIn('user_id', $swimmerIds)
            ->where('season_id', $activeSeason?->id)
            ->with(['user', 'comments.trainer'])
            ->orderByRaw("FIELD(type,'time','qualification','free')")
            ->orderBy('achieved')
            ->get()
            ->groupBy('user_id');

        $criteria = $this->roster->criteriaQuery($groups->pluck('id'), $activeSeason)
            ->with(['evaluations' => function ($q) use ($swimmerIds, $activeSeason) {
                $q->whereIn('user_id', $swimmerIds)
                  ->where('season_id', $activeSeason?->id);
            }])
            ->orderBy('training_group_id')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('training_group_id');

        return view('trainer.goals', compact(
            'groups', 'rosters', 'isPast', 'goalsBySwimmer', 'criteria', 'seasons', 'activeSeason'
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

    // ── Leistungskriterien ───────────────────────────────────────────────

    public function storeGroupGoal(Request $request)
    {
        $data = $request->validate([
            'training_group_id' => ['required', 'exists:training_groups,id'],
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'target_value'      => ['nullable', 'string', 'max:255'],
        ]);

        $group = TrainingGroup::findOrFail($data['training_group_id']);
        $this->authorizeGroup($group);

        TrainingGroupGoal::create([
            'training_group_id' => $group->id,
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'target_value'      => $data['target_value'] ?? null,
            'type'              => filled($data['target_value'] ?? null) ? 'quantitative' : 'qualitative',
            'sort_order'        => (int) $group->goals()->max('sort_order') + 1,
            'active'            => true,
        ]);

        return back()->with('success', 'Leistungskriterium angelegt.');
    }

    public function updateGroupGoal(Request $request, TrainingGroupGoal $groupGoal)
    {
        $this->authorizeGroup($groupGoal->group);

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'target_value' => ['nullable', 'string', 'max:255'],
        ]);

        $groupGoal->update([
            ...$data,
            'type' => filled($data['target_value'] ?? null) ? 'quantitative' : 'qualitative',
        ]);

        return back()->with('success', 'Leistungskriterium aktualisiert.');
    }

    public function destroyGroupGoal(TrainingGroupGoal $groupGoal)
    {
        $this->authorizeGroup($groupGoal->group);

        return back()->with('success', $groupGoal->retire() === 'archived'
            ? 'Leistungskriterium entfernt. Die Bewertungen vergangener Saisons bleiben erhalten.'
            : 'Leistungskriterium gelöscht.');
    }

    public function evaluate(Request $request, TrainingGroupGoal $groupGoal, User $user)
    {
        $this->authorizeGroup($groupGoal->group);

        $data = $request->validate([
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'achieved'  => ['nullable', 'in:0,1'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        TrainingGroupGoalEvaluation::record(
            $groupGoal, $user->id, 'trainer', (int) $data['season_id'],
            $data['achieved'] ?? null, $data['notes'] ?? null, auth()->id(),
        );

        return back()->with('success', 'Bewertung gespeichert.');
    }

    private function authorizeGroup(?TrainingGroup $group): void
    {
        abort_if(!$group || !$group->canEdit(auth()->user()), 403, 'Kein Zugriff auf diese Trainingsgruppe.');
    }
}
