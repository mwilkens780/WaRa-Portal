<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Models\TrainingDiary;
use App\Models\TrainingGroup;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmingTime;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TrainingSessionController extends Controller
{
    public function index()
    {
        $sessions = TrainingSession::with(['trainer', 'trainingGroups:id,name,color'])
            ->when(!auth()->user()->isAdmin(), fn($q) => $q->where('trainer_id', auth()->id()))
            ->orderByDesc('date')
            ->paginate(20);

        return view('trainer.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $groups = $this->availableGroups();
        return view('trainer.sessions.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'date'              => ['required', 'date'],
            'start_time'        => ['required', 'date_format:H:i'],
            'end_time'          => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'          => ['required', 'string', 'max:255'],
            'type'              => ['required', 'in:kondition,technik,wettkampf,ausdauer,krafttraining,physio,mentaltraining,sonstiges'],
            'notes'             => ['nullable', 'string'],
            'trainer_id'        => ['nullable', 'exists:users,id'],
            'groups'            => ['nullable', 'array'],
            'groups.*'          => ['exists:training_groups,id'],
            'recurrence_type'   => ['required', 'in:none,weekly,biweekly,monthly'],
            'recurrence_until'  => ['nullable', 'date', 'after:date', 'required_unless:recurrence_type,none'],
            'team_plan'         => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:5120'],
        ]);

        $data['trainer_id'] = $data['trainer_id'] ?? auth()->id();
        $groupIds = $request->input('groups', []);
        $recurrenceGroupId = null;

        // Datei-Upload Teamplan
        $teamPlanPath = null;
        if ($request->hasFile('team_plan')) {
            $teamPlanPath = $request->file('team_plan')
                ->store('training-plans', 'local');
        }

        if ($data['recurrence_type'] === 'none') {
            $session = TrainingSession::create(array_merge($data, [
                'team_plan_path' => $teamPlanPath,
            ]));
            $session->trainingGroups()->sync($groupIds);
            return redirect()->route('trainer.sessions.show', $session)
                ->with('success', 'Trainingseinheit angelegt.');
        }

        // Wiederholende Einheiten erzeugen
        $recurrenceGroupId = (string) Str::uuid();
        $until   = Carbon::parse($data['recurrence_until']);
        $current = Carbon::parse($data['date']);
        $created = 0;
        $firstSession = null;

        while ($current->lte($until)) {
            $session = TrainingSession::create([
                'trainer_id'          => $data['trainer_id'],
                'title'               => $data['title'],
                'date'                => $current->format('Y-m-d'),
                'start_time'          => $data['start_time'],
                'end_time'            => $data['end_time'] ?? null,
                'location'            => $data['location'],
                'type'                => $data['type'],
                'notes'               => $data['notes'] ?? null,
                'recurrence_type'     => $data['recurrence_type'],
                'recurrence_until'    => $data['recurrence_until'],
                'recurrence_group_id' => $recurrenceGroupId,
                'team_plan_path'      => $teamPlanPath,
            ]);
            $session->trainingGroups()->sync($groupIds);

            if (!$firstSession) $firstSession = $session;
            $created++;

            $current = match($data['recurrence_type']) {
                'weekly'    => $current->addWeek(),
                'biweekly'  => $current->addWeeks(2),
                'monthly'   => $current->addMonth(),
            };
        }

        return redirect()->route('trainer.sessions.show', $firstSession)
            ->with('success', "{$created} Trainingseinheiten (Wiederholung) angelegt.");
    }

    public function show(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $session->load('trainer', 'attendances.user', 'swimmingTimes.user', 'diaries.user', 'trainingGroups.swimmers', 'trainingPlan.blocks');

        // Only show swimmers from the session's training groups; fall back to all if no groups assigned
        if ($session->trainingGroups->isNotEmpty()) {
            $swimmerIds = $session->trainingGroups
                ->flatMap(fn($g) => $g->swimmers->where('active', true)->pluck('id'))
                ->unique();
            $swimmers = User::where('role', 'schwimmer')->where('active', true)
                ->whereIn('id', $swimmerIds)
                ->orderBy('lastname')->orderBy('firstname')->get();
        } else {
            $swimmers = User::where('role', 'schwimmer')->where('active', true)
                ->orderBy('lastname')->orderBy('firstname')->get();
        }

        $attendedIds = $session->attendances->where('attended', true)->pluck('user_id')->toArray();

        $totalSwimmers    = $swimmers->count();
        $presentCount     = count(array_intersect($attendedIds, $swimmers->pluck('id')->toArray()));
        $participationPct = $totalSwimmers > 0 ? round($presentCount / $totalSwimmers * 100) : 0;

        // Split into registered (no pre-absence) and cancelled (pre_absent = true)
        $preAbsentIds = $session->attendances->where('pre_absent', true)->pluck('user_id')->toArray();
        $registeredSwimmers = $swimmers->filter(fn($s) => !in_array($s->id, $preAbsentIds))->values();
        $cancelledSwimmers  = $swimmers->filter(fn($s) =>  in_array($s->id, $preAbsentIds))->values();
        $preAbsentCount     = $cancelledSwimmers->count();

        $siblings = $session->recurrence_group_id
            ? TrainingSession::where('recurrence_group_id', $session->recurrence_group_id)
                ->where('id', '!=', $session->id)
                ->orderBy('date')->get()
            : collect();

        $allTrainers = User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        $blockTimesMap = [];
        if ($session->trainingPlan) {
            $blockIds = $session->trainingPlan->blocks->pluck('id');
            \App\Models\TrainingBlockTime::whereIn('training_plan_block_id', $blockIds)
                ->get()
                ->each(function ($t) use (&$blockTimesMap) {
                    $blockTimesMap[$t->training_plan_block_id][$t->user_id][$t->repetition] = $t->time_cs;
                });
        }

        return view('trainer.sessions.show', compact(
            'session', 'swimmers', 'attendedIds',
            'participationPct', 'presentCount', 'totalSwimmers',
            'preAbsentCount', 'registeredSwimmers', 'cancelledSwimmers',
            'siblings', 'allTrainers', 'blockTimesMap'
        ));
    }

    public function printView(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $session->load(['trainer', 'trainingPlan.blocks', 'attendances.user', 'trainingGroups.swimmers']);

        if ($session->trainingGroups->isNotEmpty()) {
            $swimmerIds = $session->trainingGroups
                ->flatMap(fn($g) => $g->swimmers->where('active', true)->pluck('id'))
                ->unique();
            $swimmers = User::where('role', 'schwimmer')->where('active', true)
                ->whereIn('id', $swimmerIds)
                ->orderBy('lastname')->orderBy('firstname')->get();
        } else {
            $swimmers = User::where('role', 'schwimmer')->where('active', true)
                ->orderBy('lastname')->orderBy('firstname')->get();
        }

        $attendedIds = $session->attendances->where('attended', true)->pluck('user_id')->toArray();

        $blockTimesMap = [];
        if ($session->trainingPlan) {
            $blockIds = $session->trainingPlan->blocks->pluck('id');
            \App\Models\TrainingBlockTime::whereIn('training_plan_block_id', $blockIds)
                ->get()
                ->each(function ($t) use (&$blockTimesMap) {
                    $blockTimesMap[$t->training_plan_block_id][$t->user_id][$t->repetition] = $t->time_cs;
                });
        }

        return view('trainer.sessions.print', compact('session', 'swimmers', 'attendedIds', 'blockTimesMap'));
    }

    public function edit(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $groups = $this->availableGroups();
        $allTrainers = User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();
        return view('trainer.sessions.edit', compact('session', 'groups', 'allTrainers'));
    }

    public function update(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'    => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:kondition,technik,wettkampf,ausdauer,krafttraining,physio,mentaltraining,sonstiges'],
            'notes'       => ['nullable', 'string'],
            'trainer_id'  => ['nullable', 'exists:users,id'],
            'groups'      => ['nullable', 'array'],
            'groups.*'    => ['exists:training_groups,id'],
        ]);

        $data['trainer_id'] = $data['trainer_id'] ?? $session->trainer_id;
        $groupIds = $request->input('groups', []);

        $session->update($data);
        $session->trainingGroups()->sync($groupIds);

        return redirect()->route('trainer.sessions.show', $session)
            ->with('success', 'Trainingseinheit aktualisiert.');
    }

    public function destroy(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $session->delete();
        return redirect()->route('trainer.sessions.index')
            ->with('success', 'Trainingseinheit gelöscht.');
    }

    public function destroyGroup(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $groupId = $session->recurrence_group_id;

        if ($groupId) {
            $count = TrainingSession::where('recurrence_group_id', $groupId)->count();
            TrainingSession::where('recurrence_group_id', $groupId)->delete();
            return redirect()->route('trainer.sessions.index')
                ->with('success', "{$count} Einheiten der Wiederholungsgruppe gelöscht.");
        }

        $session->delete();
        return redirect()->route('trainer.sessions.index')
            ->with('success', 'Trainingseinheit gelöscht.');
    }

    // ── Anwesenheit ─────────────────────────────────────────────────────────

    public function saveAttendance(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);
        $session->loadMissing('trainingGroups.swimmers');

        if ($session->trainingGroups->isNotEmpty()) {
            $swimmerIds = $session->trainingGroups
                ->flatMap(fn($g) => $g->swimmers->where('active', true)->pluck('id'))
                ->unique();
        } else {
            $swimmerIds = User::where('role', 'schwimmer')->where('active', true)->pluck('id');
        }

        foreach ($swimmerIds as $swimmerId) {
            $attended = $request->has("attendance.{$swimmerId}");
            TrainingAttendance::updateOrCreate(
                ['training_session_id' => $session->id, 'user_id' => $swimmerId],
                [
                    'attended'         => $attended,
                    'notes'            => $request->input("notes.{$swimmerId}"),
                    'trainer_comment'  => $request->input("trainer_comment.{$swimmerId}"),
                ]
            );
        }

        return back()->with('success', 'Anwesenheit gespeichert.');
    }

    // ── Zeiten ──────────────────────────────────────────────────────────────

    public function saveTime(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'user_id'            => ['required', 'exists:users,id'],
            'discipline'         => ['required', 'in:freistil,brust,ruecken,schmetterling,lagen'],
            'distance'           => ['required', 'integer', 'min:25'],
            'time_minutes'       => ['nullable', 'integer', 'min:0'],
            'time_seconds'       => ['required', 'integer', 'min:0', 'max:59'],
            'time_centiseconds'  => ['required', 'integer', 'min:0', 'max:99'],
            'notes'              => ['nullable', 'string'],
        ]);

        $timeMs = (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000
            + $data['time_centiseconds'] * 10;

        $existingBest = SwimmingTime::where('user_id', $data['user_id'])
            ->where('discipline', $data['discipline'])
            ->where('distance', $data['distance'])
            ->min('time_ms');

        SwimmingTime::create([
            'user_id'             => $data['user_id'],
            'training_session_id' => $session->id,
            'discipline'          => $data['discipline'],
            'distance'            => $data['distance'],
            'time_ms'             => $timeMs,
            'is_personal_best'    => !$existingBest || $timeMs < $existingBest,
            'notes'               => $data['notes'] ?? null,
        ]);

        $this->checkTimeGoalAutoAchievement($data['user_id'], $data['discipline'], $data['distance'], $timeMs);

        return back()->with('success', 'Zeit eingetragen.');
    }

    public function destroyTime(SwimmingTime $time)
    {
        $time->delete();
        return back()->with('success', 'Zeit gelöscht.');
    }

    private function checkTimeGoalAutoAchievement(int $userId, string $discipline, int $distance, int $timeMs): void
    {
        $season = Season::current();
        if (!$season) return;

        $goals = SwimmerGoal::where('user_id', $userId)
            ->where('season_id', $season->id)
            ->where('type', 'time')
            ->where('status', 'open')
            ->where('discipline', $discipline)
            ->where('distance', $distance)
            ->whereNotNull('target_time_ms')
            ->where('target_time_ms', '>=', $timeMs)
            ->get();

        foreach ($goals as $goal) {
            $goal->update([
                'status'           => 'achieved',
                'achieved'         => true,
                'achieved_at'      => now()->toDateString(),
                'achieved_time_ms' => $timeMs,
                'progress'         => 100,
                'notified'         => false,
            ]);
        }
    }

    // ── Trainingstagebuch ───────────────────────────────────────────────────

    public function saveDiary(Request $request, TrainingSession $session)
    {
        $data = $request->validate([
            'body'                => ['nullable', 'string', 'max:2000'],
            'mood'                => ['nullable', 'in:sehr_gut,gut,mittel,schlecht,sehr_schlecht'],
            'perceived_intensity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        TrainingDiary::updateOrCreate(
            ['training_session_id' => $session->id, 'user_id' => auth()->id()],
            $data
        );

        return back()->with('success', 'Tagebucheintrag gespeichert.');
    }

    // ── Trainingspläne ──────────────────────────────────────────────────────

    public function uploadTeamPlan(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $request->validate([
            'team_plan' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:5120'],
        ]);

        if ($session->team_plan_path) {
            \Storage::disk('local')->delete($session->team_plan_path);
        }

        $path = $request->file('team_plan')->store('training-plans', 'local');
        $session->update(['team_plan_path' => $path]);

        return back()->with('success', 'Teamplan hochgeladen.');
    }

    public function uploadIndividualPlan(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $request->validate([
            'individual_plan' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:5120'],
        ]);

        if ($session->individual_plan_path) {
            \Storage::disk('local')->delete($session->individual_plan_path);
        }

        $path = $request->file('individual_plan')->store('training-plans', 'local');
        $session->update(['individual_plan_path' => $path]);

        return back()->with('success', 'Individueller Plan hochgeladen.');
    }

    public function downloadPlan(TrainingSession $session, string $type = 'team')
    {
        $path = $type === 'individual'
            ? $session->individual_plan_path
            : $session->team_plan_path;

        if (!$path || !\Storage::disk('local')->exists($path)) {
            abort(404, 'Plan nicht gefunden.');
        }

        return \Storage::disk('local')->download(
            $path,
            ($type === 'individual' ? 'Individueller-Plan' : 'Teamplan')
                . '-' . $session->date->format('Y-m-d') . '.' . pathinfo($path, PATHINFO_EXTENSION)
        );
    }

    // ── Vertretung ──────────────────────────────────────────────────────────

    public function substituteTrainer(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'trainer_id' => ['required', 'exists:users,id'],
        ]);

        $session->update(['trainer_id' => $data['trainer_id']]);

        return back()->with('success', 'Trainer für diese Einheit geändert.');
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    private function authorizeSession(TrainingSession $session): void
    {
        if (!auth()->user()->isAdmin() && $session->trainer_id !== auth()->id()) {
            abort(403);
        }
    }

    private function availableGroups()
    {
        $query = TrainingGroup::with('trainers:id,firstname,lastname')->where('active', true)->orderBy('name');
        if (!auth()->user()->isAdmin()) {
            $query->whereHas('trainers', fn($q) => $q->where('users.id', auth()->id()));
        }
        return $query->get();
    }
}
