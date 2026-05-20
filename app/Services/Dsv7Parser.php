<?php

namespace App\Services;

/**
 * Parser for EasyWk DSV7 semicolon-delimited result files.
 *
 * File structure (sequential, context-sensitive):
 *   VERANSTALTUNG  → meet metadata
 *   VERANSTALTER   → organizer
 *   ABSCHNITT      → session dates (DD.MM.YYYY)
 *   WETTKAMPF      → event definition (discipline, distance, gender)
 *   WERTUNG        → age-group subdivision of the current WETTKAMPF → gets unique wertung_id
 *   PNERGEBNIS / ERGEBNIS → result row, references wertung_id
 */
class Dsv7Parser
{
    const STROKE_MAP = [
        'F' => 'freistil',
        'B' => 'brust',
        'R' => 'ruecken',
        'S' => 'schmetterling',
        'L' => 'lagen',
    ];

    /**
     * Parse a DSV7 file and return the same array structure as DsvImportService::parse().
     * Returns clubs → athletes → results.
     */
    public function parseResults(string $filePath): array
    {
        [$meta, $sessions, $wertungMap, $clubs] = $this->parseFile($filePath);

        $startdate = $this->firstDate($sessions);
        $enddate   = $this->lastDate($sessions);

        $clubsArray = [];
        foreach ($clubs as $clubData) {
            $athletes = array_values($clubData['athletes']);
            if (!empty($athletes)) {
                $clubsArray[] = [
                    'name'      => $clubData['name'],
                    'shortname' => '',
                    'athletes'  => $athletes,
                ];
            }
        }

        return [
            'meets' => [[
                'name'      => $meta['name'] ?? '',
                'city'      => $meta['city'] ?? '',
                'course'    => $meta['course'] ?? 'SCM',
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'organizer' => $meta['organizer'] ?? '',
                'clubs'     => $clubsArray,
            ]],
        ];
    }

    /**
     * Parse a DSV7 file and return the same structure as DsvImportService::parseMeetDefinition().
     * Returns meet metadata + event list (for competition creation).
     */
    public function parseMeetDefinition(string $filePath): array
    {
        [$meta, $sessions, $wertungMap] = $this->parseFile($filePath);

        $startdate = $this->firstDate($sessions);
        $enddate   = $this->lastDate($sessions);

        // Build unique events from wertungMap (deduplicate by discipline+distance+gender+ageGroup)
        $events = [];
        foreach ($wertungMap as $wid => $w) {
            $events[] = [
                'event_number'   => $w['event_number'],
                'session_number' => $w['session_number'],
                'session_date'   => $sessions[$w['session_number']] ?? $startdate,
                'session_name'   => 'Abschnitt ' . $w['session_number'],
                'discipline'     => $w['discipline'],
                'distance'       => $w['distance'],
                'gender'         => $w['gender'],
                'age_min'        => $w['age_min'],
                'age_max'        => $w['age_max'],
                'age_group'      => $w['age_group'],
            ];
        }

        return [
            'meets' => [[
                'name'      => $meta['name'] ?? '',
                'city'      => $meta['city'] ?? '',
                'course'    => $meta['course'] ?? 'SCM',
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'organizer' => $meta['organizer'] ?? '',
                'events'    => $events,
            ]],
        ];
    }

    // ── Core file parser ────────────────────────────────────────────────────

    private function parseFile(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        }

        $meta             = [];
        $sessions         = [];   // session_number → 'YYYY-MM-DD'
        $wertungMap       = [];   // wertung_id → event context
        $clubs            = [];   // club_name → data
        $currentWettkampf = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip full Pascal comment lines
            if (str_starts_with($line, '(*')) continue;
            // Strip inline trailing comments
            $line = preg_replace('/\s*\(\*[^)]*\*\)\s*$/', '', $line);
            $line = trim($line);
            if ($line === '') continue;

            // Split keyword from fields: "KEYWORD: field1;field2;..."
            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;

            $keyword = trim(substr($line, 0, $colonPos));
            $rest    = trim(substr($line, $colonPos + 1));
            $fields  = explode(';', $rest);
            $f       = fn(int $i) => trim($fields[$i] ?? '');

