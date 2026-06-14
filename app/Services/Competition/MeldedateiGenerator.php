<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionRelayEntry;
use App\Services\Dsv7Parser;

class MeldedateiGenerator
{
    /**
     * Generate a DSV7 *-Vm.DSV7 Vereinsmeldedatei from the competition_entries.
     *
     * Returns the file content as a string (UTF-8, CRLF line endings).
     */
    public function generate(Competition $competition): string
    {
        $entries = CompetitionEntry::with(['user', 'competitionEvent'])
            ->where('competition_id', $competition->id)
            ->where('status', 'entered')
            ->get()
            ->groupBy('user_id');

        $relayEntries = CompetitionRelayEntry::with(['members.user'])
            ->where('competition_id', $competition->id)
            ->where('status', 'entered')
            ->get();

        $lines   = [];
        $lines[] = 'FORMAT:Vereinsmeldung;7';
        $lines[] = 'ERZEUGER:WaRa-Portal;1.0;portal@wasserratten.de';
        $lines[] = 'VERANSTALTUNG:' . implode(';', [
            $competition->name,
            $competition->location ?? '',
            $competition->course === 'Langbahn' ? '50' : '25',
            'AUTOMATISCH',
            $competition->date->format('d.m.Y'),
            ($competition->date_end ?? $competition->date)->format('d.m.Y'),
        ]);
        $lines[] = 'VEREIN:SG Wasserratten Norderstedt;WARA-SH;SHSV;GER';

        foreach ($entries as $userId => $userEntries) {
            $user = $userEntries->first()->user;
            if (!$user) continue;

            $lines[] = 'ANMELDUNG:' . implode(';', [
                $user->lastname  ?? '',
                $user->firstname ?? '',
                $user->birth_date?->year ?? '',
                $this->genderDs7($user->gender ?? 'X'),
                $user->dsv_id ?? '',
                'SG Wasserratten Norderstedt',
                'SHSV',
                'GER',
            ]);

            foreach ($userEntries as $entry) {
                $zeit = $entry->entry_time_ms
                    ? self::msToDs7($entry->entry_time_ms)
                    : '99:99,99';

                // Prefer WertungsID from competition_events, fall back to event_number
                $wertungsId = $entry->competitionEvent?->dsv_wertungs_id
                              ?? $entry->competitionEvent?->event_number
                              ?? '';

                $lines[] = "MELDUNG:{$user->dsv_id};{$wertungsId};{$zeit}";
            }
        }

        foreach ($relayEntries as $relay) {
            $strokeCode = strtoupper(
                array_flip(Dsv7Parser::STROKE_MAP)[$relay->discipline] ?? 'F'
            );
            $gDs7 = $relay->gender === 'mixed' ? 'X' : $this->genderDs7($relay->gender);
            $wkNr = $relay->competitionEvent?->event_number ?? '';

            $lines[] = "STAFFELANMELDUNG:{$wkNr};{$strokeCode};{$gDs7};SG Wasserratten Norderstedt";

            foreach ($relay->members as $member) {
                if ($member->user) {
                    $lines[] = "STAFFELMELDUNG:{$member->user->dsv_id};{$member->position}";
                }
            }
        }

        $lines[] = 'DATEIENDE';

        return implode("\r\n", $lines);
    }

    public static function msToDs7(int $ms): string
    {
        $h  = intdiv($ms, 3_600_000);
        $m  = intdiv($ms % 3_600_000, 60_000);
        $s  = intdiv($ms % 60_000, 1_000);
        $hh = intdiv($ms % 1_000, 10);
        return $h > 0
            ? sprintf('%02d:%02d:%02d,%02d', $h, $m, $s, $hh)
            : sprintf('%02d:%02d,%02d', $m, $s, $hh);
    }

    private function genderDs7(string $g): string
    {
        return ($g === 'F') ? 'W' : strtoupper($g);
    }
}
