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
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        TrainingSessionSwimmer::firstOrCreate([
            'user_id'             => $data['user_id'],
            'training_session_id' => $session->id,
        ]);

        return back()->with('success', 'Schwimmer zur Einheit hinzugefügt.');
    }

    public function removeFromSession(Request $request, TrainingSession $session, User $user)
    {
        TrainingSessionSwimmer::where('user_id', $user->id)
            ->where('training_session_id', $session->id)
            ->delete();

        return back()->with('success', 'Schwimmer aus der Einheit entfernt.');
    }

    public function addToSeries(Request $request, string $recurrenceGroupId)
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        TrainingSessionSwimmer::firstOrCreate([
            'user_id'             => $data['user_id'],
            'recurrence_group_id' => $recurrenceGroupId,
        ]);

        return back()->with('success', 'Schwimmer zur Serie hinzugefügt.');
    }

    public function removeFromSeries(Request $request, string $recurrenceGroupId, User $user)
    {
        TrainingSessionSwimmer::where('user_id', $user->id)
            ->where('recurrence_group_id', $recurrenceGroupId)
            ->delete();

        return back()->with('success', 'Schwimmer aus der Serie entfernt.');
    }
}
