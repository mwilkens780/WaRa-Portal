<?php

namespace App\Services;

use App\Services\WaScoringService;
use SimpleXMLElement;

class DsvImportService
{
    private Dsv7Parser $dsv7Parser;
    private WaScoringService $waScoring;

    public function __construct()
    {
        $this->dsv7Parser = new Dsv7Parser();
        $this->waScoring  = new WaScoringService();
    }

    /**
     * Detect whether a file is EasyWk DSV7 semicolon-delimited format (vs Lenex XML).
     * DSV7 files start with Pascal comments or a FORMAT: keyword line.
     */
    private function isDsv7(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return false;

        for ($i = 0; $i < 20; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '') continue;
            // Pascal comment lines — skip
            if (str_starts_with($line, '(*')) continue;
            // First non-comment, non-empty line
            fclose($handle);
            // DSV7 keyword lines look like "FORMAT: ..." or "VERANSTALTUNG: ..."
            return str_contains($line, ':') && !str_starts_with($line, '<');
        }

        fclose($handle);
        return false;
    }

    const STROKE_MAP = [
        'FREE'   => 'F',
        'BACK'   => 'R',
        'BREAST' => 'B',
        'FLY'    => 'S',
        'MEDLEY' => 'L',
    ];

    /**
     * Load a Lenex file, stripping EasyWk-style Pascal comments before parsing.
     * EasyWk prepends (* ... *) comment blocks to otherwise valid Lenex XML.
     *
     * @throws \RuntimeException on invalid file
     */
    private function loadXml(string $filePath): SimpleXMLElement
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        }

        // Strip Pascal-style (* ... *) comment lines produced by EasyWk and similar tools
        $raw = preg_replace('/^\(\*.*?\*\)\s*/ms', '', $raw);

        // Find the first XML tag and discard any remaining non-XML preamble
        $xmlStart = strpos($raw, '<');
        if ($xmlStart === false) {
            throw new \RuntimeException('Kein XML-Inhalt in der Datei gefunden.');
        }
        $raw = substr($raw, $xmlStart);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);

        if ($xml === false) {
            $err = libxml_get_errors()[0] ?? null;
            throw new \RuntimeException('Ungültiges XML: ' . ($err?->message ?? 'unbekannter Fehler'));
        }

        return $xml;
    }

    /**
     * Parse a Lenex file for competition creation: returns meet metadata + full event list.
     * Works with both Ausschreibungs- and Ergebnisdateien.
     *
     * @throws \RuntimeException on invalid file
     */
    public function parseMeetDefinition(string $filePath): array
    {
        if ($this->isDsv7($filePath)) {
            return $this->dsv7Parser->parseMeetDefinition($filePath);
        }

        $xml = $this->loadXml($filePath);

        if ($xml === false) {
            $err = libxml_get_errors()[0] ?? null;
            throw new \RuntimeException('Ungültige XML-Datei: ' . ($err?->message ?? 'unbekannter Fehler'));
        }

        $meets = [];

        foreach ($xml->MEETS->MEET ?? [] as $meet) {
            $startdate = (string)$meet['startdate'];
            $enddate   = (string)($meet['enddate'] ?: $meet['startdate']);

            $meetData = [
                'name'      => (string)$meet['name'],
                'city'      => (string)$meet['city'],
                'course'    => strtoupper((string)($meet['course'] ?? '')) === 'LCM' ? 'Langbahn' : 'Kurzbahn',
                'startdate' => $startdate,
                'enddate'   => $enddate,
                'organizer' => (string)($meet->ORGANIZER['name'] ?? ''),
                'events'    => [],
            ];

            foreach ($meet->SESSIONS->SESSION ?? [] as $session) {
                $sessionNum  = (int)($session['number'] ?? 1);
                $sessionDate = (string)($session['date'] ?? $startdate);
                $sessionName = (string)($session['name'] ?? '');

                foreach ($session->EVENTS->EVENT ?? [] as $event) {
                    $eventNum = (int)($event['number'] ?? 0);
                    $gender   = strtoupper((string)($event['gender'] ?? 'X'));
                    $style    = $event->SWIMSTYLE ?? null;
                    if (!$style) continue;

                    $stroke   = strtoupper((string)$style['stroke']);
                    $distance = (int)$style['distance'];
                    if (!isset(self::STROKE_MAP[$stroke]) || $distance <= 0) continue;

                    // Collect age groups (one event can have multiple age groups)
                    $ageGroups = [];
                    foreach ($event->AGEGROUPS->AGEGROUP ?? [] as $ag) {
                        $ageGroups[] = [
                            'age_min'   => ((int)($ag['agemin'] ?? 0)) ?: null,
                            'age_max'   => ((int)($ag['agemax'] ?? 0)) ?: null,
                            'age_group' => (string)($ag['name'] ?? ''),
                        ];
                    }

                    // If no age groups defined, add a blank one so we still record the event
                    if (empty($ageGroups)) {
                        $ageGroups[] = ['age_min' => null, 'age_max' => null, 'age_group' => ''];
                    }

                    foreach ($ageGroups as $ag) {
                        $meetData['events'][] = [
                            'event_number'   => $eventNum,
                            'session_number' => $sessionNum,
                            'session_date'   => $sessionDate,
                            'session_name'   => $sessionName,
                            'discipline'     => self::STROKE_MAP[$stroke],
                            'distance'       => $distance,
                            'gender'         => in_array($gender, ['M', 'F']) ? $gender : 'X',
                            'age_min'        => $ag['age_min'],
                            'age_max'        => $ag['age_max'],
                            'age_group'      => $ag['age_group'],
                        ];
                    }
                }
            }

            if ($meetData['name']) {
                $meets[] = $meetData;
            }
        }

        if (empty($meets)) {
            throw new \RuntimeException('Keine Wettkampfdaten in der Datei gefunden.');
        }

        return ['meets' => $meets];
    }

    /**
     * Parse a Lenex XML file (.lef / .xml / .dsv7) and return structured data.
     *
     * @throws \RuntimeException on invalid file
     */
    public function parse(string $filePath): array
    {
        if ($this->isDsv7($filePath)) {
            return $this->dsv7Parser->parseResults($filePath);
        }

        $xml = $this->loadXml($filePath);

        $meets = [];
        foreach ($xml->MEETS->MEET ?? [] as $meet) {
            $meetData = $this->parseMeet($meet);
            if (!empty($meetData['clubs'])) {
                $meets[] = $meetData;
            }
        }

        return ['meets' => $meets];
    }

    private function parseMeet(SimpleXMLElement $meet): array
    {
        $startdate = (string)$meet['startdate'];
        $enddate   = (string)($meet['enddate'] ?: $meet['startdate']);

        $data = [
            'name'      => (string)$meet['name'],
            'city'      => (string)$meet['city'],
            'course'    => strtoupper((string)($meet['course'] ?? '')) === 'LCM' ? 'Langbahn' : 'Kurzbahn',
            'startdate' => $startdate,
            'enddate'   => $enddate,
            'events'    => [],
            'clubs'     => [],
        ];

        // Build event map: eventid/number → discipline + distance
        foreach ($meet->SESSIONS->SESSION ?? [] as $session) {
            foreach ($session->EVENTS->EVENT ?? [] as $event) {
                // Lenex uses either 'eventid' or 'number' as the key
                $key    = (string)($event['eventid'] ?? $event['number']);
                $style  = $event->SWIMSTYLE ?? null;
                if (!$style || !$key) continue;

                $stroke   = strtoupper((string)$style['stroke']);
                $distance = (int)$style['distance'];

                if (isset(self::STROKE_MAP[$stroke]) && $distance > 0) {
                    $data['events'][$key] = [
                        'discipline' => self::STROKE_MAP[$stroke],
                        'distance'   => $distance,
                    ];
                }
            }
        }

        // Some files also reference events by number even if eventid exists; add both keys
        foreach ($meet->SESSIONS->SESSION ?? [] as $session) {
            foreach ($session->EVENTS->EVENT ?? [] as $event) {
                $id  = (string)($event['eventid'] ?? '');
                $num = (string)($event['number'] ?? '');
                if ($id && $num && $id !== $num && isset($data['events'][$id])) {
                    $data['events'][$num] = $data['events'][$id];
                }
            }
        }

        foreach ($meet->CLUBS->CLUB ?? [] as $club) {
            $clubData = $this->parseClub($club, $data['events']);
            if (!empty($clubData['athletes'])) {
                $data['clubs'][] = $clubData;
            }
        }

        return $data;
    }

    private function parseClub(SimpleXMLElement $club, array $eventMap): array
    {
        $clubData = [
            'name'      => (string)$club['name'],
            'shortname' => (string)($club['shortname'] ?? $club['code'] ?? ''),
            'athletes'  => [],
        ];

        foreach ($club->ATHLETES->ATHLETE ?? [] as $athlete) {
            $parsed = $this->parseAthlete($athlete, $eventMap);
            if (!empty($parsed['results'])) {
                $clubData['athletes'][] = $parsed;
            }
        }

        return $clubData;
    }

    private function parseAthlete(SimpleXMLElement $athlete, array $eventMap): array
    {
        $firstname = (string)$athlete['firstname'];
        $lastname  = (string)$athlete['lastname'];

        $data = [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'name'      => trim($firstname . ' ' . $lastname),
            'birthdate' => (string)($athlete['birthdate'] ?? ''),
            'gender'    => strtoupper((string)($athlete['gender'] ?? '')),
            'dsvid'     => (string)($athlete['athleteid'] ?? ''),
            'results'   => [],
        ];

        foreach ($athlete->RESULTS->RESULT ?? [] as $result) {
            $status   = strtoupper((string)($result['status'] ?? 'OK'));
            $swimtime = trim((string)($result['swimtime'] ?? ''));
            $eventId  = (string)($result['eventid'] ?? '');

            if (in_array($status, ['DQ', 'DNS', 'DNF', 'WDR', 'DSQ', 'EXH'])) continue;
            if ($swimtime === '' || $swimtime === 'NT') continue;
            if (!isset($eventMap[$eventId])) continue;

            $timeMs = self::parseSwimtime($swimtime);
            if ($timeMs <= 0) continue;

            $data['results'][] = [
                'eventid'    => $eventId,
                'discipline' => $eventMap[$eventId]['discipline'],
                'distance'   => $eventMap[$eventId]['distance'],
                'time_ms'    => $timeMs,
                'swimtime'   => $swimtime,
                'place'      => ((int)($result['place'] ?? 0)) ?: null,
            ];
        }

        return $data;
    }

    /**
     * Parse Lenex swimtime string (e.g. "1:02.34" or "28.56") into milliseconds.
     */
    public static function parseSwimtime(string $swimtime): int
    {
        $swimtime = trim($swimtime);

        if (str_contains($swimtime, ':')) {
            [$min, $rest] = explode(':', $swimtime, 2);
            [$sec, $centi] = array_pad(explode('.', $rest, 2), 2, '0');
            return ((int)$min * 60_000)
                + ((int)$sec * 1_000)
                + (int)str_pad(substr($centi, 0, 2), 2, '0') * 10;
        }

        [$sec, $centi] = array_pad(explode('.', $swimtime, 2), 2, '0');
        return ((int)$sec * 1_000)
            + (int)str_pad(substr($centi, 0, 2), 2, '0') * 10;
    }

    /**
     * Parse a DSV7 result file and persist results for matched own swimmers.
     * Used by BatchImporter and Crawlers.
     *
     * Stores the import_hash on the created/found Competition to prevent duplicates.
     */
    public function importResultsFile(
        string  $filePath,
        string  $source     = 'manual',
        ?string $importHash = null,
        ?string $sourceUrl  = null
    ): \App\Models\Competition {
        $parsed = $this->parse($filePath);
        $meet   = $parsed['meets'][0] ?? null;

        if (!$meet) {
            throw new \RuntimeException('Keine gültigen Wettkampfdaten in Datei gefunden.');
        }

        // Find or create Competition
        $competition = \App\Models\Competition::firstOrCreate(
            ['name' => $meet['name'], 'date' => $meet['startdate'] ?? now()->toDateString()],
            [
                'location'    => $meet['city'] ?? '',
                'type'        => 'regional',
                'organizer'   => $meet['organizer'] ?? null,
                'course'      => ($meet['course'] ?? 'Kurzbahn'),
                'date_end'    => $meet['enddate'] ?? null,
                'source_file' => basename($filePath),
                'source_url'  => $sourceUrl,
                'import_hash' => $importHash,
            ]
        );

        if ($importHash && !$competition->import_hash) {
            $competition->update(['import_hash' => $importHash]);
        }

        $swimmers   = \App\Models\User::where('role', 'schwimmer')->where('active', true)->get();
        $poolLength = $this->waScoring->poolLengthFromCourse($meet['course'] ?? '');
        $compYear   = (int) substr($meet['startdate'] ?? now()->toDateString(), 0, 4);
        $waYear     = $this->waScoring->latestYear($poolLength) ?? $compYear;

        foreach ($meet['clubs'] as $club) {
            foreach ($club['athletes'] as $athlete) {
                if ($athlete['is_relay'] ?? false) continue;

                $userId = $this->matchAthleteToUser($athlete, $swimmers);
                if (!$userId) continue;

                foreach ($athlete['results'] ?? [] as $result) {
                    $this->persistResult($competition->id, $userId, $result, $athlete['gender'] ?? 'X', $poolLength, $waYear);
                }
            }
        }

        \App\Models\ImportLog::create([
            'source'         => $source,
            'source_url'     => $sourceUrl,
            'filename'       => basename($filePath),
            'status'         => 'success',
            'competition_id' => $competition->id,
            'message'        => 'Automatischer Import',
        ]);

        return $competition;
    }

    private function matchAthleteToUser(array $athlete, \Illuminate\Support\Collection $swimmers): ?int
    {
        $first = mb_strtolower(trim($athlete['firstname'] ?? ''));
        $last  = mb_strtolower(trim($athlete['lastname']  ?? ''));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->firstname)) === $first
                && mb_strtolower(trim($swimmer->lastname)) === $last) {
                return $swimmer->id;
            }
        }
        return null;
    }

    private function persistResult(int $competitionId, int $userId, array $result, string $gender, int $poolLength = 25, int $waYear = 0): void
    {
        $exists = \App\Models\CompetitionResult::where([
            'competition_id' => $competitionId,
            'user_id'        => $userId,
            'discipline'     => $result['discipline'],
            'distance'       => $result['distance'],
            'age_group'      => $result['age_group'] ?? null,
        ])->exists();

        if ($exists) return;

        $isPb = false;
        if (!empty($result['time_ms'])) {
            $best = \App\Models\CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->where('time_ms', '>', 0)
                ->min('time_ms');
            $isPb = !$best || $result['time_ms'] < $best;
        }

        $resolvedGender = $gender !== 'X' ? $gender : null;
        $waPoints       = null;
        $usedWaYear     = null;
        if ($waYear && $resolvedGender && !empty($result['time_ms'])) {
            $waPoints   = $this->waScoring->calculatePoints(
                $result['discipline'], $result['distance'], $resolvedGender,
                $result['time_ms'], $waYear, $poolLength
            );
            $usedWaYear = $waPoints !== null ? $waYear : null;
        }

        \App\Models\CompetitionResult::create([
            'competition_id'   => $competitionId,
            'user_id'          => $userId,
            'discipline'       => $result['discipline'],
            'distance'         => $result['distance'],
            'time_ms'          => $result['time_ms'] ?? 0,
            'placement'        => $result['place'] ?? null,
            'is_personal_best' => $isPb,
            'age_group'        => $result['age_group'] ?? null,
            'gender'           => $resolvedGender,
            'is_final'         => ($result['round_type'] ?? '') === 'F',
            'notes'            => $result['status'] ?? null,
            'wa_points'        => $waPoints,
            'wa_table_year'    => $usedWaYear,
        ]);
    }
}
