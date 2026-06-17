<?php

namespace App\Services;

/**
 * Parser for DSV Standard 7 semicolon-delimited result/definition files.
 *
 * Supports:
 *   - Official DSV Standard 7 (August 2022) — Vereinsergebnisliste + Wettkampfergebnisliste
 *   - EasyWK 3.x (new format, runde field) and EasyWK 2.x (old format)
 *   - PNERGEBNIS (extended) and ERGEBNIS (compact DSV) individual results
 *   - STAFFELERGEBNIS / PNSTAFFELERGEBNIS (Vereinsergebnisliste relay)
 *   - STERGEBNIS (Wettkampfergebnisliste relay)
 *   - STAFFELMITGLIED / STAFFELPERSON relay members
 *   - PFLICHTZEIT qualifying times and MELDEGELD entry fees
 *   - DNS / AB / DNF / DQ status entries (imported with time_ms=0 and notes)
 *   - Windows-1252 / ISO-8859-1 encoded files
 */
class Dsv7Parser
{
    const STROKE_MAP = [
        'F' => 'F',
        'B' => 'B',
        'R' => 'R',
        'S' => 'S',
        'L' => 'L',
    ];

    // Status codes that indicate the swimmer did not achieve a valid result
    const DNS_STATUSES = ['AB', 'DNS', 'DNF', 'DQ', 'DSQ', 'DISQ', 'NZ', 'WDR', 'EXH'];

    // Record types that carry no result/definition data — skip entirely
    private const SKIP_KEYWORDS = [
        'FORMAT', 'WETTKAMPFENDE', 'DATEIENDE', 'DATEI', 'ERZEUGER',
        'PNZWISCHENZEIT', 'ZWISCHENZEIT', 'ZWISCHENZEITEN', 'STZWISCHENZEIT',
        'PNREAKTION', 'STABLOESE',
        'MELDUNG', 'ANMELDUNG', 'NACHMELDUNG',
        'STAFFELSTART', 'STAFFELAUSSCHLUSS',
        'AUSSCHLUSS', 'DISQUALIFIKATION',
        'VERANSTALTUNGSORT', 'AUSRICHTER', 'KAMPFGERICHT',
        'ANSPRECHPARTNER', 'BANKVERBINDUNG', 'BESONDERES',
        'NACHWEIS', 'MELDESCHLUSS', 'MELDEADRESSE',
        'KARIMELDUNG', 'KARIABSCHNITT',
        'TRAINER', 'PNMELDUNG', 'STARTPN', 'STMELDUNG', 'STARTST',
    ];

    // ── Public API ──────────────────────────────────────────────────────────