            switch ($keyword) {
                case 'VERANSTALTUNG':
                    // Name;City;PoolLength;TimingType;
                    $meta['name']   = $f(0);
                    $meta['city']   = $f(1);
                    $meta['course'] = $f(2) === '50' ? 'LCM' : 'SCM';
                    break;

                case 'VERANSTALTER':
                    $meta['organizer'] = $f(0);
                    break;

                case 'ABSCHNITT':
                    // Number;DD.MM.YYYY;Time;RelativeFlag;
                    $num = (int)$f(0);
                    $dt  = $this->parseGermanDate($f(1));
                    if ($num && $dt) {
                        $sessions[$num] = $dt;
                    }
                    break;

                case 'WETTKAMPF':
                    // event_number;roundType;session;relay_legs;distance;stroke;GL;gender;SW;...
                    $eventNum   = (int)$f(0);
                    $sessionNum = (int)$f(2);
                    $relayLegs  = (int)$f(3);
                    $distance   = (int)$f(4);
                    $strokeCode = strtoupper($f(5));
                    $gender     = $this->normalizeGender($f(7));

                    if (!isset(self::STROKE_MAP[$strokeCode]) || $distance <= 0 || $relayLegs > 1) {
                        $currentWettkampf = null;
                        break;
                    }

                    $currentWettkampf = [
                        'event_number'   => $eventNum,
                        'session_number' => $sessionNum,
                        'discipline'     => self::STROKE_MAP[$strokeCode],
                        'distance'       => $distance,
                        'gender'         => $gender,
                    ];
                    break;

                case 'WERTUNG':
                    // ?;round;wertung_id;criteria_type;min;max;gender;description;
                    if (!$currentWettkampf) break;

                    $wertungId = (int)$f(2);
                    if (!$wertungId) break;

                    $wGender  = $this->normalizeGender($f(6));
                    $ageGroup = $f(7);
                    $ageMin   = is_numeric($f(4)) ? (int)$f(4) : null;
                    $ageMax   = is_numeric($f(5)) ? (int)$f(5) : null;

                    $wertungMap[$wertungId] = array_merge($currentWettkampf, [
                        'gender'    => $wGender !== 'X' ? $wGender : $currentWettkampf['gender'],
                        'age_group' => $ageGroup,
                        'age_min'   => $ageMin,
                        'age_max'   => $ageMax,
                    ]);
                    break;

                case 'PNERGEBNIS':
                case 'ERGEBNIS':
                    // placement;round;wertung_id;heat;lane;name;dsv_id;athlete_id;gender;birth_year;ageGroup;club_name;club_id;time;points;status;nation;...
                    $wertungId = (int)$f(2);
                    if (!isset($wertungMap[$wertungId])) break;

                    $wertung   = $wertungMap[$wertungId];
                    $rawName   = $f(5);
                    $timeStr   = $f(13);

                    if ($rawName === '' || $timeStr === '' || $timeStr === 'NT') break;

                    $timeMs = $this->parseTime($timeStr);
                    if ($timeMs <= 0) break;

                    $placement = ((int)$f(0)) ?: null;
                    $gender    = $this->normalizeGender($f(8));
                    $birthYear = $f(9);
                    $clubName  = $f(11);
                    $dsvId     = $f(6);

                    if (!isset($clubs[$clubName])) {
                        $clubs[$clubName] = ['name' => $clubName, 'athletes' => []];
                    }

                    // Key: unique per athlete within this club
                    $athleteKey = $rawName . '|' . $birthYear;

                    if (!isset($clubs[$clubName]['athletes'][$athleteKey])) {
                        $clubs[$clubName]['athletes'][$athleteKey] = [
                            'name'            => $this->parseName($rawName),
                            'firstname'       => $this->extractFirstname($rawName),
                            'lastname'        => $this->extractLastname($rawName),
                            'birthdate'       => $birthYear,
                            'gender'          => $gender,
                            'dsvid'           => $dsvId,
                            'results'         => [],
                            'matched_user_id' => null,
                        ];
                    }

                    $clubs[$clubName]['athletes'][$athleteKey]['results'][] = [
                        'eventid'    => (string)$wertungId,
                        'discipline' => $wertung['discipline'],
                        'distance'   => $wertung['distance'],
                        'time_ms'    => $timeMs,
                        'swimtime'   => $this->formatTime($timeMs),
                        'place'      => $placement,
                    ];
                    break;
            }
        }

        return [$meta, $sessions, $wertungMap, $clubs];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function parseName(string $raw): string
    {
        // "Nachname, Vorname Zweiter" → "Vorname Zweiter Nachname"
        if (str_contains($raw, ',')) {
            [$last, $first] = explode(',', $raw, 2);
            return trim(trim($first) . ' ' . trim($last));
        }
        return trim($raw);
    }

    private function extractFirstname(string $raw): string
    {
        if (str_contains($raw, ',')) return trim(explode(',', $raw, 2)[1]);
        return $raw;
    }

    private function extractLastname(string $raw): string
    {
        if (str_contains($raw, ',')) return trim(explode(',', $raw, 2)[0]);
        return $raw;
    }

    private function parseGermanDate(string $date): ?string
    {
        // DD.MM.YYYY → YYYY-MM-DD
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', trim($date), $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
    }

    /**
     * Parse DSV7 time string: "HH:MM:SS,CC" or "MM:SS,CC" or "SS,CC"
     * Decimal separator is a comma, not a dot.
     */
    private function parseTime(string $time): int
    {
        $time  = trim(str_replace(',', '.', $time));
        $parts = explode(':', $time);

        return match(count($parts)) {
            3 => ((int)$parts[0] * 3_600_000)
                + ((int)$parts[1] * 60_000)
                + $this->secCentiToMs($parts[2]),
            2 => ((int)$parts[0] * 60_000) + $this->secCentiToMs($parts[1]),
            default => $this->secCentiToMs($parts[0]),
        };
    }

    private function secCentiToMs(string $secStr): int
    {
        [$sec, $centi] = array_pad(explode('.', $secStr, 2), 2, '0');
        return ((int)$sec * 1_000) + (int)str_pad(substr($centi, 0, 2), 2, '0') * 10;
    }

    private function formatTime(int $ms): string
    {
        $min   = intdiv($ms, 60_000);
        $sec   = intdiv($ms % 60_000, 1_000);
        $centi = intdiv($ms % 1_000, 10);
        return $min > 0
            ? sprintf('%d:%02d,%02d', $min, $sec, $centi)
            : sprintf('%d,%02d', $sec, $centi);
    }

    private function normalizeGender(string $g): string
    {
        return match(strtoupper($g)) {
            'W'     => 'F',
            'M'     => 'M',
            default => 'X',
        };
    }

    private function firstDate(array $sessions): string
    {
        $sorted = array_values($sessions);
        sort($sorted);
        return $sorted[0] ?? date('Y-m-d');
    }

    private function lastDate(array $sessions): string
    {
        $sorted = array_values($sessions);
        rsort($sorted);
        return $sorted[0] ?? date('Y-m-d');
    }
}
