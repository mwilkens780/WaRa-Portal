<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupMottoWeek;
use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingGroupGoalEvaluation;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\GroupImportService;
use App\Services\GroupRoster;
use App\Services\MottoWeekService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingGroupController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────

    public function index()
    {
        $query = TrainingGroup::withCount(['trainers', 'swimmers'])
            ->with('trainers:id,firstname,lastname')
            ->orderBy('name');

        // Trainers only see their own groups
        if (!auth()->user()->isAdmin()) {
            $query->whereHas('trainers', fn($q) => $q->where('users.id', auth()->id()));
        }

        // Group by group_type in defined order
        $all = $query->get();
        $grouped = collect(array_keys(TrainingGroup::GROUP_TYPES))
            ->mapWithKeys(fn($type) => [
                $type => $all->where('group_type', $type)->values(),
            ])
            ->filter(fn($items) => $items->isNotEmpty());

        return view('admin.training-groups.index', [
            'groups'  => $all,
            'grouped' => $grouped,
        ]);
    }

    // ── Create / Store (admin only) ──────────────────────────────────────

    public function create()
    {
        $trainers = User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();
        $swimmers = User::where('role', 'schwimmer')->where('active', true)
            ->whereDoesntHave('trainingGroups')
            ->orderBy('lastname')->orderBy('firstname')->get();

        return view('admin.training-groups.create', compact('trainers', 'swimmers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color'       => ['required', 'in:' . implode(',', array_keys(TrainingGroup::COLORS))],
            'group_type'  => ['required', 'in:' . implode(',', array_keys(TrainingGroup::GROUP_TYPES))],
            'active'      => ['boolean'],
            'webclub_id'  => ['nullable', 'integer', 'min:1', 'unique:training_groups,webclub_id'],
            'trainers'    => ['nullable', 'array'],
            'trainers.*'  => ['exists:users,id'],
            'swimmers'    => ['nullable', 'array'],
            'swimmers.*'  => ['exists:users,id'],
        ]);

        $data['active']     = $request->boolean('active', true);
        $data['webclub_id'] = $request->filled('webclub_id') ? (int) $request->input('webclub_id') : null;

        $group = TrainingGroup::create($data);
        $group->trainers()->sync($request->input('trainers', []));
        $group->swimmers()->sync($request->input('swimmers', []));

        return redirect()->route('admin.training-groups.show', $group)
            ->with('success', "Trainingsgruppe \"{$group->name}\" angelegt.");
    }

    // ── Show ─────────────────────────────────────────────────────────────

    public function show(TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $trainingGroup->load(['trainers:id,firstname,lastname,role']);

        // Only active swimmers
        $activeSwimmers = $trainingGroup->swimmers()
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['users.id', 'firstname', 'lastname', 'birth_date']);

        $recentSessions   = $trainingGroup->sessions()->orderByDesc('date')->limit(10)->get();
        $upcomingSessions = $trainingGroup->sessions()->where('date', '>=', now())->orderBy('date')->limit(5)->get();

        // Leistungskriterien mit den Bewertungen der gewaehlten Saison.
        // Jede Saison beginnt leer - keine Uebernahme aus der Vorsaison.
        $season = view()->shared('appCurrentSeason') ?? Season::current();

        // Fuer abgeschlossene Saisons die Aufstellung zum Saisonende statt der
        // heutigen Gruppe - Wechsel, Aus- und Eintritte veraendern sie nicht.
        $roster           = app(GroupRoster::class);
        $criteriaRoster   = $roster->swimmersFor($trainingGroup, $season);
        $criteriaSwimmers = $criteriaRoster['swimmers'];
        $criteriaPast     = $roster->isPast($season);

        $evalUserIds = $criteriaSwimmers->pluck('id')
            ->merge($criteriaRoster['leavers']->map(fn($l) => $l->user->id));

        $goals = $roster->criteriaQuery(collect([$trainingGroup->id]), $season)
            ->with(['evaluations' => function ($q) use ($evalUserIds, $season) {
                $q->whereIn('user_id', $evalUserIds)
                  ->where('season_id', $season?->id);
            }])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return view('admin.training-groups.show', compact(
            'trainingGroup', 'activeSwimmers', 'recentSessions', 'upcomingSessions', 'goals', 'season',
            'criteriaSwimmers', 'criteriaRoster', 'criteriaPast'
        ));
    }

    // ── Goal management ──────────────────────────────────────────────────

    public function storeGoal(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'target_value' => ['nullable', 'string', 'max:255'],
        ]);

        // Bewertet wird nur noch erreicht / nicht erreicht; die Art ergibt
        // sich allein daraus, ob ein Zielwert angegeben ist.
        $data['type'] = filled($data['target_value'] ?? null) ? 'quantitative' : 'qualitative';

        $data['training_group_id'] = $trainingGroup->id;
        $data['sort_order'] = $trainingGroup->goals()->max('sort_order') + 1;

        TrainingGroupGoal::create($data);

        return back()->with('success', 'Ziel hinzugefügt.');
    }

    public function updateGoal(Request $request, TrainingGroup $trainingGroup, TrainingGroupGoal $goal)
    {
        $this->authorizeGroup($trainingGroup);
        abort_if($goal->training_group_id !== $trainingGroup->id, 404);

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'target_value' => ['nullable', 'string', 'max:255'],
        ]);

        // Bewertet wird nur noch erreicht / nicht erreicht; die Art ergibt
        // sich allein daraus, ob ein Zielwert angegeben ist.
        $data['type'] = filled($data['target_value'] ?? null) ? 'quantitative' : 'qualitative';

        $goal->update($data);

        return back()->with('success', 'Ziel aktualisiert.');
    }

    public function destroyGoal(TrainingGroup $trainingGroup, TrainingGroupGoal $goal)
    {
        $this->authorizeGroup($trainingGroup);
        abort_if($goal->training_group_id !== $trainingGroup->id, 404);

        return back()->with('success', $goal->retire() === 'archived'
            ? 'Leistungskriterium entfernt. Die Bewertungen vergangener Saisons bleiben erhalten.'
            : 'Leistungskriterium gelöscht.');
    }

    public function storeTrainerEvaluation(Request $request, TrainingGroup $trainingGroup, TrainingGroupGoal $goal, User $user)
    {
        $this->authorizeGroup($trainingGroup);
        abort_if($goal->training_group_id !== $trainingGroup->id, 404);

        $data = $request->validate([
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'achieved'  => ['nullable', 'in:0,1'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        TrainingGroupGoalEvaluation::record(
            $goal, $user->id, 'trainer', (int) $data['season_id'],
            $data['achieved'] ?? null, $data['notes'] ?? null, auth()->id(),
        );

        return back()->with('success', 'Bewertung gespeichert.');
    }

    // ── Remove single swimmer ──────────────────────────────────────────────

    public function removeSwimmer(TrainingGroup $trainingGroup, User $user)
    {
        $this->authorizeGroup($trainingGroup);
        $trainingGroup->swimmers()->detach($user->id);

        return back()->with('success', "{$user->firstname} {$user->lastname} aus der Gruppe entfernt.");
    }

    // ── CSV-Import ────────────────────────────────────────────────────────

    public function importCsvUpload(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);

        $path     = $request->file('csv_file')->store('group-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $service = new GroupImportService();
            $parsed  = $service->parse($fullPath, $trainingGroup);
        } finally {
            \Storage::disk('local')->delete($path);
        }

        if (empty($parsed['rows']) && empty($parsed['to_remove'])) {
            return back()->withErrors(['csv_file' => 'Keine verwertbaren Zeilen in der Datei gefunden.']);
        }

        session(['group_import' => array_merge($parsed, ['group_id' => $trainingGroup->id])]);

        return redirect()->route('admin.training-groups.csv-preview', $trainingGroup);
    }

    public function importCsvPreview(TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        $data = session('group_import');

        if (!$data || ($data['group_id'] ?? null) !== $trainingGroup->id) {
            return redirect()->route('admin.training-groups.show', $trainingGroup)
                ->with('error', 'Keine Import-Daten. Bitte CSV erneut hochladen.');
        }

        return view('admin.training-groups.import-csv', [
            'trainingGroup' => $trainingGroup,
            'rows'          => $data['rows'],
            'toRemove'      => $data['to_remove'],
        ]);
    }

    public function importCsvExecute(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        $data = session('group_import');

        if (!$data || ($data['group_id'] ?? null) !== $trainingGroup->id) {
            return redirect()->route('admin.training-groups.show', $trainingGroup)
                ->with('error', 'Sitzung abgelaufen. Bitte CSV erneut hochladen.');
        }

        $service = new GroupImportService();
        $result  = $service->execute(
            $trainingGroup,
            $data['rows'],
            $data['to_remove'],
            $request->input('rows', []),
            $request->input('remove', [])
        );

        session()->forget('group_import');

        $msg = "{$result['added']} hinzugefügt · {$result['removed']} entfernt · "
             . "{$result['updated']} aktualisiert · {$result['created']} neu angelegt";

        return redirect()->route('admin.training-groups.show', $trainingGroup)
            ->with('success', "CSV-Import abgeschlossen: {$msg}.");
    }

    // ── Edit / Update ─────────────────────────────────────────────────────

    public function edit(TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $trainers = User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();
        // Only completely unassigned swimmers (no group at all)
        $swimmers = User::where('role', 'schwimmer')->where('active', true)
            ->whereDoesntHave('trainingGroups')
            ->orderBy('lastname')->orderBy('firstname')->get();

        $assignedTrainers = $trainingGroup->trainers()->pluck('users.id')->toArray();
        $assignedSwimmers = $trainingGroup->swimmers()->pluck('users.id')->toArray();

        // Sessions not yet linked to this group (trainer-scoped for non-admins)
        $linkedIds = $trainingGroup->sessions()->pluck('training_sessions.id')->toArray();

        $availableSessions = TrainingSession::manageableBy(auth()->user())
            ->whereNotIn('id', $linkedIds)
            ->orderByDesc('date')->limit(30)->get();

        $linkedSessions = $trainingGroup->sessions()->orderByDesc('date')->limit(20)->get();

        return view('admin.training-groups.edit', compact(
            'trainingGroup', 'trainers', 'swimmers',
            'assignedTrainers', 'assignedSwimmers',
            'availableSessions', 'linkedSessions'
        ));
    }

    public function update(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color'       => ['required', 'in:' . implode(',', array_keys(TrainingGroup::COLORS))],
            'group_type'  => ['required', 'in:' . implode(',', array_keys(TrainingGroup::GROUP_TYPES))],
            'active'      => ['boolean'],
            'webclub_id'  => ['nullable', 'integer', 'min:1', 'unique:training_groups,webclub_id,' . $trainingGroup->id],
            'trainers'    => ['nullable', 'array'],
            'trainers.*'  => ['exists:users,id'],
            'swimmers'    => ['nullable', 'array'],
            'swimmers.*'  => ['exists:users,id'],
        ]);

        // Admin-only: changing trainers/swimmers (trainers can change swimmers in their group)
        $isAdmin = auth()->user()->isAdmin();

        $data['active']     = $request->boolean('active');
        $data['webclub_id'] = $request->filled('webclub_id') ? (int) $request->input('webclub_id') : null;
        $trainingGroup->update($data);

        // Trainers pivot: admin only — the form shows all trainers with checkboxes,
        // so sync() (full replace) is correct here.
        if ($isAdmin && $request->has('trainers')) {
            $trainingGroup->trainers()->sync($request->input('trainers', []));
        }

        // Swimmers pivot: the edit form only shows unassigned swimmers to ADD,
        // so we must never remove existing members — use syncWithoutDetaching().
        if ($request->has('swimmers')) {
            $trainingGroup->swimmers()->syncWithoutDetaching($request->input('swimmers', []));
        }

        // Link/unlink sessions via pivot
        if ($request->has('link_sessions')) {
            $trainingGroup->sessions()->syncWithoutDetaching($request->input('link_sessions', []));
        }
        if ($request->has('unlink_sessions')) {
            $trainingGroup->sessions()->detach($request->input('unlink_sessions', []));
        }

        return redirect()->route('admin.training-groups.show', $trainingGroup)
            ->with('success', "Trainingsgruppe \"{$trainingGroup->name}\" gespeichert.");
    }

    // ── Destroy (admin only) ──────────────────────────────────────────────

    public function destroy(TrainingGroup $trainingGroup)
    {
        $name = $trainingGroup->name;
        $trainingGroup->sessions()->detach();
        $trainingGroup->delete();

        return redirect()->route('admin.training-groups.index')
            ->with('success', "Trainingsgruppe \"{$name}\" gelöscht.");
    }

    // ── Motto der Woche ───────────────────────────────────────────────────

    public function mottoWeeks(TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $season = Season::current();

        $weeks = GroupMottoWeek::where('training_group_id', $trainingGroup->id)
            ->with('user:id,firstname,lastname')
            ->orderBy('week_start')
            ->get();

        // Beteiligte in der eingestellten Reihenfolge - inklusive Partnergruppe
        $allMembers = $trainingGroup->mottoParticipants();

        // Welche Gruppen kommen als Partner in Frage? Nicht diese selbst, und
        // keine, die bereits in einem anderen Zyklus haengt.
        $partnerOptions = TrainingGroup::where('id', '!=', $trainingGroup->id)
            ->where('active', true)
            ->where(function ($q) use ($trainingGroup) {
                $q->whereNull('motto_partner_group_id')
                  ->orWhere('id', $trainingGroup->motto_partner_group_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'motto_week_enabled']);

        // Laeuft diese Gruppe selbst im Zyklus einer anderen?
        $ledBy = TrainingGroup::where('motto_partner_group_id', $trainingGroup->id)
            ->where('motto_week_enabled', true)
            ->first(['id', 'name']);

        return view('admin.training-groups.motto-weeks', compact(
            'trainingGroup', 'weeks', 'season', 'allMembers', 'partnerOptions', 'ledBy'
        ));
    }

    /**
     * Definition des Zyklus: Partnergruppe und Trainerbeteiligung.
     *
     * Die Partnergruppe fuehrt danach keinen eigenen Zyklus mehr - sonst
     * stuende dieselbe Person in zwei Wochenplaenen.
     */
    public function mottoSettings(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'motto_partner_group_id' => ['nullable', 'integer', 'exists:training_groups,id',
                                         Rule::notIn([$trainingGroup->id])],
            'motto_include_trainers' => ['nullable', 'boolean'],
        ]);

        $partnerId = $data['motto_partner_group_id'] ?? null;
        $partner   = $partnerId ? TrainingGroup::find($partnerId) : null;

        // Eine Gruppe, die selbst schon einen Partner fuehrt, kann nicht
        // zusaetzlich Partner sein - das waere eine Kette ohne klaren Zyklus.
        if ($partner && $partner->motto_partner_group_id) {
            return back()->with('error',
                "{$partner->name} führt bereits einen eigenen Zyklus mit einer Partnergruppe.");
        }

        $trainingGroup->update([
            'motto_partner_group_id' => $partnerId,
            'motto_include_trainers' => $request->boolean('motto_include_trainers'),
        ]);

        $msg = 'Definition gespeichert.';
        if ($partner && $partner->motto_week_enabled) {
            $partner->update(['motto_week_enabled' => false]);
            $msg .= " {$partner->name} führt keinen eigenen Zyklus mehr, die Mitglieder laufen jetzt hier mit.";
        }
        $msg .= ' Mit „Neu verteilen" wirkt die Änderung auf die kommenden Wochen.';

        return back()->with('success', $msg);
    }

    /** Reihenfolge der Beteiligten festlegen, auf Wunsch gleich anwenden. */
    public function mottoOrder(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:users,id'],
            'apply'   => ['nullable', 'boolean'],
        ]);

        $trainingGroup->update(['motto_order' => array_values(array_map('intval', $data['order']))]);

        $msg = 'Reihenfolge gespeichert.';
        if ($request->boolean('apply')) {
            $changed = app(MottoWeekService::class)->redistributeMembers($trainingGroup);
            $msg .= " {$changed} kommende Wochen neu zugeordnet"
                  . ' (Wochen mit eingetragenem Motto blieben unverändert).';
        }

        return back()->with('success', $msg);
    }

    /**
     * Wochenplan in einem Rutsch: wer ist wann dran, und der Motto-Text dazu.
     * So lassen sich Personen gezielt in die passende Woche schieben.
     */
    public function mottoWeeksBulk(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $data = $request->validate([
            'assign'   => ['array'],
            'assign.*' => ['nullable', 'integer', 'exists:users,id'],
            'motto'    => ['array'],
            'motto.*'  => ['nullable', 'string', 'max:500'],
        ]);

        $touched = array_unique(array_merge(
            array_keys($data['assign'] ?? []),
            array_keys($data['motto'] ?? [])
        ));

        $weeks = GroupMottoWeek::where('training_group_id', $trainingGroup->id)
            ->whereIn('id', $touched)
            ->get();

        $changed = 0;
        foreach ($weeks as $week) {
            $newUser  = array_key_exists($week->id, $data['assign'] ?? []) ? ($data['assign'][$week->id] ?: null) : $week->user_id;
            $newMotto = array_key_exists($week->id, $data['motto'] ?? [])  ? (trim((string) $data['motto'][$week->id]) ?: null) : $week->motto;

            if ((int) $newUser !== (int) $week->user_id || $newMotto !== $week->motto) {
                $week->update(['user_id' => $newUser, 'motto' => $newMotto]);
                $changed++;
            }
        }

        return back()->with('success', $changed === 1
            ? '1 Woche aktualisiert.'
            : "{$changed} Wochen aktualisiert.");
    }

    public function mottoToggle(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        abort_unless(auth()->user()->isAdmin(), 403);

        $enabled = $request->boolean('enabled');
        $trainingGroup->update(['motto_week_enabled' => $enabled]);

        return back()->with('success', $enabled
            ? 'Motto der Woche aktiviert.'
            : 'Motto der Woche deaktiviert.');
    }

    public function mottoGenerate(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        abort_unless(auth()->user()->isAdmin(), 403);

        $season = Season::current();
        if (!$season) {
            return back()->with('error', 'Keine aktive Saison gefunden.');
        }

        $service = app(MottoWeekService::class);
        $count   = $service->generateWeeks($trainingGroup, $season);

        return back()->with('success', "{$count} Wochen generiert.");
    }

    public function mottoReset(Request $request, TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);
        abort_unless(auth()->user()->isAdmin(), 403);

        $season = Season::current();
        if (!$season) {
            return back()->with('error', 'Keine aktive Saison gefunden.');
        }

        // Alle Wochen der aktuellen Saison löschen (nur ohne Motto – oder alle wenn force)
        $force = $request->boolean('force');
        $query = GroupMottoWeek::where('training_group_id', $trainingGroup->id)
            ->where('week_start', '>=', $season->start_date)
            ->where('week_start', '<=', $season->end_date);

        if (!$force) {
            $query->whereNull('motto'); // Wochen mit eingetragenem Motto bewahren
        }

        $deleted = $query->count();
        $query->delete();

        $service = app(MottoWeekService::class);
        $created = $service->generateWeeks($trainingGroup, $season);

        $msg = "{$deleted} Wochen zurückgesetzt, {$created} neu verteilt.";
        if (!$force) {
            $msg .= ' Wochen mit vorhandenem Motto wurden bewahrt.';
        }

        return back()->with('success', $msg);
    }

    public function mottoUpdateWeek(Request $request, TrainingGroup $trainingGroup, GroupMottoWeek $week)
    {
        $this->authorizeGroup($trainingGroup);
        abort_if($week->training_group_id !== $trainingGroup->id, 404);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'motto'   => ['nullable', 'string', 'max:500'],
        ]);

        $week->update($data);

        return back()->with('success', 'Woche aktualisiert.');
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function authorizeGroup(TrainingGroup $group): void
    {
        if (!$group->canEdit(auth()->user())) {
            abort(403, 'Kein Zugriff auf diese Trainingsgruppe.');
        }
    }
}
