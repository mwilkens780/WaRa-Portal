<?php

namespace App\Services;

use App\Models\HallBooking;
use App\Models\TrainingSession;
use Illuminate\Support\Collection;

/**
 * Nur lesende Pruefung: Trainingsserien und Hallenbelegungen.
 *
 * Hintergrund (30.09.2026): Serien sind keine eigenen Datensaetze, sondern eine
 * gemeinsame recurrence_group_id auf vielen Einheiten; Hallenbelegungen haengen
 * an EINER Einheit (training_session_id) oder an gar keiner (Excel-Import).
 * "Serie bearbeiten" hat je zukuenftiger Einheit eine eigene wochentliche
 * Belegung angelegt. Diese Pruefung zeigt, welche Daten davon betroffen sind -
 * sie aendert nichts.
 *
 * Aufruf: php artisan training:audit   bzw. Admin -> Daten -> Datenprüfung Training
 */
class TrainingDataAudit
{
    /** @return array<string, array{title: string, explain: string, rows: array<int, array<string, mixed>>}> */
    public function run(): array
    {
        $bookings = HallBooking::with(['resource:id,name', 'trainingSession:id,title,date,start_time,end_time,recurrence_group_id', 'trainingGroup:id,name'])
            ->orderBy('day_of_week')->orderBy('start_time')->get();

        $sessions = TrainingSession::with(['trainingGroups:id,name', 'coTrainers:id,firstname,lastname'])
            ->whereNotNull('recurrence_group_id')
            ->orderBy('date')
            ->get(['id', 'title', 'date', 'start_time', 'end_time', 'location', 'type', 'recurrence_group_id']);
        $series = $sessions->groupBy('recurrence_group_id');

        return [
            'doppelt'        => $this->identicalBookings($bookings),
            'je_einheit'     => $this->perSessionBookings($bookings, $series),
            'zeit_abweichend' => $this->bookingTimeMismatch($bookings),
            'an_vergangener' => $this->bookingOnPastSession($bookings, $series),
            'uneinheitlich'  => $this->inconsistentSeries($series),
            'wochentage'     => $this->mixedWeekdays($series),
            'doppelte_termine' => $this->duplicateDates($series),
            'unverknuepft'   => $this->unlinkedCandidates($bookings, $series),
        ];
    }

    /** Gleiche Bahn, gleicher Tag, gleiche Zeit - mehrfach vorhanden */
    private function identicalBookings(Collection $bookings): array
    {
        $rows = $bookings
            ->groupBy(fn($b) => implode('|', [$b->hall_resource_id, $b->day_of_week, substr($b->start_time, 0, 5), substr($b->end_time, 0, 5)]))
            ->filter(fn($g) => $g->count() > 1)
            ->map(fn($g) => [
                'bahn'        => $g->first()->resource?->name,
                'tag'         => HallBooking::DAY_NAMES[$g->first()->day_of_week] ?? $g->first()->day_of_week,
                'zeit'        => substr($g->first()->start_time, 0, 5) . '–' . substr($g->first()->end_time, 0, 5),
                'anzahl'      => $g->count(),
                'bezeichnung' => $g->pluck('label')->unique()->implode(' / '),
                'serien'      => $g->pluck('trainingSession.recurrence_group_id')->filter()->unique()->count(),
                'ohne_einheit' => $g->whereNull('training_session_id')->count(),
                'ids'         => $g->pluck('id')->implode(', '),
            ])->values()->all();

        return [
            'title'   => 'Identische Belegungen (gleiche Bahn, Tag, Zeit)',
            'explain' => 'Im Hallenplan liegen sie übereinander und teilen sich die Spaltenbreite – bei vielen werden sie unsichtbar. Meist Folge von „Serie bearbeiten → Bahnen“.',
            'rows'    => $rows,
        ];
    }

    /** Eine Serie hat pro Bahn mehrere Belegungen (je Einheit eine) */
    private function perSessionBookings(Collection $bookings, Collection $series): array
    {
        $rows = $bookings->whereNotNull('training_session_id')
            ->filter(fn($b) => $b->trainingSession?->recurrence_group_id)
            ->groupBy(fn($b) => $b->trainingSession->recurrence_group_id . '|' . $b->hall_resource_id)
            ->filter(fn($g) => $g->count() > 1)
            ->map(function ($g) use ($series) {
                $group = $g->first()->trainingSession->recurrence_group_id;
                return [
                    'serie'      => $series[$group]?->first()?->title ?? $g->first()->label,
                    'bahn'       => $g->first()->resource?->name,
                    'tag_zeit'   => (HallBooking::DAY_NAMES[$g->first()->day_of_week] ?? '') . ' ' . substr($g->first()->start_time, 0, 5),
                    'belegungen' => $g->count(),
                    'sollte'     => 1,
                    'serie_id'   => $group,
                ];
            })->values()->all();

        return [
            'title'   => 'Serien mit einer Belegung je Einheit statt einer für die Serie',
            'explain' => 'Richtig wäre eine wöchentliche Belegung je Bahn für die ganze Serie.',
            'rows'    => $rows,
        ];
    }

