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
     * Zuordnung des Kampfrichterobmanns speichern: je Person und Abschnitt eine
     * Position (leer = nicht eingesetzt) und die Kampfrichtergruppe.
     * Eingesetzt wird nur, wer für den Tag des Abschnitts zugesagt hat.
     *
     * @param array<int, array{sessions?: array<int, ?string>, group?: ?string}> $rows  invitee_id => …
     * @return int Anzahl Einsätze
     */
    public function saveAssignments(CompetitionOfficialRequest $request, array $rows): int
    {
        if ($request->finalized_at) {
            throw new \DomainException('Die Meldung ist freigegeben. Zum Ändern zuerst die Freigabe zurücknehmen.');
        }

        $valid = array_keys(CompetitionOfficialRequest::POSITIONS);
        $count = 0;

        foreach ($request->invitees()->with('assignments')->get() as $inv) {
            $row = $rows[$inv->id] ?? [];
            $inv->update(['kari_group' => array_key_exists($row['group'] ?? '', CompetitionOfficialRequest::KARI_GROUPS) ? $row['group'] : null]);
            $inv->assignments()->delete();

            foreach ($row['sessions'] ?? [] as $nr => $position) {
                $day = $request->dayOfSession((int) $nr);
                if (!$position || !in_array($position, $valid, true) || !$day || $inv->availableOn($day) !== true) continue;
                $inv->assignments()->create(['session_number' => (int) $nr, 'position' => $position]);
                $count++;
            }
        }

        return $count;
    }

    /** Meldung freigeben: Ab jetzt stehen die Eingesetzten in der Meldedatei */
    public function finalize(CompetitionOfficialRequest $request, User $by): void
    {
        if (!$request->readyToAssign()) {
            throw new \DomainException('Es haben noch nicht alle geantwortet. Abfrage schließen oder Rückmeldungen abwarten.');
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
