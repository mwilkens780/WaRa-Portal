<?php

namespace App\Http\Controllers;

use App\Models\CalendarEventInvitee;
use Illuminate\Http\Request;

/**
 * "Einladungen": alle Termine, zu denen ich (oder ein minderjähriges Kind von
 * mir) eingeladen bin – offene Rückmeldungen zuerst.
 */
class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $ids  = array_merge([$user->id], $user->wards()->pluck('id')->all());

        $invitations = CalendarEventInvitee::whereIn('user_id', $ids)
            ->whereHas('event', fn($q) => $q->where(fn($q) => $q
                ->whereDate('start_date', '>=', today()->subDays(30))
                ->orWhereDate('end_date', '>=', today()->subDays(30))))
            ->with(['event', 'user:id,firstname,lastname'])
            ->get()
            ->sortBy(fn($i) => [$i->event->start_date->lt(today()) ? 1 : 0, $i->event->start_date->timestamp]);

        // Kampfrichter-Anfragen zu anstehenden Wettkämpfen
        $officialInvites = \App\Models\CompetitionOfficialInvitee::where('user_id', $user->id)
            ->whereHas('request.competition', fn($q) => $q->where(fn($q) => $q
                ->whereDate('date', '>=', today())->orWhereDate('date_end', '>=', today())))
            ->with('request.competition')
            ->get()
            ->sortBy(fn($i) => $i->request->competition->date->timestamp)
            ->values();

        return view('invitations.index', [
            'upcoming'        => $invitations->filter(fn($i) => ($i->event->end_date ?? $i->event->start_date)->gte(today()))->values(),
            'past'            => $invitations->filter(fn($i) => ($i->event->end_date ?? $i->event->start_date)->lt(today()))->values(),
            'officialInvites' => $officialInvites,
            'user'            => $user,
        ]);
    }
}
