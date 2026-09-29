<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSignupRequest;
use App\Services\SignupResponder;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function respond(Request $request, CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        $data = $request->validate([
            'status'          => ['required', 'in:attending,not_attending'],
            'note'            => ['nullable', 'string', 'max:500'],
            'wants_overnight' => ['boolean'],
            'wants_dinner'    => ['boolean'],
        ]);

        [$ok, $msg] = $responder->respond($signupRequest, auth()->user(), $data);
        return back()->with($ok ? 'success' : 'error', $msg);
    }

    public function toggleBus(CompetitionSignupRequest $signupRequest, SignupResponder $responder)
    {
        [$ok, $msg] = $responder->toggleBus($signupRequest, auth()->user());
        return back()->with($ok ? 'success' : 'error', $msg);
    }
}
