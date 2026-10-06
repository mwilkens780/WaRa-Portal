<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\CalendarEventController;
use App\Models\CalendarEventFile;
use App\Models\CalendarEventInvitee;
use App\Services\EventInvitations;
use Illuminate\Http\Request;

/**
 * Einladung für Gäste ohne Portal-Konto: persönlicher Link mit Token
 * (Entscheidung Martin, 06.10.2026). Der Link zeigt Termin, Agenda,
 * Anhänge und Protokolle dieses einen Termins und nimmt die Zusage an.
 */
class GuestInvitationController extends Controller
{
    public function show(string $token)
    {
        $invitee = $this->invitee($token);

        return view('invitations.guest', [
            'invitee' => $invitee,
            'event'   => $invitee->event->load('files.source'),
        ]);
    }

    public function respond(Request $request, string $token, EventInvitations $invitations)
    {
        $invitee = $this->invitee($token);
        $data = $request->validate([
            'status'  => ['required', 'in:zugesagt,abgesagt,vielleicht'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $invitations->respond($invitee, $data['status'], $data['comment'] ?? null, null);
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Danke, deine Rückmeldung ist gespeichert: ' . CalendarEventInvitee::STATUSES[$data['status']] . '.');
    }

    public function download(string $token, CalendarEventFile $file)
    {
        $invitee = $this->invitee($token);
        abort_unless($file->calendar_event_id === $invitee->calendar_event_id, 404);

        return CalendarEventController::deliver($file);
    }

    private function invitee(string $token): CalendarEventInvitee
    {
        $invitee = CalendarEventInvitee::where('token', $token)->whereNull('user_id')->with('event')->first();
        abort_unless($invitee && $invitee->event, 404, 'Diese Einladung gibt es nicht (mehr).');

        return $invitee;
    }
}
