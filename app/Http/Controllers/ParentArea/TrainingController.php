<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
use App\Models\TrainingSessionRegistration;
use App\Models\TrainingSessionSwimmer;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function childTrainings(int $childId)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        // Same visibility logic as swimmer's DashboardController
        $groupIds      = $child->trainingGroups()->pluck('training_groups.id');
        $individualIds = TrainingSessionSwimmer::where('user_id', $child->id)->whereNotNull('training_session_id')->pluck('training_session_id');
        $seriesIds     = TrainingSessionSwimmer::where('user_id', $child->id)->whereNotNull('recurrence_group_id')->pluck('recurrence_group_id');

        $visibilityFilter = function ($q) use ($groupIds, $individualIds, $seriesIds) {
            $q->where(function ($inner) use ($groupIds, $individualIds, $seriesIds) {
                if ($groupIds->isNotEmpty()) {
                    $inner->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $groupIds));
                }
                if ($individualIds->isNotEmpty()) {
                    $inner->orWhereIn('id', $individualIds);
                }
                if ($seriesIds->isNotEmpty()) {
                    $inner->orWhereIn('recurrence_group_id', $seriesIds);
                }
            });
        };

        $upcoming = TrainingSession::where('date', '>', today())
            ->tap($visibilityFilter)
            ->with(['trainingGroups:id,name'])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $myRegistrations = TrainingSessionRegistration::where('user_id', $child->id)
            ->whereIn('training_session_id', $upcoming->pluck('id'))
            ->pluck('training_session_id');

        $preAbsenceMap = TrainingAttendance::where('user_id', $child->id)
            ->where('pre_absent', true)
            ->whereIn('training_session_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('training_session_id');

        return view('parent.child-trainings', compact('child', 'upcoming', 'myRegistrations', 'preAbsenceMap'));
    }

    public function cancelSession(Request $request, int $childId, TrainingSession $session)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        if ($session->date->lte(today())) {
            return back()->with('error', 'Vergangene Einheiten können nicht abgesagt werden.');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $attendance = TrainingAttendance::where('training_session_id', $session->id)
            ->where('user_id', $child->id)->first();

        if ($attendance?->pre_absent) {
            $attendance->update(['pre_absent' => false, 'pre_absent_note' => null]);
            return back()->with('success', 'Absage zurückgenommen.');
        }

        TrainingAttendance::updateOrCreate(
            ['training_session_id' => $session->id, 'user_id' => $child->id],
            ['pre_absent' => true, 'pre_absent_note' => $data['note'] ?? null]
        );

        return back()->with('success', 'Absage für ' . $child->firstname . ' gespeichert.');
    }

    public function register(int $childId, TrainingSession $session)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        if (!$session->registration_open) {
            return back()->with('error', 'Anmeldung ist nicht geöffnet.');
        }

        if ($session->max_participants !== null && $session->remainingSpots() <= 0) {
            return back()->with('error', 'Keine freien Plätze mehr verfügbar.');
        }

        TrainingSessionRegistration::firstOrCreate(
            ['training_session_id' => $session->id, 'user_id' => $child->id],
            ['registered_at' => now()]
        );

        return back()->with('success', $child->firstname . ' erfolgreich angemeldet.');
    }

    public function unregister(int $childId, TrainingSession $session)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        TrainingSessionRegistration::where('training_session_id', $session->id)
            ->where('user_id', $child->id)
            ->delete();

        return back()->with('success', 'Abmeldung erfolgreich.');
    }
}
