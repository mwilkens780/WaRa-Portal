<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSignupRequest;
use App\Models\CompetitionSignupResponse;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function respond(Request $request, CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isActive()) {
            return back()->with('error', 'Diese Anmeldeabfrage ist nicht mehr aktiv.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:attending,not_attending'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $response = CompetitionSignupResponse::where('competition_signup_request_id', $signupRequest->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$response) {
            return back()->with('error', 'Du bist nicht für diese Anmeldeabfrage eingeladen.');
        }

        $response->update([
            'status'       => $data['status'],
            'note'         => $data['note'] ?? null,
            'responded_at' => now(),
        ]);

        $label = $data['status'] === 'attending' ? 'Zusage' : 'Absage';
        return back()->with('success', "{$label} gespeichert.");
    }
}
