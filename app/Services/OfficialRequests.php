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
     * @param array<string, array{available?: string|bool|null, comment?: ?string}> $days
     */
    public function respond(CompetitionOfficialInvitee $invitee, array $days, array $positions, ?string $comment): void
    {
        if (!$invitee->request->isOpen()) {
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
