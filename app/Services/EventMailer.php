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
    private function linkFor(User $user, string $swimmerRoute, array $params = []): string
    {
        if ($user->role === 'schwimmer' && \Illuminate\Support\Facades\Route::has($swimmerRoute)) {
            return route($swimmerRoute, $params);
        }
        if ($user->role === 'elternteil' && \Illuminate\Support\Facades\Route::has('parent.dashboard')) {
            return route('parent.dashboard');
        }

        return config('app.url');
    }

    // ── Wettkämpfe ───────────────────────────────────────────────────────────

    /** Abfrage wurde gestartet: Einladung an die berechtigten Sportler. */
    public function signupActivated(CompetitionSignupRequest $request, Collection $users): int
    {
        $competition = $request->competition;
        $deadline    = $request->deadline ?? $request->response_deadline ?? null;

        return $this->toSwimmers($users, 'competition_invitation', fn(User $user) => new NotificationMail(
            subjectText: 'Einladung: ' . $competition->name,
            heading:     'Einladung zum Wettkampf',
            paragraphs:  [
                'für den folgenden Wettkampf brauchen wir deine Rückmeldung – sag uns bitte im Portal, '
                . 'ob du dabei bist.',
            ],
            facts: array_filter([
                'Wettkampf' => $competition->name,
                'Datum'     => $competition->date?->format('d.m.Y'),
                'Ort'       => $competition->location,
                'Rückmeldung bis' => $deadline instanceof \DateTimeInterface ? $deadline->format('d.m.Y') : null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.competitions'),
            actionLabel: 'Jetzt rückmelden',
            greetingName: $user->firstname,
        ));
    }

    /** Erinnerung an alle, die noch nicht geantwortet haben. */
    public function signupReminder(CompetitionSignupRequest $request, Collection $responses): int
    {
        $competition = $request->competition;

        $users = $responses->map(fn(CompetitionSignupResponse $r) => $r->user)->filter();

        return $this->toSwimmers($users, 'competition_reminder', fn(User $user) => new NotificationMail(
            subjectText: 'Erinnerung: Rückmeldung zu ' . $competition->name . ' fehlt noch',
            heading:     'Deine Rückmeldung fehlt noch',
            paragraphs:  ['zu diesem Wettkampf haben wir von dir noch keine Antwort. Bitte melde dich kurz zurück – '
                          . 'auch eine Absage hilft uns bei der Planung.'],
            facts: array_filter([
                'Wettkampf' => $competition->name,
                'Datum'     => $competition->date?->format('d.m.Y'),
                'Ort'       => $competition->location,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.competitions'),
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

        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user) => new NotificationMail(
            subjectText: 'Rückmeldung zu deinem Ziel',
            heading:     'Dein Trainer hat dir geschrieben',
            paragraphs:  ['zu einem deiner Ziele gibt es eine Rückmeldung:', '„' . $comment . '"'],
            facts: array_filter([
                'Ziel'     => $goal->title,
                'Zielzeit' => $goal->formatted_target_time ?? null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.goals.index'),
            actionLabel: 'Ziel ansehen',
            greetingName: $user->firstname,
        ));
    }

    /** Trainer hat ein persoenliches Ziel bewertet: Info an den Sportler. */
    public function goalEvaluated(SwimmerGoal $goal): int
    {
        $swimmer = $goal->user;
        if (!$swimmer) return 0;

        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user) => new NotificationMail(
            subjectText: 'Dein Ziel wurde bewertet',
            heading:     'Rückmeldung zu deinem Ziel',
            paragraphs:  ['ein Trainer hat eines deiner Ziele bewertet.'],
            facts: array_filter([
                'Ziel'    => $goal->title ?? $goal->discipline_label ?? null,
                'Stand'   => $goal->status_label ?? null,
                'Zeit'    => $goal->formatted_achieved_time ?? null,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.goals.index'),
            actionLabel: 'Ziel ansehen',
            greetingName: $user->firstname,
        ));
    }

    /** Leistungskriterium bewertet: Info an den Sportler. */
    public function criterionEvaluated(TrainingGroupGoal $criterion, User $swimmer, bool $achieved, ?string $note = null): int
    {
        return $this->toSwimmers(collect([$swimmer]), 'own_goals', fn(User $user) => new NotificationMail(
            subjectText: 'Leistungskriterium bewertet',
            heading:     'Bewertung eines Leistungskriteriums',
            paragraphs:  ['ein Trainer hat ein Leistungskriterium für dich bewertet.'],
            facts: array_filter([
                'Kriterium' => $criterion->title,
                'Bewertung' => $achieved ? 'erreicht' : 'noch nicht erreicht',
                'Notiz'     => $note,
            ]),
            actionUrl:   $this->linkFor($user, 'swimmer.group-goals.index'),
            actionLabel: 'Leistungskriterien ansehen',
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
            $sent += $this->toSwimmers(collect([$swimmer]), 'own_records', fn(User $user) => new NotificationMail(
                subjectText: "Vereinsrekord: {$strecke}",
                heading:     'Neuer Vereinsrekord',
                paragraphs:  ['du hast einen neuen Vereinsrekord aufgestellt. Glückwunsch!'],
                facts:       $facts,
                actionUrl:   $this->linkFor($user, 'swimmer.times'),
                actionLabel: 'Rekorde ansehen',
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

        foreach ($this->withParents($users) as $recipient) {
            $mail = $build($recipient);
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

    /** Sportler samt Eltern, ohne Doppelte. */
    private function withParents(Collection $users): Collection
    {
        $all = collect();

        foreach ($users->filter() as $user) {
            $all->push($user);
            try {
                foreach ($user->parents()->where('active', true)->get() as $parent) {
                    $all->push($parent);
                }
            } catch (\Throwable) {
                // Beziehung nicht vorhanden - dann eben nur der Sportler
            }
        }

        return $all->filter()->unique('id')->values();
    }
}
