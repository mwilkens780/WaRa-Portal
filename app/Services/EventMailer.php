<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\CompetitionSignupRequest;
use App\Models\CompetitionSignupResponse;
use App\Models\GroupMottoWeek;
use App\Models\Record;
use App\Models\SwimmerGoal;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\MailTopic;
use Illuminate\Support\Collection;

/**
 * Baut die Ereignis-Mails und bestimmt, wer sie bekommt.
 *
 * Die Auswahl der Empfaenger gehoert an eine Stelle, nicht in jeden
 * Controller: Wer eine Wettkampfeinladung bekommt, ist dieselbe Frage wie
 * bei der Erinnerung, und Eltern gehoeren in beiden Faellen dazu.
 *
 * Ob jemand die Mail wirklich bekommt, entscheidet der Mailer anhand der
 * Profileinstellung - hier wird nur bestimmt, wer ueberhaupt in Frage kommt.
 */
class EventMailer
{
    /** Lagen ausgeschrieben - fuer Mailtexte, die keine Kuerzel vertragen. */
    private const DISCIPLINES = [
        'F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust',
        'S' => 'Schmetterling', 'L' => 'Lagen',
    ];

    public function __construct(private Mailer $mailer) {}

    /**
     * Ziel eines Knopfes, passend zur Rolle des Empfaengers.
     *
     * Eltern duerfen die Schwimmer-Seiten nicht oeffnen - ein Link dorthin
     * endete fuer sie in einer Fehlermeldung. Dann lieber ins Elternportal,
     * und wenn es auch das nicht gibt, auf die Startseite.
     */
    private function linkFor(User $user, string $swimmerRoute, array $params = [], ?User $swimmer = null): string
    {
        // Eltern: direkt auf die Seite des betroffenen Kindes, wo es eine gibt
        if ($swimmer && $user->id !== $swimmer->id) {
            $parentRoute = self::PARENT_ROUTES[$swimmerRoute] ?? null;
            if ($parentRoute && \Illuminate\Support\Facades\Route::has($parentRoute)) {
                return route($parentRoute, ['childId' => $swimmer->id]);
            }
            if (\Illuminate\Support\Facades\Route::has('parent.dashboard')) {
                return route('parent.dashboard');
            }
        }
        if ($user->role === 'schwimmer' && \Illuminate\Support\Facades\Route::has($swimmerRoute)) {
            return route($swimmerRoute, $params);
        }
        if ($user->role === 'elternteil' && \Illuminate\Support\Facades\Route::has('parent.dashboard')) {
            return route('parent.dashboard');
        }

        return config('app.url');
    }

    /** Schwimmer-Seite → Seite desselben Inhalts fuer Eltern (je Kind) */
    private const PARENT_ROUTES = [
        'swimmer.competitions' => 'parent.child.competitions',
        'swimmer.times'        => 'parent.child.times',
    ];

    // ── Wettkämpfe ───────────────────────────────────────────────────────────

