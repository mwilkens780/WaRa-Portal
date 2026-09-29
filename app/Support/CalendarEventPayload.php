<?php

namespace App\Support;

use App\Models\CalendarEvent;
use Carbon\Carbon;

/**
 * Daten eines Kalendertermins fuer das Detail-Sheet (calendar/_event-sheet).
 * Eine Stelle, damit Woche, Monat und Agenda dasselbe zeigen.
 */
final class CalendarEventPayload
{
    /** Bezeichnung der Kategorie zur Farbe: Einheiten/Wettkaempfe fest, Rest aus CalendarEvent::TYPES */
    public static function category(string $color): string
    {
        if ($color === 'blue') return 'Trainingseinheit';
        if ($color === 'red')  return 'Wettkampf';
        foreach (CalendarEvent::TYPES as $type) {
            if ($type['color'] === $color) return $type['label'];
        }
        return 'Termin';
    }

    /** @param array{title: string, sub?: ?string, time?: ?string, color: string, url?: ?string, id?: ?int} $evt */
    public static function make(array $evt, Carbon $day, bool $isTrainer): array
    {
        $time = $evt['time'] ?? null;

        return [
            'title'    => $evt['title'],
            'sub'      => $evt['sub'] ?? null,
            'time'     => $time,
            'date'     => $day->isoFormat('dddd, D. MMMM YYYY'),
            'color'    => $evt['color'],
            'category' => self::category($evt['color']),
            'url'      => $evt['url'] ?? null,
            'urlLabel' => $evt['url_label'] ?? 'Öffnen',
            // Eigene Kalendertermine: Bearbeiten fuer Trainer/Admin (vorher nur per Maus-Hover)
            'editUrl'  => $isTrainer && !empty($evt['id']) ? route('calendar.events.edit', $evt['id']) : null,
            'aria'     => $evt['title'] . ', ' . $day->isoFormat('dd D.M.') . ($time ? ', ' . $time : ''),
        ];
    }
}
