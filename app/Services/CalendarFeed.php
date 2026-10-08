<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Competition;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Ics;
use Illuminate\Support\Carbon;

/**
 * Kalendereinträge eines Benutzers als iCalendar – für das Abo (webcal) und
 * den Einzel-Export ("In meinen Kalender"). Wer was sieht, regelt
 * CalendarScope; hier nur die Aufbereitung. Keine Trainingspläne, keine
 * Gesundheitsdaten: nur Zeit, Ort, Titel, Gruppen und ein Link ins Portal.
 */
class CalendarFeed
{
    /** Zeitraum des Abos: etwas Rückblick, gut ein Jahr Vorschau */
    public const PAST_DAYS   = 90;
    public const FUTURE_DAYS = 400;

    public function __construct(private CalendarScope $scope) {}

    public function forUser(User $user): string
    {
        $from = today()->subDays(self::PAST_DAYS);
        $to   = today()->addDays(self::FUTURE_DAYS);
        $items = [];

        $sessions = $this->scope->sessions($user)
            ->with(['trainingGroups:id,name', 'coTrainers:id,firstname,lastname'])
            ->whereBetween('date', [$from, $to])->orderBy('date')->get();
        foreach ($sessions as $s) $items[] = $this->session($s, $user);

        $competitions = $this->scope->competitions($user)
            ->where(fn($q) => $q->whereBetween('date', [$from, $to])->orWhereBetween('date_end', [$from, $to]))
            ->orderBy('date')->get();
        foreach ($competitions as $c) $items[] = $this->competition($c, $user);

        $events = CalendarEvent::where(fn($q) => $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]))
            ->orderBy('start_date')->get();
        foreach ($this->scope->visibleEvents($user, $events) as $e) $items[] = $this->event($e);

        return Ics::calendar($items, 'WaRa – ' . $user->firstname, feed: true);
    }

    /** Ein einzelner Eintrag als Datei ("In meinen Kalender", Mail-Anhang) */
    public function single(array $item): string
    {
        return Ics::calendar([$item], $item['summary']);
    }

    // ── Einträge ─────────────────────────────────────────────────────────────

    public function session(TrainingSession $s, ?User $viewer = null): array
    {
        $s->loadMissing(['trainingGroups:id,name', 'coTrainers:id,firstname,lastname']);
        $allDay = !$s->start_time;
        $start  = Ics::local($s->date, $s->start_time);
        $end    = $s->end_time ? Ics::local($s->date, $s->end_time) : $start->copy()->addMinutes(90);

        $title = $s->title ?: 'Training';
        // Eltern mit mehreren Kindern: für wen ist die Einheit?
        $kids = $viewer ? $this->scope->childrenOf($viewer, $s) : [];
        if ($kids) $title .= ' (' . implode(', ', $kids) . ')';
        // Abgesagte bleiben stehen, deutlich markiert (Martin, 08.10.2026)
        if ($s->isCancelled()) $title = 'ABGESAGT: ' . $title;

        return [
            'uid'         => "training-{$s->id}@" . $this->host(),
            'summary'     => $title,
            'start'       => $start,
            'end'         => $allDay ? $start : $end,
            'all_day'     => $allDay,
            'location'    => $s->location ?? null,
            'description' => implode("\n", array_filter([
                $s->trainingGroups->isNotEmpty() ? 'Gruppe: ' . $s->trainingGroups->pluck('name')->implode(', ') : null,
                $s->coTrainers->isNotEmpty() ? 'Trainer: ' . $s->coTrainers->map(fn($t) => $t->firstname . ' ' . $t->lastname)->implode(', ') : null,
                $s->isCancelled() ? 'Diese Einheit fällt aus.' . ($s->cancel_reason ? ' Grund: ' . $s->cancel_reason : '') : null,
            ])),
            'url'         => $this->sessionUrl($s, $viewer),
            'updated'     => $s->updated_at,
        ];
    }

    public function competition(Competition $c, ?User $viewer = null): array
    {
        return [
            'uid'         => "wettkampf-{$c->id}@" . $this->host(),
            'summary'     => 'Wettkampf: ' . $c->name,
            'start'       => Carbon::parse($c->date->format('Y-m-d'), Ics::TZ),
            'end'         => Carbon::parse(($c->date_end ?? $c->date)->format('Y-m-d'), Ics::TZ),
            'all_day'     => true,
            'location'    => $c->location,
            'description' => implode("\n", array_filter([
                $c->meldeschluss ? 'Meldeschluss: ' . $c->meldeschluss->format('d.m.Y') : null,
            ])),
            'url'         => $viewer && $viewer->canAccess('competitions')
                ? route('admin.competitions.show', $c)
                : route('calendar.index', ['view' => 'list']),
            'updated'     => $c->updated_at,
        ];
    }

    public function event(CalendarEvent $e): array
    {
        $allDay = !$e->start_time;
        $last   = $e->end_date ?? $e->start_date;
        $start  = Ics::local($e->start_date, $e->start_time);
        $end    = $allDay
            ? Carbon::parse($last->format('Y-m-d'), Ics::TZ)
            : ($e->end_time ? Ics::local($last, $e->end_time) : Ics::local($last, $e->start_time)->addHour());
        if (!$allDay && $end->lte($start)) $end = $start->copy()->addHour();

        return [
            'uid'         => "termin-{$e->id}@" . $this->host(),
            'summary'     => $e->title,
            'start'       => $start,
            'end'         => $end,
            'all_day'     => $allDay,
            'location'    => $e->location,
            'description' => implode("\n", array_filter([
                $e->type_label,
                $e->description ? trim(strip_tags($e->description)) : null,
                $e->hasInvitations() ? 'Agenda, Anhänge und Anmeldung im Portal.' : null,
            ])),
            'url'         => route('calendar.events.show', $e),
            'updated'     => $e->updated_at,
        ];
    }

    private function sessionUrl(TrainingSession $s, ?User $viewer): string
    {
        if ($viewer && $viewer->role === 'schwimmer') return route('swimmer.session.show', $s);
        if ($viewer && $viewer->canAccess('training', 'training_all') && $s->isManageableBy($viewer)) {
            return route('trainer.sessions.show', $s);
        }
        return route('calendar.index', ['view' => 'list']);
    }

    private function host(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?: 'wara-portal';
    }
}
