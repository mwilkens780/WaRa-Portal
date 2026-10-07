<?php

namespace App\View\Components;

use App\Models\Competition;
use App\Models\CompetitionOfficialInvitee;
use App\Models\CompetitionOfficialRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * Kampfgericht auf dem Dashboard (Auftrag Martin, 07.10.2026):
 *
 *  - Kampfrichter: Erinnerungen (offene Anfragen, eigene Lizenz läuft aus),
 *    anstehende Wettkämpfe mit eigener Rückmeldung/Einsatz, letzte Einsätze
 *  - Vorstand: anstehende Wettkämpfe mit Stand der Kampfrichter-Meldung und
 *    konsolidierte Liste auslaufender Lizenzen (6 Monate im Voraus)
 *
 * Auf allen Dashboards eingebunden; zeigt nichts, wenn nichts davon passt.
 */
class OfficialsPanel extends Component
{
    public const LICENSE_WARN_MONTHS = 6;

    public bool $isBoard;
    public bool $managesLicenses;
    public Collection $ownExpiring;
    public bool $isOfficial;
    public Collection $openRequests;
    public Collection $upcoming;
    public Collection $lastAssignments;
    public Collection $boardCompetitions;
    public Collection $expiringLicenses;

    public function __construct()
    {
        $user = auth()->user();

        $this->isBoard    = (bool) $user && CompetitionOfficialRequest::canManage($user);
        // Lizenzen pflegen (Matrix "Kampfrichter & Lizenzen")
        $this->managesLicenses = (bool) $user && $user->canAccess('officials');
        $mine             = $user ? $this->myInvites($user) : collect();
        $this->isOfficial = $user && ($user->hasAnyRole('kampfrichter') || $mine->isNotEmpty() || $user->officialQualifications()->exists());

        // Kampfrichter
        $future = $mine->filter(fn($i) => $this->lastDay($i->request->competition)->gte(today()));
        $this->openRequests = $future->filter(fn($i) => !$i->responded_at && $i->request->isOpen() && !$i->request->finalized_at)->values();
        $this->upcoming     = $future->sortBy(fn($i) => $i->request->competition->date->timestamp)->values();
        $this->lastAssignments = $mine
            ->filter(fn($i) => $i->request->finalized_at && $i->assignments->isNotEmpty() && $this->lastDay($i->request->competition)->lt(today()))
            ->sortByDesc(fn($i) => $i->request->competition->date->timestamp)->take(5)->values();
        // Eigene Qualifikationen, die in 6 Monaten auslaufen (oder abgelaufen sind)
        $this->ownExpiring = $user
            ? $user->officialQualifications()->whereNotNull('valid_until')
                ->whereDate('valid_until', '<=', today()->addMonths(self::LICENSE_WARN_MONTHS))->get()
            : collect();

        // Vorstand
        $this->boardCompetitions = $this->isBoard ? $this->boardCompetitions() : collect();
        $this->expiringLicenses  = $this->managesLicenses ? self::expiringLicenses() : collect();
    }

    public function shouldRender(): bool
    {
        return $this->isBoard || $this->isOfficial || $this->managesLicenses;
    }

    public function render()
    {
        return view('components.officials-panel');
    }

    private function myInvites(User $user): Collection
    {
        return CompetitionOfficialInvitee::where('user_id', $user->id)
            ->with(['request.competition', 'assignments'])
            ->get()
            ->filter(fn($i) => $i->request?->competition);
    }

    private function lastDay(Competition $c): \Illuminate\Support\Carbon
    {
        return $c->date_end ?? $c->date;
    }

    /** Anstehende Wettkämpfe (90 Tage) mit Stand der Kampfrichter-Meldung */
    private function boardCompetitions(): Collection
    {
        return Competition::where(fn($q) => $q->whereDate('date', '>=', today())->orWhereDate('date_end', '>=', today()))
            ->whereDate('date', '<=', today()->addDays(90))
            ->with(['officialRequest.invitees.assignments', 'officialRequest.needs'])
            ->orderBy('date')->limit(10)->get();
    }

    /**
     * Lizenzen, die in den nächsten 6 Monaten auslaufen, und solche, die im
     * letzten Jahr abgelaufen sind (aktive Mitglieder).
     */
    public static function expiringLicenses(): Collection
    {
        return \App\Models\OfficialQualification::whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', today()->addMonths(self::LICENSE_WARN_MONTHS))
            ->whereDate('valid_until', '>=', today()->subYear())
            ->whereHas('user', fn($q) => $q->where('active', true))
            ->with('user:id,firstname,lastname')
            ->orderBy('valid_until')
            ->get();
    }
}
