<?php

namespace App\Services\Import;

/**
 * Parser for WebClub competition result CSV exports.
 *
 * Expected file structure:
 *   Line 1:  <EventName>;
 *   Line 2:  <DD.MM.YYYY> - <DD.MM.YYYY>, <Kurzbahn|Langbahn>, <City>, <Venue>;
 *   Line 3:  Name;Jg;M/F;WkNr;Strecke;Typ;Abs.;Zeit;Platz;Rek;Bemerkung;
 *   Lines 4+: data rows
 *
 * Rek values:
 *   PBZ = Persönliche Bestzeit  → is_personal_best
 *   SBZ = Saisonbestzeit        → is_season_best
 *   SR  = Streckenrekord        → breaks_vereinsrekord
 *   VR  = Vereinsrekord         → breaks_vereinsrekord
 *   LR  = Landesrekord          → breaks_landesrekord
 */
class WebClubResultsCsvParser
{
    /**
     * Parse a WebClub CSV file.
     *
     * Returns data in the same structure as DsvImportService::parse() so the
     * existing preview and execute infrastructure can be reused.
     */
    public function parse(string $filePath): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252') ?: $raw;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);

        // ── Meta extraction ──────────────────────────────────────────────────
        $name      = trim(rtrim($lines[0] ?? '', ';'));
        $name      = preg_replace('/^Ergebnisse\s+/i', '', $name);
        $meta2     = trim(rtrim($lines[1] ?? '', ';'));
        $startdate = null;
        $enddate   = null;
        $city      = '';
        $course    = 'Kurzbahn';

        // "11.10.2025 - 12.10.2025, Kurzbahn, Kiel, ..."
        if (preg_match('/^(\d{1,2}\.\d{1,2}\.\d{4})(?:\s*-\s*(\d{1,2}\.\d{1,2}\.\d{4}))?/', $meta2, $dm)) {
            $startdate = $this->parseGermanDate($dm[1]);
            $enddate   = isset($dm[2]) ? $this->parseGermanDate($dm[2]) : $startdate;
        }
        $parts = array_map('trim', explode(',', $meta2));
        foreach ($parts as $p) {
            if (preg_match('/langbahn/iu', $p)) { $course = 'Langbahn'; break; }
            if (preg_match('/kurzbahn/iu', $p)) { $course = 'Kurzbahn'; break; }
        }
        // City = 3rd comma-separated part (after dates and course)
        if (count($parts) >= 3) {
            $cityRaw = $parts[2];
            // Remove leading date part if it got merged
            $city = preg_replace('/^\d[\d\s\.\-]*,\s*(?:Kurzbahn|Langbahn),\s*/i', '', $cityRaw);
            $city = trim($city) ?: $cityRaw;
        }

        // ── Skip header row (line index 2) ───────────────────────────────────
        $headerLineIndex = 2;
        for ($i = 2; $i < min(5, count($lines)); $i++) {
            if (str_contains($lines[$i], 'Name') && str_contains($lines[$i], 'Zeit')) {
                $headerLineIndex = $i;
                break;
            }
        }

        // ── Parse data rows ──────────────────────────────────────────────────
        // All athletes go into a single pseudo-club so the preview can group them.
        $athletes = [];

        for ($i = $headerLineIndex + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || $line === ';') continue;

            $fields = explode(';', $line);
            $f      = static fn(int $idx) => trim($fields[$idx] ?? '');

            $rawName    = $f(0);
            $jg         = $f(1);
            $rawGender  = strtoupper($f(2));
            $strecke    = $f(4);
            $typ        = strtoupper($f(5));    // V/F/E/Z
            $zeitStr    = $f(7);
            $platz      = (int)$f(8);
            $rekStr     = strtoupper(trim($f(9)));
            $bemerkung  = $f(10);

            if ($rawName === '' || $zeitStr === '' || $strecke === '') continue;

            $gender = match($rawGender) { 'W' => 'F', 'M' => 'M', default => 'X' };

            // ── Relay detection ──────────────────────────────────────────────
            if ($jg === '0' || preg_match('/^\d+\s*x\s*\d+/i', $strecke)) {
                [$relayLegs, $distance, $discipline] = $this->parseRelayStrecke($strecke);
                if (!$discipline || $distance <= 0) continue;

                $timeMs = $this->parseTime($zeitStr);
                if ($timeMs <= 0) continue;

                $members = $this->parseRelayMembers($bemerkung);
                $key     = '__relay_' . $strecke . '_' . $platz . '_' . $rawGender . '_' . $timeMs;

                $athletes[$key] = [
                    'name'            => $rawName,
                    'firstname'       => $rawName,
                    'lastname'        => '(Staffel)',
                    'birthdate'       => '',
                    'gender'          => $gender,
                    'dsvid'           => '',
                    'is_relay'        => true,
                    'relay_members'   => $members,
                    'matched_user_id' => null,
                    'results'         => [[
                        'discipline' => $discipline,
                        'distance'   => $distance * $relayLegs,
                        'time_ms'    => $timeMs,
                        'swimtime'   => $this->formatTime($timeMs),
                        'place'      => $platz ?: null,
                        'age_group'  => '',
                        'gender'     => $gender,
                        'round_type' => $this->normalizeTyp($typ),
                        'status'     => null,
                        'is_relay'   => true,
                        'rek'        => $rekStr,
                    ]],
                ];
                continue;
            }

