<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSignupRequest;
use App\Models\CompetitionSignupResponse;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function childSignups(int $childId)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        // Active signup requests that have a response record for the child
        $signupRequests = CompetitionSignupRequest::where('status', 'active')
            ->whereHas('responses', fn($q) => $q->where('user_id', $child->id))
            ->with(['competition', 'responses' => fn($q) => $q->where('user_id', $child->id)])
            ->orderBy('deadline')
            ->get();

        return view('parent.child-signups', compact('child', 'signupRequests'));
    }

    public function respond(Request $request, int $childId, CompetitionSignupRequest $signupRequest)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        if (!$signupRequest->isActive()) {
            return back()->with('error', 'Diese Anmeldeabfrage ist nicht mehr aktiv.');
        }

        $data = $request->validate([
            'status'          => ['required', 'in:attending,not_attending'],
            'note'            => ['nullable', 'string', 'max:500'],
            'carpool_seats'   => ['nullable', 'integer', 'min:0', 'max:20'],
            'wants_overnight' => ['boolean'],
            'wants_dinner'    => ['boolean'],
        ]);

        $response = CompetitionSignupResponse::where('competition_signup_request_id', $signupRequest->id)
            ->where('user_id', $child->id)
            ->first();

        if (!$response) {
            return back()->with('error', $child->firstname . ' ist nicht für diese Anmeldeabfrage eingeladen.');
        }

        $update = [
            'status'       => $data['status'],
            'note'         => $data['note'] ?? null,
            'responded_at' => now(),
            'carpool_seats'=> isset($data['carpool_seats']) ? (int) $data['carpool_seats'] : null,
        ];
        if ($signupRequest->offer_overnight) {
            $update['wants_overnight'] = $data['wants_overnight'] ?? false;
        }
        if ($signupRequest->offer_dinner) {
            $update['wants_dinner'] = $data['wants_dinner'] ?? false;
        }

        $response->update($update);

        $label = $data['status'] === 'attending' ? 'Zusage' : 'Absage';
        return back()->with('success', "{$label} für {$child->firstname} gespeichert.");
    }
}
