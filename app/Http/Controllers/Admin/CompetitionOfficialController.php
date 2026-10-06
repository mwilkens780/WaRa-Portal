<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionOfficialRequest;
use App\Models\User;
use App\Services\OfficialRequests;
use Illuminate\Http\Request;

/**
 * Kampfrichter-Abfrage zu einem Wettkampf (Vorstand und Admin):
 * starten bzw. weitere Personen anfragen, erinnern, schließen.
 * Die Übersicht der Rückmeldungen steht im Reiter "Kampfgericht" des Wettkampfs.
 */
class CompetitionOfficialController extends Controller
{
    public function __construct(private OfficialRequests $officials) {}

    public function store(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);

        $data = $request->validate([
            'message'        => ['nullable', 'string', 'max:2000'],
            'deadline'       => ['nullable', 'date'],
            'all_officials'  => ['nullable', 'boolean'],
            'user_ids'       => ['nullable', 'array'],
            'user_ids.*'     => ['integer', 'exists:users,id'],
        ]);

        $users = $request->boolean('all_officials') ? OfficialRequests::officials() : collect();
        $users = $users->merge(User::whereIn('id', $data['user_ids'] ?? [])->get());
        if ($users->isEmpty()) {
            return back()->withErrors(['user_ids' => 'Bitte „Alle Kampfrichter“ wählen oder einzelne Personen.'])->withFragment('kampfgericht');
        }

        [, $new] = $this->officials->invite($competition, $data, $users, $request->user());

        return redirect()->to(route('admin.competitions.show', $competition) . '?tab=kampfgericht')
            ->with('success', $new ? "{$new} Kampfrichter angefragt." : 'Alle Ausgewählten waren schon angefragt.');
    }

    public function remind(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $req = $competition->officialRequest()->firstOrFail();
        $n   = $this->officials->remind($req->setRelation('competition', $competition));

        return redirect()->to(route('admin.competitions.show', $competition) . '?tab=kampfgericht')
            ->with('success', $n ? "Erinnerung an {$n} Kampfrichter verschickt." : 'Niemand hatte eine offene Rückmeldung (oder hat Erinnerungen abgewählt).');
    }

    public function toggle(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $req = $competition->officialRequest()->firstOrFail();
        $req->update(['closed_at' => $req->closed_at ? null : now()]);

        return redirect()->to(route('admin.competitions.show', $competition) . '?tab=kampfgericht')
            ->with('success', $req->closed_at ? 'Abfrage geschlossen.' : 'Abfrage wieder geöffnet.');
    }
}