            // ── Individual result ────────────────────────────────────────────
            [$distance, $discipline] = $this->parseStrecke($strecke);
            if (!$discipline || $distance <= 0) continue;

            $timeMs = $this->parseTime($zeitStr);
            if ($timeMs <= 0) continue;

            $firstname = $this->extractFirstname($rawName);
            $lastname  = $this->extractLastname($rawName);
            $key       = $rawName . '|' . $jg . '|' . $rawGender;

            if (!isset($athletes[$key])) {
                $athletes[$key] = [
                    'name'            => $rawName,
                    'firstname'       => $firstname,
                    'lastname'        => $lastname,
                    'birthdate'       => $jg,
                    'gender'          => $gender,
                    'dsvid'           => '',
                    'is_relay'        => false,
                    'relay_members'   => [],
                    'matched_user_id' => null,
                    'results'         => [],
                ];
            }

            $athletes[$key]['results'][] = [
                'discipline'       => $discipline,
                'distance'         => $distance,
                'time_ms'          => $timeMs,
                'swimtime'         => $this->formatTime($timeMs),
                'place'            => $platz ?: null,
                'age_group'        => '',
                'gender'           => $gender,
                'round_type'       => $this->normalizeTyp($typ),
                'status'           => null,
                'is_relay'         => false,
                'rek'              => $rekStr,
                'is_personal_best' => str_contains($rekStr, 'PBZ'),
                'is_season_best'   => str_contains($rekStr, 'SBZ'),
                'is_vereinsrekord' => str_contains($rekStr, 'SR') || str_contains($rekStr, 'VR'),
                'is_landesrekord'  => str_contains($rekStr, 'LR'),
            ];
        }

        return [
            'meets' => [[
                'name'      => $name,
                'city'      => $city,
                'course'    => $course,
                'startdate' => $startdate ?? date('Y-m-d'),
                'enddate'   => $enddate   ?? date('Y-m-d'),
                'organizer' => '',
                'clubs'     => [[
                    'name'      => '(Alle Vereine)',
                    'shortname' => '',
                    'athletes'  => array_values($athletes),
                ]],
            ]],
        ];
    }

    // ── Strecke parsing ──────────────────────────────────────────────────────

    /**
     * Parse individual "100 F", "50 BR" → [distance, disciplineCode].
     * Returns [null, null] for unknown formats.
     */
    private function parseStrecke(string $strecke): array
    {
        if (!preg_match('/^(\d+)\s+([A-ZÄÖÜ]+)$/iu', trim($strecke), $m)) {
            return [null, null];
        }

        $distance = (int)$m[1];
        $code     = strtoupper(trim($m[2]));

        $map = [
            'F'   => 'F', 'FR' => 'F', 'KB' => 'F',
            'B'   => 'B', 'BR' => 'B', 'BB' => 'B',
            'R'   => 'R', 'RU' => 'R', 'RB' => 'R',
            'S'   => 'S', 'SCH' => 'S', 'DB' => 'S',
            'L'   => 'L',
        ];

        return [$distance, $map[$code] ?? null];
    }

    /**
     * Parse relay "4x50 L" → [legs, distancePerLeg, disciplineCode].
     */
    private function parseRelayStrecke(string $strecke): array
    {
        if (!preg_match('/^(\d+)\s*x\s*(\d+)\s+([A-Z]+)/iu', trim($strecke), $m)) {
            return [1, 0, null];
        }

        $legs     = (int)$m[1];
        $distance = (int)$m[2];
        $code     = strtoupper($m[3]);

        $map = ['F' => 'F', 'B' => 'B', 'R' => 'R', 'S' => 'S', 'L' => 'L'];

        return [$legs, $distance, $map[$code] ?? null];
    }

    // ── Relay member parsing ─────────────────────────────────────────────────

    /**
     * Extract relay members from Bemerkung field.
     * Format: "(1) Firstname Lastname - YYYY(2) Firstname Lastname - YYYY..."
     */
    private function parseRelayMembers(string $bemerkung): array
    {
        $members = [];
        preg_match_all('/\((\d+)\)\s+(.+?)\s+-\s+(\d{4})(?=\(\d+\)|$)/u', $bemerkung, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $fullName = trim($m[2]);
            $members[] = [
                'leg'       => (int)$m[1],
                'name'      => $fullName,
                'firstname' => $this->extractFirstname($fullName),
                'lastname'  => $this->extractLastname($fullName),
                'birthyear' => $m[3],
                'splittime' => '',
            ];
        }

        return $members;
    }

    // ── Round type normalization ─────────────────────────────────────────────

    private function normalizeTyp(string $typ): string
    {
        return match($typ) {
            'V'  => 'V',   // Vorlauf
            'F'  => 'F',   // Finale
            'Z'  => 'Z',   // Zwischenlauf
            'E'  => 'E',   // Entscheidung
            default => '',
        };
    }

    // ── Name helpers ─────────────────────────────────────────────────────────

    private function extractFirstname(string $name): string
    {
        // Format "Firstname [Middle] Lastname" — last word = lastname
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) <= 1) return $name;
        array_pop($parts);
        return implode(' ', $parts);
    }

    private function extractLastname(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        return array_pop($parts) ?? $name;
    }

    // ── Date / time helpers ──────────────────────────────────────────────────

    private function parseGermanDate(string $date): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', trim($date), $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
    }

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
}
