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

    /** Bedarf: gesuchte Positionen je Abschnitt (Kampfrichterobmann) */
    public function needs(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $req = $competition->officialRequest()->firstOrFail()->setRelation('competition', $competition);
        $data = $request->validate(['need' => ['nullable', 'array'], 'need.*' => ['array'], 'need.*.*' => ['nullable', 'integer', 'min:0', 'max:20']]);

        try {
            $n = $this->officials->saveNeeds($req, $data['need'] ?? []);
        } catch (\DomainException $e) {
            return $this->back($competition)->withErrors(['assign' => $e->getMessage()]);
        }

        return $this->back($competition)->with('success', "Bedarf gespeichert: {$n} gesuchte Positionen.");
    }

    /** Gesuchte Positionen aus den Rückmeldungen besetzen */
    public function assign(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $req = $competition->officialRequest()->firstOrFail()->setRelation('competition', $competition);

        $data = $request->validate([
            'slots'      => ['nullable', 'array'],
            'groups'     => ['nullable', 'array'],
            'groups.*'   => ['nullable', 'in:' . implode(',', array_keys(CompetitionOfficialRequest::KARI_GROUPS))],
        ]);

        try {
            $n = $this->officials->saveSlots($req, $data['slots'] ?? [], $data['groups'] ?? []);
        } catch (\DomainException $e) {
            return $this->back($competition)->withErrors(['assign' => $e->getMessage()])->withInput();
        }

        $open = $req->openCount();
        return $this->back($competition)->with('success', "Besetzung gespeichert: {$n} Einsätze" . ($open ? ", noch {$open} offen." : ' – alle gesuchten Positionen sind besetzt.'));
    }
    public function finalize(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $req = $competition->officialRequest()->with(['invitees', 'needs'])->firstOrFail()->setRelation('competition', $competition);

        try {
            $this->officials->finalize($req, $request->user(), $request->boolean('despite_vacancies'));
        } catch (\DomainException $e) {
            return $this->back($competition)->withErrors(['assign' => $e->getMessage()]);
        }

        return $this->back($competition)->with('success', 'Meldung freigegeben – die Kampfrichter stehen jetzt in der Meldedatei (KARIMELDUNG).');
    }

    public function unfinalize(Request $request, Competition $competition)
    {
        abort_unless(CompetitionOfficialRequest::canManage($request->user()), 403);
        $competition->officialRequest()->firstOrFail()->update(['finalized_at' => null, 'finalized_by_id' => null]);

        return $this->back($competition)->with('success', 'Freigabe zurückgenommen – die Zuordnung kann wieder geändert werden.');
    }

    private function back(Competition $competition)
    {
        return redirect()->to(route('admin.competitions.show', $competition) . '?tab=kampfgericht');
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
