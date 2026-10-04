<?php

namespace App\Http\Controllers\ParentArea;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSignupRequest;
use App\Services\SignupResponder;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function childSignups(int $childId)
    {
        $parent = auth()->user();
        $child  = $parent->children()->findOrFail($childId);

        // Active signup requests that have a response record for the child
        $signupRequests = CompetitionSignupRequest::where('status', 'active')
            ->whereHas('competition', fn($q) => $q->upcomingOrRunning())
            ->whereHas('responses', fn($q) => $q->where('user_id', $child->id))
            ->with(['competition', 'responses' => fn($q) => $q->where('user_id', $child->id)])
            ->orderBy('deadline')
            ->get();

        return view('parent.child-signups', compact('child', 'signupRequests'));
    }

    public function respond(Request $request, int $childId, CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        $child = auth()->user()->children()->findOrFail($childId);

        $data = $request->validate([
            'status'          => ['required', 'in:attending,not_attending'],
            'note'            => ['nullable', 'string', 'max:500'],
            'carpool_seats'   => ['nullable', 'integer', 'min:0', 'max:20'],
            'carpool_note'    => ['nullable', 'string', 'max:255'],
            'carpool_show_phone' => ['boolean'],
            'wants_overnight' => ['boolean'],
            'wants_dinner'    => ['boolean'],
        ]);

        [$ok, $msg] = $responder->respond($signupRequest, $child, $data, byParent: true);
        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /**
     * Busplatz fuer ein minderjaehriges Kind buchen/stornieren - viele Kinder
     * haben noch kein Handy und keine Mail-Adresse.
     */
    public function toggleBus(int $childId, CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        $child = auth()->user()->children()->findOrFail($childId);
        abort_unless(auth()->user()->isGuardianOf($child), 403);

        [$ok, $msg] = $responder->toggleBus($signupRequest, $child, byParent: true);
        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /** Platz in einer Fahrgemeinschaft fuer das Kind buchen */
    public function bookCarpool(Request $request, int $childId, CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        $child = auth()->user()->children()->findOrFail($childId);
        abort_unless(auth()->user()->isGuardianOf($child), 403);
        $data  = $request->validate(['offer_id' => ['required', 'integer']]);

        [$ok, $msg] = $responder->bookCarpool($signupRequest, $child, (int) $data['offer_id'], byParent: true);
        return back()->with($ok ? 'success' : 'error', $msg);
    }

    public function cancelCarpool(int $childId, CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        $child = auth()->user()->children()->findOrFail($childId);

        abort_unless(auth()->user()->isGuardianOf($child), 403);
        [$ok, $msg] = $responder->cancelCarpool($signupRequest, $child, byParent: true);
        return back()->with($ok ? 'success' : 'error', $msg);
    }
}