    public function parseResults(string $filePath): array
    {
        [$meta, $sessions, , $clubs] = $this->parseFile($filePath);

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
                'course'    => $meta['course'] ?? 'Kurzbahn',
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'organizer' => $meta['organizer'] ?? '',
                'clubs'     => $clubsArray,
            ]],
        ];
    }

    public function parseMeetDefinition(string $filePath): array
    {
        [$meta, $sessions, $wertungMap] = $this->parseFile($filePath);

        $startdate = $this->firstDate($sessions);
        $enddate   = $this->lastDate($sessions);

        $events = [];
        foreach ($wertungMap as $wid => $w) {
            $events[] = [
                'event_number'       => $w['event_number'],
                'session_number'     => $w['session_number'],
                'session_date'       => $sessions[$w['session_number']] ?? $startdate,
                'session_name'       => 'Abschnitt ' . $w['session_number'],
                'discipline'         => $w['discipline'],
                'distance'           => $w['distance'],
                'gender'             => $w['gender'],
                'age_min'            => $w['age_min'] ?? null,
                'age_max'            => $w['age_max'] ?? null,
                'age_group'          => $w['age_group'] ?? '',
                'qualifying_time_ms'  => $w['qualifying_time_ms'] ?? null,
                'qualifying_deadline' => $w['qualifying_deadline'] ?? null,
                'meldegeld'           => $w['meldegeld'] ?? null,
            ];
        }

        return [
            'meets' => [[
                'name'      => $meta['name'] ?? '',
                'city'      => $meta['city'] ?? '',
                'course'    => $meta['course'] ?? 'Kurzbahn',
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'organizer' => $meta['organizer'] ?? '',
                'events'    => $events,
            ]],
        ];
    }

    // ── Core parser ─────────────────────────────────────────────────────────

    private function parseFile(string $filePath): array
    {
        $content = $this->readFile($filePath);
        $lines   = preg_split('/\r\n|\r|\n/', $content);

        $meta             = [];
        $sessions         = [];
        $wertungMap       = [];
        $clubs            = [];
        $wettkampfByNum   = []; // eventNum → wettkampf data, for WERTUNG lookup by wkNr
        $currentWettkampf = null;
        $pendingRelay     = null; // ['club' => string, 'key' => string]
        $currentClub      = null; // set by VEREIN record (Vereinsergebnisliste context)
        $useNewFormat     = null; // null=undetected; true=new (runde/art field); false=old

        // Post-parse lookups: PFLICHTZEIT / MELDEGELD may appear after all WERTUNGs
        $pflichtzeiten    = []; // wertungsID → ['time_ms' => int, 'deadline' => string|null]
        $meldegelder      = []; // wertungsID → meldegeld amount

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            // Skip Pascal comment lines
            if (str_starts_with($line, '(*')) continue;
            // Strip inline trailing comments: (* ... *)
            $line = preg_replace('/\s*\(\*.*?\*\)\s*$/', '', $line);
            $line = trim($line);
            if ($line === '') continue;

            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;

            $keyword = strtoupper(trim(substr($line, 0, $colonPos)));
            $rest    = substr($line, $colonPos + 1);
            $fields  = explode(';', $rest);
            $f       = static fn(int $i) => trim($fields[$i] ?? '');
            $count   = count($fields);

            if (in_array($keyword, self::SKIP_KEYWORDS, true)) continue;

            switch ($keyword) {

                case 'VERANSTALTUNG':
                    // Name;City;PoolLength(25|50);TimingType;...
                    $meta['name']   = $f(0);
                    $meta['city']   = $f(1);
                    $meta['course'] = $f(2) === '50' ? 'Langbahn' : 'Kurzbahn';
                    break;

                case 'VERANSTALTER':
                    $meta['organizer'] = $f(0);
                    break;

                case 'ABSCHNITT':
                    // Nr;DD.MM.YYYY;...
                    $num = (int)$f(0);
                    $dt  = $this->parseGermanDate($f(1));
                    if ($num && $dt) $sessions[$num] = $dt;
                    break;

                case 'VEREIN':
                    // Tracks current club context for Vereinsergebnisliste relay records
                    $currentClub = $f(0);
                    break;

                case 'WETTKAMPF':
                    $pendingRelay = null;

                    // Format detection: stroke code position differs between formats.
                    //
                    // Official DSV / EasyWK new:
                    //   nr ; art_or_runde ; abschnitt ; starter_or_legs ; strecke ; technik ; ausübung ; geschlecht
                    //    0        1              2               3             4         5          6           7
                    //
                    // EasyWK old:
                    //   nr ; abschnitt ; staffelstrecke ; strecke ; stil ; lagenfolge ; geschlecht
                    //    0       1              2             3       4          5            6

                    $strokeAtNew = strtoupper($f(5));
                    $strokeAtOld = strtoupper($f(4));

                    if ($useNewFormat === null) {
                        $useNewFormat = isset(self::STROKE_MAP[$strokeAtNew])
                            || (!isset(self::STROKE_MAP[$strokeAtOld]));
                    }

                    if ($useNewFormat) {
                        $eventNum   = (int)$f(0);
                        $sessionNum = (int)$f(2);
                        $relayLegs  = (int)$f(3);
                        $distance   = (int)$f(4);
                        $strokeCode = $strokeAtNew;
                        $gender     = $this->normalizeGender($f(7));
                    } else {
                        $eventNum   = (int)$f(0);
                        $sessionNum = (int)$f(1);
                        $relayLegs  = (int)$f(2);
                        $distance   = (int)$f(3);
                        $strokeCode = $strokeAtOld;
                        $gender     = $this->normalizeGender($f(6));
                    }

                    // Only store events with known strokes; skip 'X' (Sonderform) etc.
                    $discipline = self::STROKE_MAP[$strokeCode] ?? null;

                    if (!$discipline || $distance <= 0) {
                        $currentWettkampf = null;
                        break;
                    }

                    $currentWettkampf = [
                        'event_number'   => $eventNum,
                        'session_number' => $sessionNum,
                        'discipline'     => $discipline,
                        'distance'       => $relayLegs > 1 ? $distance * $relayLegs : $distance,
                        'gender'         => $gender,
                        'is_relay'       => $relayLegs > 1,
                        'relay_legs'     => $relayLegs,
                    ];
                    // Store by event number so WERTUNG can look it up regardless of
                    // whether all WETTKAMPFs precede all WERTUNGs in the file.
                    $wettkampfByNum[$eventNum] = $currentWettkampf;
                    break;

                case 'WERTUNG':
                    // Official DSV / EasyWK new:
                    //   wkNr ; art_or_runde ; wertungsID ; typ ; minJGAK ; maxJGAK ; geschlecht ; name
                    //     0          1             2         3      4         5           6          7
                    //
                    // EasyWK old (no wkNr field):
                    //   wertungsID ; typ ; minJGAK ; maxJGAK ; geschlecht ; name
                    //       0         1      2         3           4          5
                    //
                    // For new format: look up the WETTKAMPF by wkNr (f(0)) so that files
                    // where all WETTKAMPF lines precede all WERTUNG lines are handled correctly.

                    if ($useNewFormat) {
                        $wkNr       = (int)$f(0);
                        $sourceWk   = $wettkampfByNum[$wkNr] ?? $currentWettkampf;
                        $artRaw     = strtoupper(trim($f(1)));
                        $wertungId  = (int)$f(2);
                        $ageMin     = is_numeric($f(4)) ? (int)$f(4) : null;
                        $ageMax     = is_numeric($f(5)) ? (int)$f(5) : null;
                        $wGender    = $this->normalizeGender($f(6));
                        $ageGroup   = $f(7);
                    } else {
                        $sourceWk   = $currentWettkampf;
                        $artRaw     = '';
                        $wertungId  = (int)$f(0);
                        $ageMin     = is_numeric($f(2)) ? (int)$f(2) : null;
                        $ageMax     = is_numeric($f(3)) ? (int)$f(3) : null;
                        $wGender    = $this->normalizeGender($f(4));
                        $ageGroup   = $f(5);
                    }

                    // V=Vorlauf, Z=Zwischenlauf, F=Finale, E=Einzel; numbers = EasyWK runde
                    $artCode = in_array($artRaw, ['V', 'Z', 'F', 'E']) ? $artRaw : '';

                    if (!$wertungId || !$sourceWk) break;

                    $wertungMap[$wertungId] = array_merge($sourceWk, [
                        'gender'             => $wGender !== 'X' ? $wGender : $sourceWk['gender'],
                        'age_group'          => $ageGroup,
                        'age_min'            => $ageMin,
                        'age_max'            => $ageMax,
                        'art'                => $artCode,
                        'qualifying_time_ms' => null,
                        'meldegeld'          => null,
                    ]);
                    break;

                case 'PFLICHTZEIT':
                    // Qualifying time for a specific Wertung.
                    //
                    // New format: wkNr ; art ; wertungsID ; meldeschluss ; zeit ; ...
                    //               0     1        2             3           4
                    //
                    // Old / simple: wertungsID ; zeit ; ...
                    //                   0          1

                    if ($useNewFormat && ctype_alpha($f(1)) && $f(1) !== '') {
                        $pWertungId   = (int)$f(2);
                        $pMeldeschluss = $f(3); // DD.MM.YYYY or empty
                        $pTimeStr      = $f(4) !== '' ? $f(4) : $f(3);
                        // If f(4) is empty, f(3) is the time (old 3-field variant)
                        if ($f(4) === '') {
                            $pMeldeschluss = '';
                            $pTimeStr      = $f(3);
                        }
                    } else {
                        $pWertungId    = (int)$f(0);
                        $pMeldeschluss = '';
                        $pTimeStr      = $f(1);
                    }

                    if ($pWertungId && $pTimeStr !== '' && strtoupper($pTimeStr) !== 'NT') {
                        $pTimeMs = $this->parseTime($pTimeStr);
                        if ($pTimeMs > 0) {
                            $pDeadline = null;
                            if ($pMeldeschluss !== '') {
                                // DSV date format: DD.MM.YYYY
                                $parsed = \DateTime::createFromFormat('d.m.Y', trim($pMeldeschluss));
                                if ($parsed) {
                                    $pDeadline = $parsed->format('Y-m-d');
                                }
                            }
                            $pflichtzeiten[$pWertungId] = ['time_ms' => $pTimeMs, 'deadline' => $pDeadline];
                        }
                    }
                    break;

                case 'MELDEGELD':
                    // Entry fee per start, optionally per Wertung.
                    //
                    // New format (per wertung): wkNr ; art ; wertungsID ; betrag ; waehrung
                    //                             0     1        2           3        4
                    //
                    // Simple format (global):  betrag ; waehrung ; beschreibung
                    //                             0        1            2

                    if ($useNewFormat && ctype_alpha($f(1)) && $f(1) !== '' && is_numeric($f(3))) {
                        $mWertungId = (int)$f(2);
                        $mAmount    = (float)str_replace(',', '.', $f(3));
                        if ($mWertungId && $mAmount > 0) {
                            $meldegelder[$mWertungId] = $mAmount;
                        }
                    } elseif (is_numeric(str_replace(',', '.', $f(0)))) {
                        // Global fee: apply to all wertungen (resolved after parsing)
                        $mAmount = (float)str_replace(',', '.', $f(0));
                        if ($mAmount > 0) {
                            $meldegelder['__global__'] = $mAmount;
                        }
                    }
                    break;

                case 'PNERGEBNIS':
                case 'ERGEBNIS':
                    $pendingRelay = null;

                    // Official DSV / EasyWK extended (PNERGEBNIS or ERGEBNIS with ≥14 fields):
                    //   wkNr ; art ; wertungsID ; platz ; nichtwertung ; name ; dsvID ; veranstID ;
                    //     0     1        2           3          4          5      6        7
                    //   geschl ; jg ; AK ; verein ; vereinNr ; endzeit ; punkte ; status
                    //     8      9    10    11        12          13        14       15
                    //
                    // Compact DSV (ERGEBNIS with <14 fields):
                    //   platz ; wertungsID ; lauf ; bahn ; name ; dsvID ; athID ; endzeit ; punkte ; status ; verein
                    //     0         1         2      3      4      5       6        7         8        9       10

                    if ($count >= 14 || $keyword === 'PNERGEBNIS') {
                        $placement = (int)$f(3); // 0 = DNS/AB/not placed
                        $wertungId = (int)$f(2);
                        $rawStatus = strtoupper($f(4)); // nichtwertung
                        $rawName   = $f(5);
                        $dsvId     = $f(6);
                        $gender    = $this->normalizeGender($f(8));
                        $birthYear = $f(9);
                        $clubName  = $f(11);
                        $timeStr   = $f(13);
                    } else {
                        $placement = (int)$f(0);
                        $wertungId = (int)$f(1);
                        $rawStatus = strtoupper($f(9)); // status field in compact
                        $rawName   = $f(4);
                        $dsvId     = $f(5);
                        $gender    = 'X';
                        $birthYear = '';
                        $clubName  = $f(10);
                        $timeStr   = $f(7);
                    }

                    if ($rawName === '') break;
                    if (!isset($wertungMap[$wertungId])) break;

                    $isDnsType = in_array($rawStatus, self::DNS_STATUSES, true);

                    // Parse time if present
                    $timeMs = 0;
                    if ($timeStr !== '' && strtoupper($timeStr) !== 'NT') {
                        $timeMs = $this->parseTime($timeStr);
                    }

                    // Skip entries with no time AND no known DNS/AB status
                    if ($timeMs <= 0 && !$isDnsType) break;

                    $wertung   = $wertungMap[$wertungId];
                    $ageGroup  = $wertung['age_group'] ?? '';

                    if (!isset($clubs[$clubName])) {
                        $clubs[$clubName] = ['name' => $clubName, 'athletes' => []];
                    }

                    $athleteKey = $rawName . '|' . $birthYear;

                    if (!isset($clubs[$clubName]['athletes'][$athleteKey])) {
                        $clubs[$clubName]['athletes'][$athleteKey] = [
                            'name'            => $this->parseName($rawName),
                            'firstname'       => $this->extractFirstname($rawName),
                            'lastname'        => $this->extractLastname($rawName),
                            'birthdate'       => $birthYear,
                            'gender'          => $gender,
                            'dsvid'           => $dsvId,
                            'is_relay'        => false,
                            'relay_members'   => [],
                            'results'         => [],
                            'matched_user_id' => null,
                        ];
                    }

                    // Deduplicate: same physical swim = same (event_number, round_type, time/status)
                    $eventNum  = $wertung['event_number'];
                    $roundType = $wertung['art'] ?? '';
                    $resultKey = $eventNum . '|' . ($isDnsType ? ('DNS.' . $rawStatus) : $roundType);

                    $foundIdx = null;
                    foreach ($clubs[$clubName]['athletes'][$athleteKey]['results'] as $i => $r) {
                        if (($r['result_key'] ?? '') === $resultKey) {
                            $foundIdx = $i;
                            break;
                        }
                    }

                    if ($foundIdx !== null) {
                        // Same physical swim in another Wertung — accumulate only
                        if ($ageGroup !== '' && !in_array($ageGroup, $clubs[$clubName]['athletes'][$athleteKey]['results'][$foundIdx]['wertungen'])) {
                            $clubs[$clubName]['athletes'][$athleteKey]['results'][$foundIdx]['wertungen'][] = $ageGroup;
                        }
                    } else {
                        $clubs[$clubName]['athletes'][$athleteKey]['results'][] = [
                            'result_key' => $resultKey,
                            'eventid'    => (string)$wertungId,
                            'discipline' => $wertung['discipline'],
                            'distance'   => $wertung['distance'],
                            'age_group'  => $ageGroup,
                            'wertungen'  => $ageGroup !== '' ? [$ageGroup] : [],
                            'gender'     => $gender !== 'X' ? $gender : ($wertung['gender'] ?? 'X'),
                            'time_ms'    => $timeMs,
                            'swimtime'   => $timeMs > 0 ? $this->formatTime($timeMs) : ($isDnsType ? $rawStatus : 'NT'),
                            'place'      => $isDnsType ? 0 : ($placement ?: null),
                            'status'     => $isDnsType ? $rawStatus : null,
                            'round_type' => $roundType,
                            'is_relay'   => false,
                        ];
                    }
                    break;

                case 'STAFFELERGEBNIS':
                case 'PNSTAFFELERGEBNIS':
                    // Two possible formats:
                    //
                    // Official DSV Vereinsergebnisliste — f(2) is art (a letter like E/V/F):
                    //   VIDstaffel ; wkNr ; art ; wertungsID ; platz ; endzeit
                    //       0          1     2        3           4       5
                    //
                    // EasyWK — f(2) is wertungsID (a number):
                    //   platz ; runde ; wertungs_nr ; lauf ; bahn ; staffel_name ; verein ; vereinNr ; endzeit
                    //     0       1         2           3      4         5            6        7          8

                    $isOfficialVer = $f(2) !== '' && ctype_alpha($f(2));

                    if ($isOfficialVer) {
                        $wertungId   = (int)$f(3);
                        $placement   = ((int)$f(4)) ?: null;
                        $timeStr     = $f(5);
                        $staffelName = 'Staffel';
                        $clubName    = $currentClub ?? '';
                        $staffelId   = (int)$f(0);
                    } else {
                        $wertungId   = (int)$f(2);
                        $placement   = ((int)$f(0)) ?: null;
                        $timeStr     = $f(8);
                        $staffelName = $f(5) !== '' ? $f(5) : 'Staffel';
                        $clubName    = $f(6);
                        $staffelId   = 0;
                    }

                    if (!isset($wertungMap[$wertungId])) break;
                    if ($clubName === '') break;
                    if ($timeStr === '' || strtoupper($timeStr) === 'NT') break;

                    $timeMs = $this->parseTime($timeStr);
                    if ($timeMs <= 0) break;

                    $wertung    = $wertungMap[$wertungId];
                    $ageGroup   = $wertung['age_group'] ?? '';
                    $eventNum   = $wertung['event_number'];
                    // Key by event + staffelId (not wertungId) so same relay under multiple Wertungen merges
                    $athleteKey = '__relay_' . $eventNum . '_' . ($staffelId ?: md5($staffelName));

                    if (!isset($clubs[$clubName])) {
                        $clubs[$clubName] = ['name' => $clubName, 'athletes' => []];
                    }

                    if (!isset($clubs[$clubName]['athletes'][$athleteKey])) {
                        $clubs[$clubName]['athletes'][$athleteKey] = [
                            'name'            => $staffelName,
                            'firstname'       => $staffelName,
                            'lastname'        => '(Staffel)',
                            'birthdate'       => '',
                            'gender'          => $wertung['gender'],
                            'dsvid'           => '',
                            'is_relay'        => true,
                            'relay_members'   => [],
                            'results'         => [],
                            'matched_user_id' => null,
                        ];
                    }

                    if (empty($clubs[$clubName]['athletes'][$athleteKey]['results'])) {
                        $clubs[$clubName]['athletes'][$athleteKey]['results'][] = [
                            'eventid'    => (string)$wertungId,
                            'discipline' => $wertung['discipline'],
                            'distance'   => $wertung['distance'],
                            'age_group'  => $ageGroup,
                            'wertungen'  => $ageGroup !== '' ? [$ageGroup] : [],
                            'time_ms'    => $timeMs,
                            'swimtime'   => $this->formatTime($timeMs),
                            'place'      => $placement,
                            'status'     => null,
                            'is_relay'   => true,
                        ];
                    } elseif ($ageGroup !== '' && !in_array($ageGroup, $clubs[$clubName]['athletes'][$athleteKey]['results'][0]['wertungen'])) {
                        $clubs[$clubName]['athletes'][$athleteKey]['results'][0]['wertungen'][] = $ageGroup;
                    }

                    $pendingRelay = ['club' => $clubName, 'key' => $athleteKey];
                    break;

                case 'STERGEBNIS':
                    // Wettkampfergebnisliste relay: wkNr;art;wertungsID;platz;nichtwertung;nrMannschaft;VIDstaffel;verein;vereinNr;endzeit
                    $wertungId   = (int)$f(2);
                    $placement   = ((int)$f(3)) ?: null;
                    $staffelId   = (int)$f(6);
                    $staffelName = $f(5) !== '' ? $f(5) : 'Staffel';
                    $clubName    = $f(7);
                    $timeStr     = $f(9);

                    if (!isset($wertungMap[$wertungId])) break;
                    if ($clubName === '') break;
                    if ($timeStr === '' || strtoupper($timeStr) === 'NT') break;

                    $timeMs = $this->parseTime($timeStr);
                    if ($timeMs <= 0) break;

                    $wertung    = $wertungMap[$wertungId];
                    $ageGroup   = $wertung['age_group'] ?? '';
                    $eventNum   = $wertung['event_number'];
                    $athleteKey = '__relay_' . $eventNum . '_' . $staffelId;

                    if (!isset($clubs[$clubName])) {
                        $clubs[$clubName] = ['name' => $clubName, 'athletes' => []];
                    }

                    if (!isset($clubs[$clubName]['athletes'][$athleteKey])) {
                        $clubs[$clubName]['athletes'][$athleteKey] = [
                            'name'            => $staffelName,
                            'firstname'       => $staffelName,
                            'lastname'        => '(Staffel)',
                            'birthdate'       => '',
                            'gender'          => $wertung['gender'],
                            'dsvid'           => '',
                            'is_relay'        => true,
                            'relay_members'   => [],
                            'results'         => [],
                            'matched_user_id' => null,
                        ];
                    }

                    if (empty($clubs[$clubName]['athletes'][$athleteKey]['results'])) {
                        $clubs[$clubName]['athletes'][$athleteKey]['results'][] = [
                            'eventid'    => (string)$wertungId,
                            'discipline' => $wertung['discipline'],
                            'distance'   => $wertung['distance'],
                            'age_group'  => $ageGroup,
                            'wertungen'  => $ageGroup !== '' ? [$ageGroup] : [],
                            'time_ms'    => $timeMs,
                            'swimtime'   => $this->formatTime($timeMs),
                            'place'      => $placement,
                            'status'     => null,
                            'is_relay'   => true,
                        ];
                    } elseif ($ageGroup !== '' && !in_array($ageGroup, $clubs[$clubName]['athletes'][$athleteKey]['results'][0]['wertungen'])) {
                        $clubs[$clubName]['athletes'][$athleteKey]['results'][0]['wertungen'][] = $ageGroup;
                    }

                    $pendingRelay = ['club' => $clubName, 'key' => $athleteKey];
                    break;

                case 'STAFFELMITGLIED':
                case 'PNSTAFFELMITGLIED':
                case 'STAFFELPERSON':
                    if (!$pendingRelay) break;

                    $memberName = $f(1);
                    $birthYear  = $keyword === 'STAFFELPERSON' ? $f(3) : $f(5);
                    $splitTime  = $keyword === 'STAFFELPERSON' ? $f(8) : $f(7);

                    $clubs[$pendingRelay['club']]['athletes'][$pendingRelay['key']]['relay_members'][] = [
                        'name'      => $this->parseName($memberName),
                        'firstname' => $this->extractFirstname($memberName),
                        'lastname'  => $this->extractLastname($memberName),
                        'birthyear' => $birthYear,
                        'splittime' => $splitTime,
                    ];
                    break;

                default:
                    // Unknown record — ignore without clearing $pendingRelay
                    break;
            }
        }

        // Merge PFLICHTZEIT and MELDEGELD into wertungMap
        $globalFee = $meldegelder['__global__'] ?? null;

        foreach ($wertungMap as $wid => &$wertung) {
            if (isset($pflichtzeiten[$wid])) {
                $wertung['qualifying_time_ms'] = $pflichtzeiten[$wid]['time_ms'];
                $wertung['qualifying_deadline'] = $pflichtzeiten[$wid]['deadline'];
            }
            if (isset($meldegelder[$wid])) {
                $wertung['meldegeld'] = $meldegelder[$wid];
            } elseif ($globalFee !== null) {
                $wertung['meldegeld'] = $globalFee;
            }
        }
        unset($wertung);

        return [$meta, $sessions, $wertungMap, $clubs];
    }

    // ── File reading ────────────────────────────────────────────────────────

    private function readFile(string $filePath): string
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            return $converted !== false ? $converted : $raw;
        }

        return $raw;
    }

    // ── Name helpers ────────────────────────────────────────────────────────

    private function parseName(string $raw): string
    {
        if (str_contains($raw, ',')) {
            [$last, $first] = explode(',', $raw, 2);
            return trim(trim($first) . ' ' . trim($last));
        }
        return trim($raw);
    }

    private function extractFirstname(string $raw): string
    {
        if (str_contains($raw, ',')) {
            return trim(explode(',', $raw, 2)[1]);
        }
        $parts = explode(' ', trim($raw), 2);
        return $parts[0];
    }

    private function extractLastname(string $raw): string
    {
        if (str_contains($raw, ',')) {
            return trim(explode(',', $raw, 2)[0]);
        }
        $parts = explode(' ', trim($raw), 2);
        return $parts[1] ?? $parts[0];
    }

    // ── Date/time helpers ───────────────────────────────────────────────────

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
