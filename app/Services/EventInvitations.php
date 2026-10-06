<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventFile;
use App\Models\CalendarEventInvitee;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Einladungen zu Kalenderterminen: wer eingeladen wird, Versand, Rückmeldung.
 *
 * Zielgruppen (Entscheidungen Martin, 06.10.2026):
 *  - Vorstandssitzung: alle Vorstandsmitglieder (Portal- oder Vereinsrolle)
 *  - Elternabend:      je gewählter Gruppe die Eltern minderjähriger Mitglieder
 *                      und die volljährigen Mitglieder selbst
 *  - Team-Event:       Mitglieder gewählter Gruppen und einzelne Schwimmer;
 *                      bei Minderjährigen erhalten die Eltern die Mail mit und
 *                      dürfen zusagen
 *  - immer zusätzlich: Gäste aus dem Portal und Gäste per E-Mail (Zusage-Link)
 */
class EventInvitations
{
    public function __construct(private EventMailer $mails) {}

    /**
     * Empfänger aus der Auswahl im Formular.
     *
     * @param array{all_board?: bool, group_ids?: int[], user_ids?: int[], guest_user_ids?: int[]} $sel
     * @return Collection<int, array{user: User, source: string}>  nach user_id
     */
    public function audience(CalendarEvent $event, array $sel): Collection
    {
        $out = collect();
        $add = function (User $u, string $source) use ($out) {
            if ($u->active && !$out->has($u->id)) $out->put($u->id, ['user' => $u, 'source' => $source]);
        };

        if ($event->audience === 'vorstand' && !empty($sel['all_board'])) {
            foreach (self::boardMembers() as $u) $add($u, 'vorstand');
        }

        $groups = TrainingGroup::whereIn('id', $sel['group_ids'] ?? [])->with('swimmers.parents')->get();

        if ($event->audience === 'eltern') {
            foreach ($groups as $g) {
                foreach ($g->swimmers as $member) {
                    if ($this->adultOn($member, $event)) {
                        $add($member, 'gruppe');
                    } else {
                        foreach ($member->parents as $parent) $add($parent, 'eltern');
                    }
                }
            }
        }

        if ($event->audience === 'team') {
            foreach ($groups as $g) {
                foreach ($g->swimmers as $member) $add($member, 'gruppe');
            }
            foreach (User::whereIn('id', $sel['user_ids'] ?? [])->get() as $u) $add($u, 'einzeln');
        }

        foreach (User::whereIn('id', $sel['guest_user_ids'] ?? [])->get() as $u) $add($u, 'gast');

        return $out;
    }

    /** Vorstand = Portal-Rolle oder Vereinsrolle "vorstand" */
    public static function boardMembers(): Collection
    {
        return User::where('active', true)
            ->where(fn($q) => $q->where('role', 'vorstand')
                ->orWhereHas('userRoles', fn($r) => $r->where('role', 'vorstand')))
            ->orderBy('lastname')->orderBy('firstname')
            ->get();
    }

    /** Volljährig am Tag des Termins? Ohne Geburtsdatum: nein (dann Eltern) */
    private function adultOn(User $u, CalendarEvent $event): bool
    {
        return $u->birth_date && $u->birth_date->copy()->addYears(18)->lte($event->start_date);
    }

    /**
     * Gast-Adressen aus Freitext: je Zeile "Name <mail@x.de>", "mail@x.de" oder "Name, mail@x.de".
     *
     * @return list<array{name: ?string, email: string}>
     */
    public static function parseGuests(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\r\n;]+/', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '' || !preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $line, $m)) continue;
            $email = mb_strtolower($m[1]);
            $name  = trim(str_replace([$m[1], '<', '>', ','], '', $line)) ?: null;
            $out[$email] = ['name' => $name, 'email' => $email];
        }
        return array_values($out);
    }

    /**
     * Legt Einladungen an und verschickt sie. Bereits Eingeladene bleiben
     * unberührt (keine zweite Mail).
     *
     * @return int Anzahl neuer Einladungen
     */
    public function invite(CalendarEvent $event, Collection $audience, array $guests = []): int
    {
        $new = collect();

        DB::transaction(function () use ($event, $audience, $guests, $new) {
            $existingUsers  = $event->invitees()->whereNotNull('user_id')->pluck('user_id')->flip();
            $existingGuests = $event->invitees()->whereNotNull('guest_email')->pluck('guest_email')->flip();

            foreach ($audience as $userId => $row) {
                if (isset($existingUsers[$userId])) continue;
                $new->push($event->invitees()->create([
                    'user_id' => $userId, 'source' => $row['source'], 'invited_at' => now(),
                ]));
            }
            foreach ($guests as $g) {
                // Hat der Gast ein Konto, wird das Konto eingeladen
                $account = User::where('email', $g['email'])->where('active', true)->first();
                if ($account) {
                    if (isset($existingUsers[$account->id])) continue;
                    $existingUsers[$account->id] = true;
                    $new->push($event->invitees()->create(['user_id' => $account->id, 'source' => 'gast', 'invited_at' => now()]));
                    continue;
                }
                if (isset($existingGuests[$g['email']])) continue;
                $new->push($event->invitees()->create([
                    'guest_name' => $g['name'], 'guest_email' => $g['email'], 'source' => 'gast',
                    'token' => CalendarEventInvitee::newToken(), 'invited_at' => now(),
                ]));
            }
        });

        foreach ($new as $invitee) {
            $this->mails->calendarInvitation($invitee->setRelation('event', $event));
        }

        return $new->count();
    }

    /** Rückmeldung speichern. Wirft bei geschlossener Anmeldung oder vollem Termin. */
    public function respond(CalendarEventInvitee $invitee, string $status, ?string $comment, ?User $by): void
    {
        $event = $invitee->event;
        if (!array_key_exists($status, CalendarEventInvitee::STATUSES) || $status === 'offen') {
            throw new \InvalidArgumentException('Unbekannte Rückmeldung.');
        }
        if (!$event->rsvpOpen()) {
            throw new \DomainException('Die Anmeldung ist geschlossen.');
        }

        DB::transaction(function () use ($invitee, $event, $status, $comment, $by) {
            // Plätze: gleichzeitige Zusagen dürfen die Grenze nicht überschreiten
            if ($status === 'zugesagt' && $invitee->status !== 'zugesagt' && $event->capacity) {
                $taken = CalendarEventInvitee::where('calendar_event_id', $event->id)
                    ->where('status', 'zugesagt')->lockForUpdate()->count();
                if ($taken >= $event->capacity) {
                    throw new \DomainException('Alle Plätze sind vergeben.');
                }
            }

            $invitee->update([
                'status'          => $status,
                'comment'         => $comment !== null ? mb_substr(trim($comment), 0, 500) ?: null : $invitee->comment,
                'responded_at'    => now(),
                'responded_by_id' => $by?->id,
            ]);
        });
    }

    /** Erinnerung an alle ohne Rückmeldung */
    public function remind(CalendarEvent $event): int
    {
        $n = 0;
        foreach ($event->invitees()->where('status', 'offen')->with('user')->get() as $invitee) {
            $n += $this->mails->calendarReminder($invitee->setRelation('event', $event));
        }
        $event->update(['reminder_sent_at' => now()]);

        return $n;
    }

    /** Neues Protokoll: Hinweis an alle Eingeladenen */
    public function protocolAdded(CalendarEvent $event, CalendarEventFile $file): int
    {
        $n = 0;
        foreach ($event->invitees()->with('user')->get() as $invitee) {
            $n += $this->mails->calendarProtocol($invitee->setRelation('event', $event), $file);
        }
        return $n;
    }
}
