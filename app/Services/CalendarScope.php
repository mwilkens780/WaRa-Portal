<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventInvitee;
use App\Models\Competition;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingSession;
use App\Models\TrainingSessionRegistration;
use App\Models\TrainingSessionSwimmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Wer sieht was im Kalender – eine Regel für Kalenderansicht, Abo (ICS-Feed)
 * und Einzel-Export (Martin, 08.10.2026):
 *
 *  - Trainingseinheiten: nur zugewiesene. Sportler über Gruppe (ohne dauerhaft
 *    abgemeldete Serien), Einzel- oder Serienzuweisung, Anmeldung oder
 *    Anwesenheit; Eltern die ihrer Kinder; Trainer die, bei denen sie
 *    eingetragen sind oder deren Gruppe sie betreuen.
 *  - Wettkämpfe: der eigenen Gruppen bzw. mit Einladung, Ergebnis oder
 *    Kampfrichter-Anfrage.
 *  - Termine ohne Einladung (Vereinstermin, Ehrung, Meldefrist, Sonstiges):
 *    für alle oder nur für die gewählten Gruppen.
 *  - Termine mit Einladung: Eingeladene (auch über minderjährige Kinder) und
 *    wer sie verwaltet (Vorstandssitzung: der Vorstand).
 *  - Matrix-Recht "calendar_all" (Vorstand, Admin): alles über alle Gruppen.
 */
class CalendarScope
{
    /** @var array<int, array> */
    private array $context = [];

    public function seesAll(User $user): bool
    {
        return $user->canAccess('calendar_all');
    }

    // ── Trainingseinheiten ───────────────────────────────────────────────────

