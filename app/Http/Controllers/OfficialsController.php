<?php

namespace App\Http\Controllers;

use App\Models\OfficialQualification;
use App\Models\User;
use App\Services\OfficialRequests;
use App\View\Components\OfficialsPanel;
use Illuminate\Http\Request;

/**
 * Kampfrichter-Qualifikationen und Lizenzen (Auftrag Martin, 07.10.2026):
 *  - Kampfrichter pflegen ihre eigenen ("Meine Qualifikationen")
 *  - Vorstand, Geschäftsstelle und Admin pflegen alle ("Kampfrichter & Lizenzen")
 * Die Hauptlizenz ist mit dem Benutzer-Stamm synchron (OfficialQualification).
 */
class OfficialsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isClubManager(), 403);

        $withQuals = OfficialQualification::pluck('user_id')->unique();
        $officials = OfficialRequests::officials()->pluck('id')->merge($withQuals)->unique();

        $users = User::whereIn('id', $officials)->where('active', true)
            ->with(['officialQualifications', 'userRoles'])
            ->orderBy('lastname')->orderBy('firstname')->get();

        $expiringOnly = $request->boolean('auslaufend');
        if ($expiringOnly) {
            $users = $users->filter(fn($u) => $u->officialQualifications->contains(fn($q) => $q->expiresWithin(OfficialsPanel::LICENSE_WARN_MONTHS)))->values();
        }

        return view('officials.index', [
            'users'        => $users,
            'expiringOnly' => $expiringOnly,
            'candidates'   => User::where('active', true)->whereNotIn('id', $officials)->orderBy('lastname')->get(['id', 'firstname', 'lastname']),
        ]);
    }

    public function mine(Request $request)
    {
        return view('officials.mine', ['user' => $request->user()->load('officialQualifications')]);
    }

    public function store(Request $request, User $user)
    {
        $this->authorizeFor($request->user(), $user);
        $user->officialQualifications()->create($this->validated($request));

        return back()->with('success', 'Qualifikation gespeichert.');
    }

    public function update(Request $request, OfficialQualification $qualification)
    {
        $this->authorizeFor($request->user(), $qualification->user);
        $qualification->update($this->validated($request));

        return back()->with('success', 'Qualifikation aktualisiert.');
    }

    public function destroy(Request $request, OfficialQualification $qualification)
    {
        $this->authorizeFor($request->user(), $qualification->user);
        $qualification->delete();

        return back()->with('success', 'Qualifikation entfernt.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'acquired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'license_nr'  => ['nullable', 'string', 'max:50'],
            'valid_until' => ['nullable', 'date'],
            'is_primary'  => ['nullable', 'boolean'],
        ]);
        $data['is_primary'] = $request->boolean('is_primary');

        return $data;
    }

    /** Eigene Daten oder Vereinsverwaltung (Vorstand, Geschäftsstelle, Admin) */
    private function authorizeFor(User $me, User $user): void
    {
        abort_unless($me->id === $user->id || $me->isClubManager(), 403);
    }
}
