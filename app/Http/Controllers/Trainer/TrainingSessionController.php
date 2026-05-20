<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Models\TrainingDiary;
use App\Models\SwimmingTime;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TrainingSessionController extends Controller
{
    public function index()
    {
        $sessions = TrainingSession::with('trainer')
            ->when(!auth()->user()->isAdmin(), fn($q) => $q->where('trainer_id', auth()->id()))
            ->orderByDesc('date')
            ->paginate(20);

        return view('trainer.sessions.index', compact('sessions'));
    }

    public function create()
    {
        return view('trainer.sessions.create');
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
            'recurrence_type'   => ['required', 'in:none,weekly,biweekly,monthly'],
            'recurrence_until'  => ['nullable', 'date', 'after:date', 'required_unless:recurrence_type,none'],
            'team_plan'         => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:5120'],
        ]);

        $data['trainer_id'] = auth()->id();
        $groupId = null;

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
            return redirect()->route('trainer.sessions.show', $session)
                ->with('success', 'Trainingseinheit angelegt.');
        }

        // Wiederholende Einheiten erzeugen
        $groupId = (string) Str::uuid();
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
                'recurrence_group_id' => $groupId,
                'team_plan_path'      => $teamPlanPath,
            ]);

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
        $session->load('trainer', 'attendances.user', 'swimmingTimes.user', 'diaries.user');

        $swimmers    = User::where('role', 'schwimmer')->where('active', true)->orderBy('name')->get();
        $attendedIds = $session->attendances->where('attended', true)->pluck('user_id')->toArray();

        // Beteiligung dieser Einheit
        $totalSwimmers    = $swimmers->count();
        $presentCount     = count($attendedIds);
        $participationPct = $totalSwimmers > 0 ? round($presentCount / $totalSwimmers * 100) : 0;

        // Geschwister-Einheiten (gleiche Wiederholungsgruppe)
        $siblings = $session->recurrence_group_id
            ? TrainingSession::where('recurrence_group_id', $session->recurrence_group_id)
                ->where('id', '!=', $session->id)
                ->orderBy('date')->get()
            : collect();

        return view('trainer.sessions.show', compact(
            'session', 'swimmers', 'attendedIds',
            'participationPct', 'presentCount', 'totalSwimmers', 'siblings'
        ));
    }

    public function edit(TrainingSession $session)
    {
        $this->authorizeSession($session);
        return view('trainer.sessions.edit', compact('session'));
    }

    public function update(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'date'       => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location'   => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:kondition,technik,wettkampf,ausdauer,krafttraining,physio,mentaltraining,sonstiges'],
            'notes'      => ['nullable', 'string'],
        ]);

        $session->update($data);

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

        $swimmers = User::where('role', 'schwimmer')->where('active', true)->pluck('id');

        foreach ($swimmers as $swimmerId) {
            $attended = $request->has("attendance.{$swimmerId}");
            TrainingAttendance::updateOrCreate(
                ['training_session_id' => $session->id, 'user_id' => $swimmerId],
                ['attended' => $attended, 'notes' => $request->input("notes.{$swimmerId}")]
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

        return back()->with('success', 'Zeit eingetragen.');
    }

    public function destroyTime(SwimmingTime $time)
    {
        $time->delete();
        return back()->with('success', 'Zeit gelöscht.');
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

    // ── Helper ──────────────────────────────────────────────────────────────

    private function authorizeSession(TrainingSession $session): void
    {
        if (!auth()->user()->isAdmin() && $session->trainer_id !== auth()->id()) {
            abort(403);
        }
    }
}
