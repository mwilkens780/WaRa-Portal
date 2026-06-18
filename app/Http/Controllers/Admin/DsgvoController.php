<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DsgvoRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DsgvoController extends Controller
{
    public function index()
    {
        $requests = DsgvoRequest::with('user')
            ->orderByRaw("FIELD(status, 'offen', 'in_bearbeitung', 'abgeschlossen')")
            ->orderBy('created_at')
            ->paginate(25);

        $openCount = DsgvoRequest::where('status', '!=', 'abgeschlossen')->count();

        return view('admin.dsgvo.index', compact('requests', 'openCount'));
    }

    public function create()
    {
        $users = User::orderBy('lastname')->orderBy('firstname')->get();
        return view('admin.dsgvo.create', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'         => ['nullable', 'exists:users,id'],
            'requester_name'  => ['required', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'type'            => ['required', 'in:' . implode(',', array_keys(DsgvoRequest::$types))],
            'description'     => ['nullable', 'string', 'max:5000'],
        ]);

        DsgvoRequest::create($data);

        return redirect()->route('admin.dsgvo.index')
            ->with('success', 'DSGVO-Anfrage angelegt.');
    }

    public function show(DsgvoRequest $dsgvoRequest)
    {
        $userData = null;
        if ($dsgvoRequest->user_id) {
            $userData = $this->collectUserData($dsgvoRequest->user);
        }

        return view('admin.dsgvo.show', compact('dsgvoRequest', 'userData'));
    }

    public function update(Request $request, DsgvoRequest $dsgvoRequest)
    {
        $data = $request->validate([
            'status'      => ['required', 'in:offen,in_bearbeitung,abgeschlossen'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['status'] === 'abgeschlossen' && $dsgvoRequest->status !== 'abgeschlossen') {
            $data['responded_at'] = now();
        }

        $dsgvoRequest->update($data);

        return back()->with('success', 'Anfrage aktualisiert.');
    }

    /**
     * Anonymize a user (Art. 17 – right to erasure).
     * Replaces all personal identifiers; keeps performance/audit records.
     */
    public function anonymize(Request $request, User $user)
    {
        $request->validate([
            'confirm' => ['required', 'in:LOESCHEN'],
        ]);

        DB::transaction(function () use ($user) {
            $user->update([
                'firstname'                    => 'Gelöscht',
                'lastname'                     => 'Nutzer',
                'name'                         => 'Gelöscht Nutzer',
                'email'                        => null,
                'email2'                       => null,
                'phone'                        => null,
                'mobile'                       => null,
                'birth_date'                   => null,
                'street'                       => null,
                'postal_code'                  => null,
                'city'                         => null,
                'country'                      => null,
                'dsv_id'                       => null,
                'membership_number'            => null,
                'notes'                        => null,
                'trainer_license_nr'           => null,
                'kampfrichter_license_nr'      => null,
                'police_clearance_date'        => null,
                'active'                       => false,
                'password'                     => bcrypt(\Illuminate\Support\Str::random(32)),
                'remember_token'               => null,
                'initial_password'             => null,
            ]);
        });

        return redirect()->route('admin.dsgvo.index')
            ->with('success', "Benutzer #{$user->id} wurde anonymisiert.");
    }

    /** Collect all stored data for a user (for Datenauskunft, Art. 15). */
    private function collectUserData(User $user): array
    {
        return [
            'profil' => $user->only([
                'id', 'firstname', 'lastname', 'email', 'email2',
                'role', 'active', 'birth_date', 'gender',
                'phone', 'mobile', 'street', 'postal_code', 'city', 'country',
                'dsv_id', 'membership_number', 'member_since',
                'trainer_license_nr', 'trainer_license_valid_until',
                'kampfrichter_license_nr', 'kampfrichter_license_valid_until',
                'rescue_certificate_until', 'first_aid_until',
                'police_clearance_date',
                'created_at', 'updated_at',
            ]),

            'trainingsgruppen' => $user->trainingGroups()
                ->get(['training_groups.id', 'training_groups.name'])
                ->toArray(),

            'trainings_anwesenheit' => $user->attendances()
                ->with('session:id,date,title,location')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'training_session_id', 'attended', 'pre_absent', 'created_at'])
                ->toArray(),

            'wettkampf_ergebnisse' => $user->competitionResults()
                ->with('competition:id,name,date,location')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'competition_id', 'discipline', 'distance', 'time_ms', 'age_group', 'created_at'])
                ->toArray(),

            'schwimmzeiten' => $user->swimmingTimes()
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'discipline', 'distance', 'time_ms', 'course', 'created_at'])
                ->toArray(),

            'support_tickets' => \App\Models\SupportTicket::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get(['id', 'type', 'title', 'github_issue_number', 'created_at'])
                ->toArray(),
        ];
    }
}