    /** Belegung passt nicht (mehr) zu Wochentag/Uhrzeit ihrer Einheit */
    private function bookingTimeMismatch(Collection $bookings): array
    {
        $rows = $bookings->filter(function ($b) {
            $s = $b->trainingSession;
            if (!$s || !$s->date) return false;
            return $s->date->dayOfWeekIso !== (int) $b->day_of_week
                || substr($s->start_time ?? '', 0, 5) !== substr($b->start_time, 0, 5)
                || substr($s->end_time ?? '', 0, 5) !== substr($b->end_time ?? '', 0, 5);
        })->map(fn($b) => [
            'belegung' => $b->label . ' (#' . $b->id . ')',
            'bahn'     => $b->resource?->name,
            'belegung_zeit' => (HallBooking::DAY_NAMES[$b->day_of_week] ?? '') . ' ' . substr($b->start_time, 0, 5) . '–' . substr($b->end_time, 0, 5),
            'einheit_zeit'  => $b->trainingSession->date->isoFormat('dd DD.MM.YYYY') . ' ' . substr($b->trainingSession->start_time, 0, 5) . '–' . substr($b->trainingSession->end_time ?? '', 0, 5),
        ])->values()->all();

        return [
            'title'   => 'Belegung und verknüpfte Einheit passen zeitlich nicht zusammen',
            'explain' => 'Eine Seite wurde geändert, die andere nicht.',
            'rows'    => $rows,
        ];
    }

    /** Wochentliche Belegung haengt an einer vergangenen Einheit einer laufenden Serie */
    private function bookingOnPastSession(Collection $bookings, Collection $series): array
    {
        $rows = $bookings->filter(function ($b) use ($series) {
            $s = $b->trainingSession;
            if (!$s || !$s->recurrence_group_id || $s->date->gte(today())) return false;
            return ($series[$s->recurrence_group_id] ?? collect())->contains(fn($x) => $x->date->gte(today()));
        })->map(fn($b) => [
            'serie'   => $b->trainingSession->title,
            'bahn'    => $b->resource?->name,
            'haengt_an' => $b->trainingSession->date->format('d.m.Y'),
            'belegung' => '#' . $b->id,
        ])->values()->all();

        return [
            'title'   => 'Serienbelegung hängt an einer vergangenen Einheit',
            'explain' => 'Wird diese Einheit gelöscht oder ihre Uhrzeit geändert, verschwindet die Belegung der ganzen Serie aus dem Hallenplan.',
            'rows'    => $rows,
        ];
    }

    /** Zukuenftige Einheiten einer Serie unterscheiden sich in Stammdaten */
    private function inconsistentSeries(Collection $series): array
    {
        $rows = [];
        foreach ($series as $id => $all) {
            $future = $all->filter(fn($s) => $s->date->gte(today()));
            if ($future->count() < 2) continue;
            $diff = [];
            foreach (['title' => 'Titel', 'start_time' => 'Beginn', 'end_time' => 'Ende', 'location' => 'Ort', 'type' => 'Art'] as $field => $label) {
                $values = $future->pluck($field)->map(fn($v) => is_string($v) ? substr($v, 0, $field === 'start_time' || $field === 'end_time' ? 5 : 255) : $v)->unique();
                if ($values->count() > 1) $diff[] = $label . ' (' . $values->implode(' / ') . ')';
            }
            $groups = $future->map(fn($s) => $s->trainingGroups->pluck('id')->sort()->implode(','))->unique();
            if ($groups->count() > 1) $diff[] = 'Gruppen (' . $groups->count() . ' Varianten)';
            $trainers = $future->map(fn($s) => $s->coTrainers->pluck('id')->sort()->implode(','))->unique();
            if ($trainers->count() > 1) $diff[] = 'Trainer (' . $trainers->count() . ' Varianten)';
            if ($diff) {
                $rows[] = ['serie' => $all->first()->title, 'kommende_einheiten' => $future->count(), 'abweichungen' => implode('; ', $diff), 'serie_id' => $id];
            }
        }

        return [
            'title'   => 'Serien, deren kommende Einheiten sich unterscheiden',
            'explain' => 'Teils gewollte Einzeländerungen, teils Folge der zwei Bearbeitungswege. Beim Umbau muss für jede Serie feststehen, was die „richtigen“ Serienwerte sind.',
            'rows'    => $rows,
        ];
    }

