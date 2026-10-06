<?php

namespace App\Http\Controllers;

use App\Models\CompetitionOfficialRequest;
use App\Services\OfficialRequests;
use Illuminate\Http\Request;

/**
 * Rückmeldung eines angefragten Kampfrichters: je Veranstaltungstag ja/nein
 * mit Kommentar, dazu Wunschpositionen (Entscheidung Martin, 06.10.2026).
 */
class OfficialResponseController extends Controller
{
    public function show(Request $request, CompetitionOfficialRequest $officialRequest)
    {
        $invitee = $officialRequest->invitees()->where('user_id', $request->user()->id)->firstOrFail();

        return view('officials.respond', [
            'req'     => $officialRequest->load('competition'),
            'invitee' => $invitee,
            'days'    => $officialRequest->days(),
        ]);
    }

    public function update(Request $request, CompetitionOfficialRequest $officialRequest, OfficialRequests $officials)
    {
        $invitee = $officialRequest->invitees()->where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'days'               => ['nullable', 'array'],
            'days.*.available'   => ['nullable', 'in:1,0'],
            'days.*.comment'     => ['nullable', 'string', 'max:300'],
            'positions'          => ['nullable', 'array'],
            'positions.*'        => ['string', 'in:' . implode(',', array_keys(CompetitionOfficialRequest::POSITIONS))],
            'comment'            => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $officials->respond($invitee->setRelation('request', $officialRequest), $data['days'] ?? [], $data['positions'] ?? [], $data['comment'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['days' => $e->getMessage()]);
        }

        return back()->with('success', 'Danke, deine Rückmeldung ist gespeichert.');
    }
}