    public function sessions(User $user): Builder
    {
        $q = TrainingSession::query();
        if ($this->seesAll($user)) return $q;

        $ctx = $this->context($user);

        return $q->where(function (Builder $w) use ($user, $ctx) {
            // Als Trainer eingetragen oder Trainer einer zugeordneten Gruppe
            $w->whereHas('coTrainers', fn($c) => $c->where('users.id', $user->id));
            if ($ctx['trainer_groups']) {
                $w->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $ctx['trainer_groups']));
            }
            // Als Sportler zugewiesen: man selbst und (Eltern) die Kinder
            foreach ($ctx['athletes'] as $a) {
                $w->orWhere(fn($s) => $this->assignedToAthlete($s, $a));
            }
        });
    }

    /** Einheiten, denen dieser Sportler zugewiesen ist (ohne "für alle offen") */
    private function assignedToAthlete(Builder $q, array $a): void
    {
        $q->where(function (Builder $w) use ($a) {
            $w->whereRaw('0=1');
            if ($a['groups']) {
                $w->orWhere(fn($g) => $g
                    ->whereHas('trainingGroups', fn($t) => $t->whereIn('training_groups.id', $a['groups']))
                    ->where(fn($r) => $r->whereNull('recurrence_group_id')->orWhereNotIn('recurrence_group_id', $a['excluded_series'] ?: ['']))
                );
            }
            if ($a['sessions'])  $w->orWhereIn('id', $a['sessions']);
            if ($a['series'])    $w->orWhereIn('recurrence_group_id', $a['series']);
            $w->orWhereHas('attendances', fn($t) => $t->where('user_id', $a['id'])->where('attended', true));
        });
    }

    // ── Wettkämpfe ───────────────────────────────────────────────────────────

    public function competitions(User $user): Builder
    {
        $q = Competition::query();
        if ($this->seesAll($user)) return $q;

        $ctx    = $this->context($user);
        $groups = array_values(array_unique(array_merge($ctx['trainer_groups'], ...array_map(fn($a) => $a['groups'], $ctx['athletes']))));
        $people = array_column($ctx['athletes'], 'id');

        return $q->where(function (Builder $w) use ($user, $groups, $people) {
            $w->whereHas('officialRequest.invitees', fn($i) => $i->where('user_id', $user->id));
            if ($groups) $w->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $groups));
            if ($people) {
                $w->orWhereHas('results', fn($r) => $r->whereIn('user_id', $people))
                  ->orWhereHas('signupRequest.responses', fn($s) => $s->whereIn('user_id', $people));
            }
        });
    }

    // ── Termine ──────────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, CalendarEvent> $events
     * @return Collection<int, CalendarEvent>
     */
    public function visibleEvents(User $user, Collection $events): Collection
    {
        if ($events->isEmpty()) return $events;
        $events = new \Illuminate\Database\Eloquent\Collection($events->all());
        $events->loadMissing('trainingGroups:id');

        $all     = $this->seesAll($user);
        $ctx     = $this->context($user);
        $myGroups = array_flip(array_merge($ctx['trainer_groups'], ...array_map(fn($a) => $a['groups'], $ctx['athletes'])));
        $invited = CalendarEventInvitee::whereIn('calendar_event_id', $events->pluck('id'))
            ->whereIn('user_id', $ctx['invitee_ids'])
            ->pluck('calendar_event_id')->flip();

        return $events->filter(function (CalendarEvent $e) use ($user, $all, $myGroups, $invited) {
            if (isset($invited[$e->id]) || $e->created_by === $user->id) return true;
            if ($e->hasInvitations()) return $all || $e->canManage($user);
            if ($all || $e->trainingGroups->isEmpty()) return true;
            return $e->trainingGroups->contains(fn($g) => isset($myGroups[$g->id]));
        })->values();
    }

    public function eventVisible(User $user, CalendarEvent $event): bool
    {
        return $this->visibleEvents($user, collect([$event]))->isNotEmpty();
    }

    // ── Kontext je Benutzer ──────────────────────────────────────────────────

    /**
     * Gruppen und Zuweisungen des Benutzers und (Eltern) seiner Kinder.
     *
     * @return array{trainer_groups: list<int>, athletes: list<array>, invitee_ids: list<int>, children: Collection}
     */
    public function context(User $user): array
    {
        if (isset($this->context[$user->id])) return $this->context[$user->id];

        $children = $user->children()->where('active', true)->orderBy('firstname')->get();
        $athletes = [];
        foreach ($children->prepend($user) as $a) {
            $groups = $a->trainingGroups()->pluck('training_groups.id')->all();
            $sessions = TrainingSessionSwimmer::where('user_id', $a->id)->whereNotNull('training_session_id')->pluck('training_session_id')
                ->merge(TrainingSessionRegistration::where('user_id', $a->id)->pluck('training_session_id'))->unique()->values()->all();
            $series = TrainingSessionSwimmer::where('user_id', $a->id)->whereNull('training_session_id')->whereNotNull('recurrence_group_id')
                ->pluck('recurrence_group_id')->unique()->values()->all();
            $athletes[] = [
                'id'              => $a->id,
                'name'            => $a->firstname,
                'groups'          => $groups,
                'sessions'        => $sessions,
                'series'          => $series,
                'excluded_series' => SwimmerSeriesExclusion::where('user_id', $a->id)->pluck('recurrence_group_id')->all(),
            ];
        }

        return $this->context[$user->id] = [
            'trainer_groups' => $user->trainerGroups()->pluck('training_groups.id')->all(),
            'athletes'       => $athletes,
            // Einladungen: eigene und die minderjähriger Kinder
            'invitee_ids'    => array_merge([$user->id], $user->wards()->pluck('id')->all()),
            'children'       => $children->reject(fn($c) => $c->id === $user->id)->values(),
        ];
    }

    /** Eltern: Vornamen der Kinder, zu denen eine Einheit gehört (für den Titel) */
    public function childrenOf(User $user, TrainingSession $session): array
    {
        $ctx = $this->context($user);
        if (count($ctx['athletes']) < 2) return [];

        $session->loadMissing('trainingGroups:id');
        $groupIds = $session->trainingGroups->pluck('id')->all();
        $names = [];
        foreach (array_slice($ctx['athletes'], 1) as $a) {
            if (array_intersect($groupIds, $a['groups']) || in_array($session->id, $a['sessions'], true)
                || ($session->recurrence_group_id && in_array($session->recurrence_group_id, $a['series'], true))) {
                $names[] = $a['name'];
            }
        }
        return $names;
    }
}