    /** Einheiten einer Serie an verschiedenen Wochentagen */
    private function mixedWeekdays(Collection $series): array
    {
        $rows = [];
        foreach ($series as $id => $all) {
            $byDay = $all->groupBy(fn($s) => $s->date->dayOfWeekIso);
            if ($byDay->count() > 1) {
                // Je Wochentag: Anzahl und Zeitraum - zeigt, ob einzelne Termine verlegt oder die Serie umgeschrieben wurde
                $verteilung = $byDay->sortByDesc(fn($g) => $g->count())->map(fn($g, $d) => (HallBooking::DAY_NAMES[$d] ?? $d) . ': ' . $g->count()
                    . ($g->count() === 1 ? ' (' . $g->first()->date->format('d.m.Y') . ')' : ' (' . $g->first()->date->format('d.m.') . '–' . $g->last()->date->format('d.m.Y') . ')'))
                    ->implode('; ');
                $rows[] = [
                    'serie'      => $all->first()->title,
                    'verteilung' => $verteilung,
                    'einheiten'  => $all->count(),
                    'serie_id'   => $id,
                ];
            }
        }

        return [
            'title'   => 'Serien mit Einheiten an verschiedenen Wochentagen',
            'explain' => 'Ein einzelner Termin an einem anderen Tag ist meist eine gewollte Verlegung. Viele Termine an einem zweiten Tag deuten auf „Einheit bearbeiten → ganze Serie“ mit geändertem Wochentag.',
            'rows'    => $rows,
        ];
    }

    /** Zwei Einheiten derselben Serie am selben Tag */
    private function duplicateDates(Collection $series): array
    {
        $rows = [];
        foreach ($series as $id => $all) {
            foreach ($all->groupBy(fn($s) => $s->date->format('Y-m-d'))->filter(fn($g) => $g->count() > 1) as $date => $g) {
                $rows[] = ['serie' => $all->first()->title, 'datum' => \Carbon\Carbon::parse($date)->format('d.m.Y'), 'anzahl' => $g->count(), 'einheiten' => $g->pluck('id')->implode(', ')];
            }
        }

        return [
            'title'   => 'Serien mit zwei Einheiten am selben Tag',
            'explain' => 'Z. B. durch doppelte Saisonplanung.',
            'rows'    => $rows,
        ];
    }

    /** Laufende Serien ohne verknuepfte Belegung, zu denen eine passende unverknuepfte Belegung existiert */
    private function unlinkedCandidates(Collection $bookings, Collection $series): array
    {
        $linkedSeries = $bookings->pluck('trainingSession.recurrence_group_id')->filter()->unique();
        $free = $bookings->whereNull('training_session_id');
        $rows = [];
        foreach ($series as $id => $all) {
            if ($linkedSeries->contains($id)) continue;
            $next = $all->first(fn($s) => $s->date->gte(today()));
            if (!$next) continue;
            $match = $free->filter(fn($b) => (int) $b->day_of_week === $next->date->dayOfWeekIso
                && substr($b->start_time, 0, 5) === substr($next->start_time, 0, 5)
                && substr($b->end_time ?? '', 0, 5) === substr($next->end_time ?? '', 0, 5));
            if ($match->isNotEmpty()) {
                // Gleiche Zeit allein reicht nicht: parallel trainieren oft mehrere Gruppen.
                // Passend ist eine Belegung erst, wenn Gruppe oder Bezeichnung zur Serie gehoert.
                $gruppen = $next->trainingGroups;
                $namen = $gruppen->pluck('name')->push($next->title)->map(fn($n) => mb_strtolower(trim($n)))->filter();
                [$passt, $fremd] = $match->partition(fn($b) => ($b->training_group_id && $gruppen->contains('id', $b->training_group_id))
                    || $namen->contains(mb_strtolower(trim($b->trainingGroup?->name ?? $b->label ?? ''))));
                $liste = fn($c) => $c->map(fn($b) => ($b->resource?->name) . ': ' . ($b->trainingGroup?->name ?? $b->label) . ' (#' . $b->id . ')')->implode('; ') ?: '–';
                $rows[] = [
                    'serie'     => $next->title,
                    'gruppen'   => $gruppen->pluck('name')->implode(', ') ?: '–',
                    'tag_zeit'  => (HallBooking::DAY_NAMES[$next->date->dayOfWeekIso] ?? '') . ' ' . substr($next->start_time, 0, 5) . '–' . substr($next->end_time ?? '', 0, 5),
                    'verknuepfbar' => $liste($passt),
                    'nur_gleiche_zeit' => $liste($fremd),
                    'serie_id'  => $id,
                ];
            }
        }

        return [
            'title'   => 'Serien ohne Verknüpfung, zu denen eine passende Belegung existiert',
            'explain' => 'Typisch für den Excel-Import: Belegung und Serie wurden angelegt, aber nicht verbunden. „Verknüpfbar“ = gleiche Zeit UND passende Gruppe/Bezeichnung. „Nur gleiche Zeit“ sind meist andere Gruppen, die parallel trainieren – die werden nicht verknüpft.',
            'rows'    => $rows,
        ];
    }
}
