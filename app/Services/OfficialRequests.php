<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionOfficialInvitee;
use App\Models\CompetitionOfficialRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Kampfrichter-Abfrage: anfragen, erinnern, Rückmeldung speichern.
 */
class OfficialRequests
{
    public function __construct(private EventMailer $mails) {}

    /** Kampfrichter = Portal-Rolle oder Vereinsrolle "kampfrichter" */
    public static function officials(): Collection
    {
        return User::where('active', true)
            ->where(fn($q) => $q->where('role', 'kampfrichter')
                ->orWhereHas('userRoles', fn($r) => $r->where('role', 'kampfrichter')))
            ->orderBy('lastname')->orderBy('firstname')
            ->get();
    }

    /** Abfrage starten bzw. ergänzen; neue Angefragte bekommen eine Mail */
    public function invite(Competition $competition, array $data, Collection $users, ?User $by): array
    {
        $request = CompetitionOfficialRequest::firstOrCreate(
            ['competition_id' => $competition->id],
            ['created_by_id' => $by?->id]
        );
        $request->fill(array_filter([
            'message'  => $data['message'] ?? null,
            'deadline' => $data['deadline'] ?? null,
        ], fn($v) => $v !== null))->fill(['closed_at' => null])->save();

        $existing = $request->invitees()->pluck('user_id')->flip();
        $new = 0;
        foreach ($users->filter(fn($u) => $u->active)->unique('id') as $user) {
            if (isset($existing[$user->id])) continue;
            $inv = $request->invitees()->create(['user_id' => $user->id, 'invited_at' => now()]);
            $this->mails->officialRequest($inv->setRelation('request', $request->setRelation('competition', $competition))->setRelation('user', $user));
            $new++;
        }

        return [$request, $new];
    }

    public function remind(CompetitionOfficialRequest $request): int
    {
        $n = 0;
        foreach ($request->invitees()->whereNull('responded_at')->with('user')->get() as $inv) {
            $n += $this->mails->officialRequest($inv->setRelation('request', $request), reminder: true);
        }
        return $n;
    }

    /**
     * Bedarf je Abschnitt festlegen: gesuchte Positionen mit Anzahl
     * (Basis der Zuordnung, Auftrag Martin 07.10.2026).
     *
     * @param array<int, array<string, int|string|null>> $grid  Abschnitt => [Position => Anzahl]
     */
    public function saveNeeds(CompetitionOfficialRequest $request, array $grid): int
    {
        if ($request->finalized_at) {
            throw new \DomainException('Die Meldung ist freigegeben. Zum Ändern zuerst die Freigabe zurücknehmen.');
        }
        $sessions = collect($request->sessionsByDay())->flatten()->all();
        $request->needs()->delete();
        $total = 0;
        foreach ($grid as $nr => $positions) {
            if (!in_array((int) $nr, $sessions, true)) continue;
            foreach ((array) $positions as $code => $count) {
                $count = min(20, max(0, (int) $count));
                if (!$count || !isset(CompetitionOfficialRequest::POSITIONS[$code])) continue;
                $request->needs()->create(['session_number' => (int) $nr, 'position' => $code, 'count' => $count]);
                $total += $count;
            }
        }

        // Einsätze ohne passenden Bedarf entfallen
        $needKeys = $request->needs()->get()->map(fn($n) => $n->session_number . '|' . $n->position)->all();
        CompetitionOfficialAssignment::whereIn('competition_official_invitee_id', $request->invitees()->pluck('id'))->get()
            ->reject(fn($a) => in_array($a->session_number . '|' . $a->position, $needKeys, true))
            ->each->delete();

        return $total;
    }

