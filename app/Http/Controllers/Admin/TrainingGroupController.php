<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;

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

        $groups = $query->get();

        return view('admin.training-groups.index', compact('groups'));
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
            'active'      => ['boolean'],
            'trainers'    => ['nullable', 'array'],
            'trainers.*'  => ['exists:users,id'],
            'swimmers'    => ['nullable', 'array'],
            'swimmers.*'  => ['exists:users,id'],
        ]);

        $data['active'] = $request->boolean('active', true);

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

        $trainingGroup->load([
            'trainers:id,firstname,lastname,role',
            'swimmers:id,firstname,lastname,birth_date',
        ]);

        $recentSessions = $trainingGroup->sessions()
            ->with('trainer:id,firstname,lastname')
            ->orderByDesc('date')->limit(10)->get();

        $upcomingSessions = $trainingGroup->sessions()
            ->where('date', '>=', now())->orderBy('date')->limit(5)->get();

        return view('admin.training-groups.show', compact('trainingGroup', 'recentSessions', 'upcomingSessions'));
    }

    // ── Edit / Update ─────────────────────────────────────────────────────

    public function edit(TrainingGroup $trainingGroup)
    {
        $this->authorizeGroup($trainingGroup);

        $trainers = User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();
        $swimmers = User::where('role', 'schwimmer')->where('active', true)
            ->where(fn($q) => $q
                ->whereDoesntHave('trainingGroups')
                ->orWhereHas('trainingGroups', fn($q2) => $q2->where('training_groups.id', $trainingGroup->id))
            )
            ->orderBy('lastname')->orderBy('firstname')->get();

        $assignedTrainers = $trainingGroup->trainers()->pluck('users.id')->toArray();
        $assignedSwimmers = $trainingGroup->swimmers()->pluck('users.id')->toArray();

        // Sessions not yet linked to this group (trainer-scoped for non-admins)
        $linkedIds = $trainingGroup->sessions()->pluck('training_sessions.id')->toArray();

        $availableSessions = TrainingSession::when(
            !auth()->user()->isAdmin(),
            fn($q) => $q->where('trainer_id', auth()->id())
        )->whereNotIn('id', $linkedIds)
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
            'active'      => ['boolean'],
            'trainers'    => ['nullable', 'array'],
            'trainers.*'  => ['exists:users,id'],
            'swimmers'    => ['nullable', 'array'],
            'swimmers.*'  => ['exists:users,id'],
        ]);

        // Admin-only: changing trainers/swimmers (trainers can change swimmers in their group)
        $isAdmin = auth()->user()->isAdmin();

        $data['active'] = $request->boolean('active');
        $trainingGroup->update($data);

        // Trainers pivot: admin only
        if ($isAdmin) {
            $trainingGroup->trainers()->sync($request->input('trainers', []));
        }

        // Swimmers pivot: trainer or admin
        $trainingGroup->swimmers()->sync($request->input('swimmers', []));

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

    // ── Helper ────────────────────────────────────────────────────────────

    private function authorizeGroup(TrainingGroup $group): void
    {
        if (!$group->canEdit(auth()->user())) {
            abort(403, 'Kein Zugriff auf diese Trainingsgruppe.');
        }
    }
}
