<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingSession;
use App\Models\TrainingSessionRegistration;
use Illuminate\Http\Request;

class SessionPlanningController extends Controller
{
    public function excludeSeries(string $recurrenceGroupId)
    {
        SwimmerSeriesExclusion::firstOrCreate([
            'user_id'             => auth()->id(),
            'recurrence_group_id' => $recurrenceGroupId,
        ]);

        return back()->with('success', 'Serie ausgeblendet.');
    }

    public function includeSeries(string $recurrenceGroupId)
    {
        SwimmerSeriesExclusion::where('user_id', auth()->id())
            ->where('recurrence_group_id', $recurrenceGroupId)
            ->delete();

        return back()->with('success', 'Serie wieder eingeblendet.');
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