    /** Abfrage wurde gestartet: Einladung an die berechtigten Sportler. */
    public function signupActivated(CompetitionSignupRequest $request, Collection $users): int
    {
        $competition = $request->competition;
        $deadline    = $request->deadline ?? $request->response_deadline ?? null;

        return $this->toSwimmers($users, 'competition_invitation', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
            subjectText: $forParent ? "Einladung für {$kid->firstname}: {$competition->name}" : 'Einladung: ' . $competition->name,
            heading:     'Einladung zum Wettkampf',
            paragraphs:  [$forParent
                ? "für den folgenden Wettkampf brauchen wir eine Rückmeldung, ob {$kid->firstname} dabei ist. "
                  . 'Ihr könnt direkt im Portal zu- oder absagen.'
                : 'für den folgenden Wettkampf brauchen wir deine Rückmeldung – sag uns bitte im Portal, '
                  . 'ob du dabei bist.',
            ],
            facts: array_filter([
                'Für'       => $forParent ? $kid->name : null,
                'Wettkampf' => $competition->name,
                'Datum'     => $competition->date?->format('d.m.Y'),
                'Ort'       => $competition->location,
                'Rückmeldung bis' => $deadline instanceof \DateTimeInterface ? $deadline->format('d.m.Y') : null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.competitions', [], $kid),
            actionLabel: 'Jetzt rückmelden',
            greetingName: $user->firstname,
        ));
    }

    /** Erinnerung an alle, die noch nicht geantwortet haben. */
    public function signupReminder(CompetitionSignupRequest $request, Collection $responses): int
    {
        $competition = $request->competition;

        $users = $responses->map(fn(CompetitionSignupResponse $r) => $r->user)->filter();

        return $this->toSwimmers($users, 'competition_reminder', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
            subjectText: $forParent
                ? "Erinnerung: Rückmeldung für {$kid->firstname} zu {$competition->name} fehlt noch"
                : 'Erinnerung: Rückmeldung zu ' . $competition->name . ' fehlt noch',
            heading:     $forParent ? "Rückmeldung für {$kid->firstname} fehlt noch" : 'Deine Rückmeldung fehlt noch',
            paragraphs:  [$forParent
                ? "zu diesem Wettkampf haben wir für {$kid->firstname} noch keine Antwort. Bitte meldet euch kurz zurück – "
                  . 'auch eine Absage hilft uns bei der Planung.'
                : 'zu diesem Wettkampf haben wir von dir noch keine Antwort. Bitte melde dich kurz zurück – '
                  . 'auch eine Absage hilft uns bei der Planung.'],
            facts: array_filter([
                'Für'       => $forParent ? $kid->name : null,
                'Wettkampf' => $competition->name,
                'Datum'     => $competition->date?->format('d.m.Y'),
                'Ort'       => $competition->location,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.competitions', [], $kid),
            actionLabel: 'Jetzt rückmelden',
            greetingName: $user->firstname,
        ));
    }

    /** Jemand hat geantwortet: Info an die Trainer seiner Gruppen. */
    public function signupResponded(CompetitionSignupResponse $response): int
    {
        $swimmer     = $response->user;
        $competition = $response->signupRequest?->competition;
        if (!$swimmer || !$competition) return 0;

        $antwort = match ($response->status) {
            'attending'     => 'nimmt teil',
            'not_attending' => 'sagt ab',
            default         => 'hat geantwortet',
        };

        return $this->toTrainers($swimmer, 'signup_responses', fn(User $trainer) => new NotificationMail(
            subjectText: "Rückmeldung: {$swimmer->name} {$antwort} – {$competition->name}",
            heading:     'Rückmeldung zu einer Anmeldeabfrage',
            paragraphs:  ["{$swimmer->name} {$antwort}."],
            facts: array_filter([
                'Wettkampf'  => $competition->name,
                'Datum'      => $competition->date?->format('d.m.Y'),
                'Antwort'    => $antwort,
                'Anmerkung'  => $response->note,
            ]),
            actionUrl:   route('admin.competitions.show', $competition),
            actionLabel: 'Abfrage ansehen',
            greetingName: $trainer->firstname,
        ));
    }

    // ── Training ─────────────────────────────────────────────────────────────

    /** Selbsteinschaetzung abgegeben: Info an die Trainer. */
    public function selfAssessmentSubmitted(User $swimmer, TrainingSession $session, ?string $note = null): int
    {
        return $this->toTrainers($swimmer, 'self_assessments', fn(User $trainer) => new NotificationMail(
            subjectText: "Selbsteinschätzung von {$swimmer->name}",
            heading:     'Neue Selbsteinschätzung',
            paragraphs:  ["{$swimmer->name} hat eine Selbsteinschätzung zu einer Trainingseinheit abgegeben."],
            facts: array_filter([
                'Einheit'     => $session->title,
                'Datum'       => $session->date?->format('d.m.Y'),
                'Anmerkung'   => $note,
            ]),
            actionUrl:   route('trainer.sessions.show', $session),
            actionLabel: 'Einheit ansehen',
            greetingName: $trainer->firstname,
        ));
    }

    // ── Ziele ────────────────────────────────────────────────────────────────

    /** Ziel eingetragen oder geaendert: Info an die Trainer. */
    public function goalSubmitted(SwimmerGoal $goal, bool $isNew = true): int
    {
        $swimmer = $goal->user;
        if (!$swimmer) return 0;

        $was = $isNew ? 'ein neues Ziel eingetragen' : 'ein Ziel geändert';

        return $this->toTrainers($swimmer, 'goal_submissions', fn(User $trainer) => new NotificationMail(
            subjectText: "Ziel von {$swimmer->name}",
            heading:     'Zielsetzung',
            paragraphs:  ["{$swimmer->name} hat {$was}."],
            facts: array_filter([
                'Ziel'      => $goal->title ?? $goal->discipline_label ?? null,
                'Art'       => $goal->type_label ?? null,
                'Zielzeit'  => $goal->formatted_target_time ?? null,
            ]),
            actionUrl:   route('trainer.goals.index'),
            actionLabel: 'Ziele ansehen',
            greetingName: $trainer->firstname,
        ));
    }

    /** Trainer hat ein Ziel kommentiert: Rueckmeldung an den Sportler. */
    public function goalCommented(SwimmerGoal $goal, string $comment): int
    {
        $swimmer = $goal->user;
        if (!$swimmer) return 0;

        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
            subjectText: $forParent ? "Rückmeldung zu einem Ziel von {$kid->firstname}" : 'Rückmeldung zu deinem Ziel',
            heading:     $forParent ? "Trainer-Rückmeldung für {$kid->firstname}" : 'Dein Trainer hat dir geschrieben',
            paragraphs:  [$forParent ? "zu einem Ziel von {$kid->firstname} gibt es eine Rückmeldung:" : 'zu einem deiner Ziele gibt es eine Rückmeldung:',
                          '„' . $comment . '"'],
            facts: array_filter([
                'Für'      => $forParent ? $kid->name : null,
                'Ziel'     => $goal->title,
                'Zielzeit' => $goal->formatted_target_time ?? null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.goals.index', [], $kid),
            actionLabel: $forParent ? 'Im Portal ansehen' : 'Ziel ansehen',
            greetingName: $user->firstname,
        ));
    }

    /** Trainer hat ein persoenliches Ziel bewertet: Info an den Sportler. */
    public function goalEvaluated(SwimmerGoal $goal): int
    {
        $swimmer = $goal->user;
        if (!$swimmer) return 0;

        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
            subjectText: $forParent ? "Ein Ziel von {$kid->firstname} wurde bewertet" : 'Dein Ziel wurde bewertet',
            heading:     $forParent ? "Rückmeldung zu einem Ziel von {$kid->firstname}" : 'Rückmeldung zu deinem Ziel',
            paragraphs:  [$forParent ? "ein Trainer hat ein Ziel von {$kid->firstname} bewertet." : 'ein Trainer hat eines deiner Ziele bewertet.'],
            facts: array_filter([
                'Für'     => $forParent ? $kid->name : null,
                'Ziel'    => $goal->title ?? $goal->discipline_label ?? null,
                'Stand'   => $goal->status_label ?? null,
                'Zeit'    => $goal->formatted_achieved_time ?? null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.goals.index', [], $kid),
            actionLabel: $forParent ? 'Im Portal ansehen' : 'Ziel ansehen',
            greetingName: $user->firstname,
        ));
    }

    /** Leistungskriterium bewertet: Info an den Sportler. */
    public function criterionEvaluated(TrainingGroupGoal $criterion, User $swimmer, bool $achieved, ?string $note = null): int
    {
        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
            subjectText: $forParent ? "Leistungskriterium für {$kid->firstname} bewertet" : 'Leistungskriterium bewertet',
            heading:     'Bewertung eines Leistungskriteriums',
            paragraphs:  [$forParent
                ? "ein Trainer hat ein Leistungskriterium für {$kid->firstname} bewertet."
                : 'ein Trainer hat ein Leistungskriterium für dich bewertet.'],
            facts: array_filter([
                'Für'       => $forParent ? $kid->name : null,
                'Kriterium' => $criterion->title,
                'Bewertung' => $achieved ? 'erreicht' : 'noch nicht erreicht',
                'Notiz'     => $note,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.group-goals.index', [], $kid),
            actionLabel: $forParent ? 'Im Portal ansehen' : 'Leistungskriterien ansehen',
            greetingName: $user->firstname,
        ));
    }

    // ── Rekorde ──────────────────────────────────────────────────────────────

    /** Neuer Vereinsrekord: an den Schwimmer selbst und an die Trainer. */
    public function clubRecord(Record $record): int
    {
        $swimmer = $record->user;
        $strecke = $record->distance . ' m ' . (self::DISCIPLINES[$record->discipline] ?? $record->discipline);

        $facts = array_filter([
            'Strecke' => $strecke,
            'Bahn'    => $record->course,
            'Zeit'    => $record->formatted_time,
            'Datum'   => $record->set_date?->format('d.m.Y'),
            'Ort'     => $record->location,
        ]);

        $sent = 0;

        if ($swimmer) {
            $sent += $this->toSwimmers(collect([$swimmer]), 'own_records', fn(User $user, User $kid, bool $forParent) => new NotificationMail(
                subjectText: $forParent ? "Vereinsrekord von {$kid->firstname}: {$strecke}" : "Vereinsrekord: {$strecke}",
                heading:     'Neuer Vereinsrekord',
                paragraphs:  [$forParent
                    ? "{$kid->firstname} hat einen neuen Vereinsrekord aufgestellt. Herzlichen Glückwunsch!"
                    : 'du hast einen neuen Vereinsrekord aufgestellt. Glückwunsch!'],
                facts:       $forParent ? ['Schwimmer' => $kid->name] + $facts : $facts,
                actionUrl:   $this->linkFor($user, 'swimmer.times', [], $kid),
                actionLabel: 'Zeiten ansehen',
                greetingName: $user->firstname,
            ));
        }

        $trainerMail = fn(User $trainer) => new NotificationMail(
            subjectText: 'Neuer Vereinsrekord: ' . ($record->swimmer_name ?: 'unbekannt'),
            heading:     'Neuer Vereinsrekord',
            paragraphs:  [($record->swimmer_name ?: 'Jemand') . ' hat einen neuen Vereinsrekord aufgestellt.'],
            facts:       $facts,
            actionUrl:   route('admin.records.index'),
            actionLabel: 'Rekorde ansehen',
            greetingName: $trainer->firstname,
        );

        $trainers = $swimmer
            ? $this->trainersOf($swimmer)
            : User::whereIn('role', ['trainer', 'vorstand', 'admin'])->where('active', true)->get();

        foreach ($trainers as $trainer) {
            $log = $this->mailer->send($trainer, 'club_records', $trainerMail($trainer), $trainerMail($trainer)->defaultSubject());
            if ($log->status === 'sent') $sent++;
        }

        return $sent;
    }

    // ── Motto der Woche ──────────────────────────────────────────────────────

    /** Erinnerung an die Person, die in der kommenden Woche dran ist. */
    public function mottoReminder(GroupMottoWeek $week): int
    {
        if (!$week->user) return 0;

        return $this->toSwimmers(collect([$week->user]), 'motto_reminder', fn(User $user) => new NotificationMail(
            subjectText: 'Dein Motto der Woche fehlt noch',
            heading:     'Motto der Woche',
            paragraphs:  ['in der kommenden Woche bist du mit dem Motto dran – es steht aber noch keines im Portal.'],
            facts: array_filter([
                'Woche'  => 'ab ' . $week->week_start->format('d.m.Y'),
                'Gruppe' => $week->group?->name,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.motto.index'),
            actionLabel: 'Motto eintragen',
            greetingName: $user->firstname,
        ));
    }

    // ── Training ─────────────────────────────────────────────────────────────

    /** Termin faellt aus: an alle, die dazugehoeren, und deren Eltern (Warteschlange - kann viele treffen). */
    public function trainingCancelled(TrainingSession $session): int
    {
        $queued = 0;
        foreach ($this->withParents($session->participants()) as [$recipient, $swimmer]) {
            $forParent = $recipient->id !== $swimmer->id;
            $mail = new NotificationMail(
                subjectText: 'Training fällt aus: ' . $session->title . ', ' . $session->date->isoFormat('dd DD.MM.'),
                heading:     'Training fällt aus',
                paragraphs:  array_values(array_filter([
                    $forParent
                        ? "das Training von {$swimmer->firstname} am {$session->date->isoFormat('dddd, D. MMMM')} fällt aus."
                        : "dein Training am {$session->date->isoFormat('dddd, D. MMMM')} fällt aus.",
                    $session->cancel_reason ? 'Grund: ' . $session->cancel_reason : null,
                ])),
                facts: array_filter([
                    'Training' => $session->title,
                    'Datum'    => $session->date->format('d.m.Y'),
                    'Uhrzeit'  => substr($session->start_time, 0, 5) . ($session->end_time ? '–' . substr($session->end_time, 0, 5) : ''),
                    'Ort'      => $session->location,
                ]),
                actionUrl:   $forParent && \Illuminate\Support\Facades\Route::has('parent.child.trainings')
                    ? route('parent.child.trainings', ['childId' => $swimmer->id])
                    : $this->linkFor($recipient, 'swimmer.sessions'),
                actionLabel: 'Trainings ansehen',
                greetingName: $recipient->firstname,
            );
            $log = $this->mailer->queue($recipient, 'training_changes', $mail, $mail->defaultSubject());
            if ($log->status === 'pending') $queued++;
        }

        return $queued;
    }

    // ── Empfängerkreise ──────────────────────────────────────────────────────

    /**
     * An Sportler und - falls hinterlegt - deren Eltern.
     *
     * Eltern bekommen dieselbe Nachricht: Bei juengeren Mitgliedern liest der
     * Nachwuchs seine Mails nicht, die Eltern schon.
     */
    private function toSwimmers(Collection $users, string $topic, callable $build): int
    {
        $sent = 0;

        // $build(Empfaenger, betroffener Sportler, schreibt-an-Eltern?)
        foreach ($this->withParents($users) as [$recipient, $swimmer]) {
            $mail = $build($recipient, $swimmer, $recipient->id !== $swimmer->id);
            $log  = $this->mailer->send($recipient, $topic, $mail, $mail->defaultSubject());
            if ($log->status === 'sent') $sent++;
        }

        return $sent;
    }

    private function toTrainers(User $swimmer, string $topic, callable $build): int
    {
        $sent = 0;

        foreach ($this->trainersOf($swimmer) as $trainer) {
            $mail = $build($trainer);
            $log  = $this->mailer->send($trainer, $topic, $mail, $mail->defaultSubject());
            if ($log->status === 'sent') $sent++;
        }

        return $sent;
    }

    /** Trainer aller Gruppen, in denen dieser Sportler schwimmt. */
    public function trainersOf(User $swimmer): Collection
    {
        $groupIds = $swimmer->trainingGroups()->pluck('training_groups.id');
        if ($groupIds->isEmpty()) return collect();

        return User::where('active', true)
            ->whereHas('trainerGroups', fn($q) => $q->whereIn('training_groups.id', $groupIds))
            ->get()
            ->unique('id');
    }

    /**
     * Paare [Empfaenger, betroffener Sportler]: der Sportler selbst und
     * seine Eltern.
     *
     * Frueher wurden Empfaenger ueber alle Sportler hinweg entdoppelt - wer
     * zwei eingeladene Kinder hatte, bekam eine einzige Mail, die nicht sagte,
     * um welches Kind es geht. Jetzt gibt es je Kind eine eigene Mail.
     */
    private function withParents(Collection $users): Collection
    {
        $pairs = collect();

        foreach ($users->filter()->unique('id') as $swimmer) {
            $pairs->push([$swimmer, $swimmer]);
            try {
                foreach ($swimmer->parents()->where('active', true)->get() as $parent) {
                    if ($parent->id !== $swimmer->id) $pairs->push([$parent, $swimmer]);
                }
            } catch (\Throwable) {
                // Beziehung nicht vorhanden - dann eben nur der Sportler
            }
        }

        return $pairs;
    }
}
