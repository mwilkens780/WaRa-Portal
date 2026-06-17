<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingSession;
use App\Models\TrainingSessionRegistration;
use App\Models\TrainingSessionSwimmer;
use Illuminate\Http\Request;

class SessionPlanningController extends Controller
{
    public function excludeSeries(Request $request, string $recurrenceGroupId)
    {
        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        SwimmerSeriesExclusion::updateOrCreate(
            [
                'user_id'             => auth()->id(),
                'recurrence_group_id' => $recurrenceGroupId,
            ],
            ['comment' => $data['comment'] ?? null]
        );

        return back()->with('success', 'Serie als Dauerhafte Absage eingetragen.');
    }

    public function includeSeries(string $recurrenceGroupId)
    {
        SwimmerSeriesExclusion::where('user_id', auth()->id())
            ->where('recurrence_group_id', $recurrenceGroupId)
            ->delete();

        return back()->with('success', 'Dauerhafte Absage zurückgenommen.');
    }

    public function punctualJoin(TrainingSession $session)
    {
        TrainingSessionSwimmer::firstOrCreate([
            'user_id'             => auth()->id(),
            'training_session_id' => $session->id,
        ]);

        return back()->with('success', 'Für diese Einheit eingetragen.');
    }

    public function register(TrainingSession $session)
    {
        if (!$session->registration_open) {
            return back()->with('error', 'Anmeldung ist nicht geöffnet.');
        }

        if ($session->max_participants !== null && $session->remainingSpots() <= 0) {
            return back()->with('error', 'Keine freien Plätze mehr verfügbar.');
        }

        TrainingSessionRegistration::firstOrCreate(
            ['training_session_id' => $session->id, 'user_id' => auth()->id()],
            ['registered_at' => now()]
        );

        return back()->with('success', 'Erfolgreich angemeldet.');
    }

    public function unregister(TrainingSession $session)
    {
        TrainingSessionRegistration::where('training_session_id', $session->id)
            ->where('user_id', auth()->id())
            ->delete();

        return back()->with('success', 'Abmeldung erfolgreich.');
    }
}
