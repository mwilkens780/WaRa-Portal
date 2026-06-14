<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Services\Dsv7Parser;

class DefinitionsdateiGenerator
{
    /**
     * Generate a DSV7 *-Wk.DSV7 Wettkampfdefinitionsdatei from competition_events.
     *
     * Returns the file content as a string (UTF-8, CRLF line endings).
     */
    public function generate(Competition $competition): string
    {
        $competition->load(['events' => fn($q) => $q->orderBy('session_number')->orderBy('event_number')]);

        $lines   = [];
        $lines[] = 'FORMAT:Wettkampfdefinitionsliste;7';
        $lines[] = 'ERZEUGER:WaRa-Portal;1.0;portal@wasserratten.de';
        $lines[] = 'VERANSTALTUNG:' . implode(';', [
            $competition->name,
            $competition->location ?? '',
            $competition->course === 'Langbahn' ? '50' : '25',
            'AUTOMATISCH',
            $competition->date->format('d.m.Y'),
            ($competition->date_end ?? $competition->date)->format('d.m.Y'),
        ]);

        if ($competition->organizer) {
            $lines[] = "VERANSTALTER:{$competition->organizer}";
        }

        // Kampfgericht contacts from JSON field
        foreach ($competition->kampfgericht['contacts'] ?? [] as $official) {
            $role  = $official['role'] ?? '';
            $name  = $official['name'] ?? '';
            $club  = $official['email'] ?? '';
            $lines[] = "KAMPFGERICHT:{$role};{$name};{$club}";
        }

        // ABSCHNITT per session
        $sessions = $competition->events->groupBy('session_number');
        foreach ($sessions as $sessionNum => $sessionEvents) {
            $first   = $sessionEvents->first();
            $dateStr = $first->session_date?->format('d.m.Y') ?? '';
            $name    = $first->session_name ?? ('Abschnitt ' . $sessionNum);
            $lines[] = "ABSCHNITT:{$sessionNum};{$dateStr};{$name}";
        }

        // WETTKAMPF + WERTUNG + PFLICHTZEIT + MELDEGELD
        $wertungsIdCounter = 1;

        foreach ($competition->events->groupBy('event_number') as $eventNum => $wertungen) {
            $base      = $wertungen->first();
            $isRelay   = str_contains(strtolower($base->age_group ?? ''), 'staffel');
            $legs      = $isRelay ? 4 : 1;
            $legDist   = $isRelay ? intdiv($base->distance, $legs) : $base->distance;
            $stroke    = strtoupper(array_flip(Dsv7Parser::STROKE_MAP)[$base->discipline] ?? 'F');

            $lines[] = implode(';', [
                "WETTKAMPF:{$eventNum}",
                'E',
                $base->session_number,
                $legs,
                $legDist,
                $stroke,
                'E',
                $this->genderDs7($base->gender),
            ]);

            foreach ($wertungen as $wertung) {
                $ageMin = $wertung->age_min ?? 0;
                $ageMax = $wertung->age_max ?? 9999;
                $label  = $wertung->age_group ?: 'Offene Klasse';

                $lines[] = implode(';', [
                    "WERTUNG:{$eventNum}",
                    'E',
                    $wertungsIdCounter,
                    'E',
                    $ageMin,
                    $ageMax,
                    $this->genderDs7($wertung->gender),
                    $label,
                ]);

                if ($wertung->qualifying_time_ms > 0) {
                    $zeit    = MeldedateiGenerator::msToDs7($wertung->qualifying_time_ms);
                    $lines[] = "PFLICHTZEIT:{$eventNum};E;{$wertungsIdCounter};;{$zeit}";
                }

                if ($wertung->meldegeld > 0) {
                    $betrag  = number_format((float) $wertung->meldegeld, 2, ',', '');
                    $lines[] = "MELDEGELD:{$eventNum};E;{$wertungsIdCounter};{$betrag};EUR";
                }

                $wertungsIdCounter++;
            }
        }

        $lines[] = 'DATEIENDE';

        return implode("\r\n", $lines);
    }

    private function genderDs7(string $g): string
    {
        return ($g === 'F') ? 'W' : strtoupper($g);
    }
}