    /**
     * Gesuchte Positionen besetzen: je Abschnitt und Position die gewählten
     * Personen (aus den Rückmeldungen). Eine Person je Abschnitt nur einmal,
     * nur an Tagen, für die sie zugesagt hat, höchstens so viele wie gesucht.
     *
     * @param array<int, array<string, list<int|string|null>>> $slots   Abschnitt => [Position => [invitee_id, …]]
     * @param array<int, string|null>                            $groups  invitee_id => Kampfrichtergruppe
     * @return int Anzahl Einsätze
     */
    public function saveSlots(CompetitionOfficialRequest $request, array $slots, array $groups = []): int
    {
        if ($request->finalized_at) {
            throw new \DomainException('Die Meldung ist freigegeben. Zum Ändern zuerst die Freigabe zurücknehmen.');
        }

        $invitees = $request->invitees()->with('user')->get()->keyBy('id');
        $needs    = $request->needs()->get()->keyBy(fn($n) => $n->session_number . '|' . $n->position);
        $plan     = [];   // invitee_id => [session => position]

        foreach ($slots as $nr => $positions) {
            foreach ((array) $positions as $code => $ids) {
                $need = $needs[(int) $nr . '|' . $code] ?? null;
                if (!$need) continue;
                $ids = array_slice(array_values(array_filter((array) $ids)), 0, $need->count);
                foreach ($ids as $id) {
                    $inv = $invitees[(int) $id] ?? null;
                    if (!$inv) continue;
                    $day = $request->dayOfSession((int) $nr);
                    if ($inv->availableOn($day) !== true) {
                        throw new \DomainException("{$inv->user?->firstname} {$inv->user?->lastname} hat für den Tag von Abschnitt {$nr} nicht zugesagt.");
                    }
                    if (isset($plan[$inv->id][(int) $nr])) {
                        throw new \DomainException("{$inv->user?->firstname} {$inv->user?->lastname} ist in Abschnitt {$nr} zweimal eingeteilt.");
                    }
                    $plan[$inv->id][(int) $nr] = $code;
                }
            }
        }

        $count = 0;
        foreach ($invitees as $inv) {
            $inv->assignments()->delete();
            foreach ($plan[$inv->id] ?? [] as $nr => $code) {
                $inv->assignments()->create(['session_number' => $nr, 'position' => $code]);
                $count++;
            }
            $group = $groups[$inv->id] ?? null;
            $inv->update(['kari_group' => array_key_exists((string) $group, CompetitionOfficialRequest::KARI_GROUPS) ? $group : $inv->kari_group]);
        }

        return $count;
    }
    /** Meldung freigeben: Ab jetzt stehen die Eingesetzten in der Meldedatei */
    public function finalize(CompetitionOfficialRequest $request, User $by, bool $despiteVacancies = false): void
    {
        if (!$request->readyToAssign()) {
            throw new \DomainException('Es haben noch nicht alle geantwortet. Abfrage schließen oder Rückmeldungen abwarten.');
        }
        $open = $request->openCount();
        if ($open > 0 && !$despiteVacancies) {
            throw new \DomainException("Noch {$open} gesuchte Position" . ($open === 1 ? ' ist' : 'en sind') . ' offen. Besetzen oder ausdrücklich trotzdem melden.');
        }
        if (!\App\Models\CompetitionOfficialAssignment::whereIn('competition_official_invitee_id', $request->invitees()->pluck('id'))->exists()) {
            throw new \DomainException('Noch niemand ist einem Abschnitt zugeordnet.');
        }
        $request->update(['finalized_at' => now(), 'finalized_by_id' => $by->id, 'closed_at' => $request->closed_at ?? now()]);
    }

    /**
     * Eingesetzte Kampfrichter für die Meldedatei (nur nach Freigabe).
     *
     * @return Collection<int, CompetitionOfficialInvitee>  mit assignments und user
     */
    public static function reportable(Competition $competition): Collection
    {
        $request = $competition->officialRequest()->whereNotNull('finalized_at')->first();
        if (!$request) return collect();

        return $request->invitees()->whereHas('assignments')->with(['assignments', 'user'])->get()
            ->sortBy(fn($i) => [$i->user?->lastname, $i->user?->firstname])->values();
    }

    /**
     * @param array<string, array{available?: string|bool|null, comment?: ?string}> $days
     */
    public function respond(CompetitionOfficialInvitee $invitee, array $days, array $positions, ?string $comment): void
    {
        if (!$invitee->request->isOpen() || $invitee->request->finalized_at) {
            throw new \DomainException('Die Abfrage ist geschlossen.');
        }

        $availability = [];
        foreach ($invitee->request->days() as $day) {
            $v = $days[$day]['available'] ?? null;
            $availability[$day] = [
                'available' => $v === null || $v === '' ? null : filter_var($v, FILTER_VALIDATE_BOOLEAN),
                'comment'   => trim((string) ($days[$day]['comment'] ?? '')) ?: null,
            ];
        }

        $invitee->update([
            'availability' => $availability,
            'positions'    => array_values(array_intersect(array_keys(CompetitionOfficialRequest::POSITIONS), $positions)),
            'comment'      => trim((string) $comment) ?: null,
            'responded_at' => now(),
        ]);
    }
}
