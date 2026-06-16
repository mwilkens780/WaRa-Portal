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

    public function toggleBus(CompetitionSignupRequest $signupRequest)
    {
        if (!$signupRequest->isActive()) {
            return back()->with('error', 'Diese Anmeldeabfrage ist nicht mehr aktiv.');
        }
        if (!$signupRequest->bus_available) {
            return back()->with('error', 'Für diese Abfrage ist kein Bus verfügbar.');
        }

        $response = CompetitionSignupResponse::where('competition_signup_request_id', $signupRequest->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$response || !$response->isAttending()) {
            return back()->with('error', 'Du hast dich noch nicht als Teilnehmer angemeldet.');
        }

        if (!$response->bus_booked && $signupRequest->busSeatsRemaining() <= 0) {
            return back()->with('error', 'Leider sind keine Plätze mehr frei.');
        }

        $response->update(['bus_booked' => !$response->bus_booked]);

        $msg = $response->bus_booked ? 'Busplatz gebucht.' : 'Busplatz storniert.';
        return back()->with('success', $msg);
    }
}
