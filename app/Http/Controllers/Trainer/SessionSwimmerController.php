<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingSessionSwimmer;
use App\Models\User;
use Illuminate\Http\Request;

class SessionSwimmerController extends Controller
{
    public function addToSession(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        TrainingSessionSwimmer::firstOrCreate([
            'user_id'             => $data['user_id'],
            'training_session_id' => $session->id,
        ]);

        return back()->with('success', 'Schwimmer zur Einheit hinzugefügt.');
    }

    public function removeFromSession(Request $request, TrainingSession $session, User $user)
    {
        $this->authorizeSession($session);

        TrainingSessionSwimmer::where('user_id', $user->id)
            ->where('training_session_id', $session->id)
            ->delete();

        return back()->with('success', 'Schwimmer aus der Einheit entfernt.');
    }

    public function addToSeries(Request $request, string $recurrenceGroupId)
    {
        $this->authorizeSeries($recurrenceGroupId);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        TrainingSessionSwimmer::firstOrCreate([
            'user_id'             => $data['user_id'],
            'recurrence_group_id' => $recurrenceGroupId,
        ]);

        return back()->with('success', 'Schwimmer zur Serie hinzugefügt.');
    }

    public function removeFromSeries(Request $request, string $recurrenceGroupId, User $user)
    {
        $this->authorizeSeries($recurrenceGroupId);

        TrainingSessionSwimmer::where('user_id', $user->id)
            ->where('recurrence_group_id', $recurrenceGroupId)
            ->delete();

        return back()->with('success', 'Schwimmer aus der Serie entfernt.');
    }

    // Bisher ohne jede Pruefung: Jeder Trainer konnte Schwimmer zu beliebigen
    // Einheiten und Serien hinzufuegen oder entfernen.

    private function authorizeSession(TrainingSession $session): void
    {
        abort_unless($session->isManageableBy(auth()->user()), 403);
    }

    private function authorizeSeries(string $recurrenceGroupId): void
    {
        $allowed = TrainingSession::where('recurrence_group_id', $recurrenceGroupId)
            ->manageableBy(auth()->user())
            ->exists();

        abort_unless($allowed, 403);
    }
}
