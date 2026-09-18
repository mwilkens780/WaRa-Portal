<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\ImportLog;
use App\Models\RelayResult;
use App\Models\Setting;
use App\Models\User;
use App\Services\Import\ResultReconciler;
use App\Services\WaScoringService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Crawlt Wettkampfergebnisse von dsvdaten.dsv.de.
 *
 * Quelle: öffentlich zugängliche PDF-Protokolle auf dsvdaten.dsv.de.
 * Keine Session/Cookie nötig: File.aspx?F=WKResults ist ohne Authentifizierung abrufbar.
 *
 * StateID=14 = Schleswig-Holstein (SHSV)
 */
class DsvDataCrawler
{
    private const BASE_URL     = 'https://dsvdaten.dsv.de';
    private const LIST_URL     = 'https://dsvdaten.dsv.de/Modules/Results/MeetYear.aspx';
    private const FILE_URL     = 'https://dsvdaten.dsv.de/File.aspx';

    // Default-StateID für Schleswig-Holstein; wird durch Admin-Einstellungen überschrieben
    private const DEFAULT_STATE_IDS = [14];

    /** Jahre rueckwaerts ab dem aktuellen Jahr; 1 = bisheriges Verhalten (2 Jahre). */
    private const DEFAULT_LOOKBACK_YEARS = 1;
    private const MAX_LOOKBACK_YEARS     = 25;

    /** Ueberschreibbar per Setting 'crawler.own_club_names' (JSON-Array). */
    private const DEFAULT_OWN_CLUB_NAMES = [
        'SG Wasserratten Norderstedt',
        'SG Wasserratten',
    ];

    /**
     * Reihenfolge ist entscheidend: 'lagen' MUSS vor den Einzellagen stehen.
     * Lagen-Wettkämpfe führen im Titel oft die Einzelstrecken auf
     * ("200 m Lagen (S-R-B-F)") – sonst gewinnt fälschlich Freistil.
     */
    private const DISCIPLINE_STEMS = [
        'lagen'         => 'L',
        'medley'        => 'L',
        'schmetterling' => 'S',
        'delphin'       => 'S',
        'butterfly'     => 'S',
        'brust'         => 'B',
        'breaststroke'  => 'B',
        'rücken'        => 'R',
        'ruecken'       => 'R',
        'backstroke'    => 'R',
        'freistil'      => 'F',
        'kraul'         => 'F',
        'freestyle'     => 'F',
        'frei'          => 'F',
    ];

    private ResultReconciler $reconciler;

    public function __construct(
        private WaScoringService $waScoring,
        ?ResultReconciler $reconciler = null
    ) {
        $this->reconciler = $reconciler ?? new ResultReconciler();
    }

    public function getSourceId(): string { return 'dsvdata'; }

    // ── Public entry point ──────────────────────────────────────────────────────

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        $years    = $this->relevantYears();
        $stateIds = Setting::getJson('crawler.dsvdata.state_ids', self::DEFAULT_STATE_IDS);
        $parser   = new PdfParser();

        Log::info('DsvDataCrawler: Zeitraum ' . reset($years) . '–' . end($years)
            . ' (' . count($years) . ' Jahre), StateIDs ' . implode(', ', $stateIds));

        foreach ($stateIds as $stateId) {
            foreach ($years as $year) {
                $meetIds = $this->fetchMeetIds($year, $stateId);
                Log::info("DsvDataCrawler: {$year}/StateID={$stateId} → " . count($meetIds) . ' Wettkämpfe');

                foreach ($meetIds as $meetId) {
                    try {
                        $outcome = $this->processMeet($meetId, $year, $parser);
                        $stats[$outcome]++;
                    } catch (\Throwable $e) {
                        Log::error("DsvDataCrawler: Fehler MeetID={$meetId}", ['error' => $e->getMessage()]);
                        $stats['errors']++;
                    }
                }
            }
        }

        return $stats;
    }

    // ── Meet-Listing ────────────────────────────────────────────────────────────

    /**
     * Zeitraum der Wettkampfsuche, konfigurierbar per Setting
     * 'crawler.dsvdata.lookback_years'. 1 = aktuelles Jahr + ein Jahr zurueck.
     *
     * Achtung: Jeder Lauf laedt auch fuer bereits importierte Wettkaempfe das PDF
     * erneut, damit nachtraeglich geloeschte Ergebnisse wieder auftauchen. Ein
     * grosser Wert verteuert deshalb JEDEN Lauf. Fuer einen einmaligen Neuaufbau
     * hochsetzen und danach wieder senken.
     */
    private function relevantYears(): array
    {
        $current  = (int) date('Y');
        $lookback = (int) Setting::getCached(
            'crawler.dsvdata.lookback_years',
            self::DEFAULT_LOOKBACK_YEARS
        );

        // Obergrenze, damit eine Fehleingabe nicht hunderte PDF-Downloads ausloest
        $lookback = max(0, min($lookback, self::MAX_LOOKBACK_YEARS));

        return range($current - $lookback, $current);
    }

    private function fetchMeetIds(int $year, int $stateId): array
    {
        $url = self::LIST_URL . "?MeetYear={$year}&StateID={$stateId}&Lang=de-DE";

        $response = $this->httpGet($url);
        if (!$response || $response->failed()) {
            Log::warning("DsvDataCrawler: Listing nicht abrufbar", ['url' => $url]);
            return [];
        }

        // Extrahiere alle MeetIDs aus Ergebnis-Links
        preg_match_all(
            '|/Modules/Results/Meet\.aspx\?MeetID=(\d+)|',
            $response->body(),
            $matches
        );

        return array_unique($matches[1] ?? []);
    }

    // ── Einzelner Wettkampf ─────────────────────────────────────────────────────

    private function processMeet(string $meetId, int $year, PdfParser $parser): string
    {
        $importHash  = 'dsvdata_' . $meetId;
        $existing    = Competition::where('import_hash', $importHash)->first();

        // PDF immer herunterladen und parsen — nur so können nachträglich
        // gelöschte oder fehlerhaft importierte Ergebnisse zuverlässig nachgezogen werden.
        // persistResults() hat eine eigene Duplikat-Prüfung pro Ergebnis.
        $pdfUrl  = self::FILE_URL . "?F=WKResults&File={$meetId}.pdf";
        $pdfData = $this->downloadPdf($pdfUrl);
        if (!$pdfData) {
            return 'skipped';
        }

        try {
            $pdf     = $parser->parseContent($pdfData);
            $rawText = $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning("DsvDataCrawler: PDF-Parsing fehlgeschlagen MeetID={$meetId}", ['error' => $e->getMessage()]);
            return 'errors';
        }

        $meetData   = $this->parsePdfText($rawText, $meetId, $year);
        $individual = $meetData['results']['individual'] ?? [];
        $relays     = $meetData['results']['relays']     ?? [];

        if (!$meetData || (empty($individual) && empty($relays))) {
            if (!$existing) {
                $this->logImport($meetId, $pdfUrl, null, 'skipped', 'Keine Ergebnisse im PDF erkannt');
            }
            return 'skipped';
        }

        // Competition erstellen (falls neu) oder vorhandene verwenden
        $competition  = $existing ?? $this->persistMeet($meetData, $pdfUrl, $importHash);
        $count        = $this->persistResults($competition, $individual);
        $relaysSynced = $this->persistRelayResults($competition, $relays);

        if ($count > 0 || $relaysSynced > 0) {
            $parts = [];
            if ($count > 0)        $parts[] = "{$count} eigene Ergebnisse";
            if ($relaysSynced > 0) $parts[] = "{$relaysSynced} eigene Staffeln";

            $this->logImport($meetId, $pdfUrl, $competition->id, 'success',
                ($existing ? 'Nachgezogen: ' : '') . implode(' · ', $parts) . ' importiert'
            );
            return 'imported';
        }

        return 'skipped';
    }

    // ── PDF-Parsing ─────────────────────────────────────────────────────────────

    /**
     * Parst den extrahierten Text eines EasyWk/Synactis-Protokoll-PDFs.
     *
     * Erwartete Struktur:
     *   WK 1  100 m Freistil  männlich  Jahrgang: 2008–2010
     *   Platz  Name                Jg.  Verein            Zeit      WA Pkt.
     *      1   Mustermann, Max      05  SC Test Kiel     57,34         534
     *
     * Die Textreihenfolge im PDF folgt der Render-Reihenfolge (oben links → rechts → unten).
     */
    private function parsePdfText(string $text, string $meetId, int $year): ?array
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== ''
        ));

        // Wettkampf-Header (erste Zeilen)
        $meet = $this->extractMeetHeader($lines, $year);
        if (!$meet) {
            Log::debug("DsvDataCrawler: Kein Wettkampf-Header erkannt MeetID={$meetId}", [
                'preview' => implode(' | ', array_slice($lines, 0, 10)),
            ]);
            return null;
        }

        // Ergebnisse extrahieren
        $meet['results'] = $this->extractResults($lines);

        return $meet;
    }

    private function extractMeetHeader(array $lines, int $year): ?array
    {
        // Wettkampfname: i.d.R. erste nicht-leere Zeile
        $name = $lines[0] ?? null;
        if (!$name || mb_strlen($name) < 5) return null;

        // Datum suchen: DD. MM. YYYY oder DD.MM.YYYY oder DD. - DD. MM. YYYY
        $date    = null;
        $dateEnd = null;
        foreach (array_slice($lines, 0, 10) as $line) {
            if (preg_match('/(\d{1,2})\.\s*(?:(?:–|-)\s*(\d{1,2})\.\s*)?(\d{1,2})\.\s*(\d{4})/', $line, $m)) {
                $date    = sprintf('%04d-%02d-%02d', $m[4], $m[3], $m[1]);
                $dateEnd = $m[2] ? sprintf('%04d-%02d-%02d', $m[4], $m[3], $m[2]) : $date;
                break;
            }
        }
        if (!$date) {
            // Fallback: Jahr aus Parameter + 01-01
            $date = $year . '-01-01';
        }

        // Ort und Bahnlänge
        $city   = '';
        $course = 'Kurzbahn';
        foreach (array_slice($lines, 0, 15) as $line) {
            if (preg_match('/^(.+?),\s*\d/u', $line, $m)) {
                $city = trim($m[1]);
            }
            if (preg_match('/50\s*[mM]|[Ll]angbahn|LCM/u', $line)) {
                $course = 'Langbahn';
            }
        }

        return [
            'name'     => $name,
            'date'     => $date,
            'date_end' => $dateEnd ?? $date,
            'city'     => $city,
            'course'   => $course,
            'results'  => [],
        ];
    }

    /**
     * Zerlegt den Protokolltext in Einzel- und Staffelergebnisse.
     *
     * Reales Format (verifiziert an DSV-Protokollen 2025, EasyWK/Synactis):
     *   Wettkampf 2 - 200m Schmetterling männlich
     *   Jahrgang 2017
     *   Platz Schwimmer(in)<TAB>Jg. Verein<TAB>Endzeit WA
     *   1. Leif Bennet Möller<TAB>2007 TSV RW Niebüll<TAB>02:27,35
     *   100m: 01:11,03 (01:11,03) | 200m: 02:27,35 (01:16,32)   ← Zwischenzeiten
     *
     * Staffeln – Platz UND Mannschaftsnummer sind beide "N.":
     *   Wettkampf 3 - 4x50m Lagen mixed
     *   1. 1. Mannschaft<TAB>MTV von 1860 e.V. Heide 02:20,60
     *   Mia Rehse<TAB>2011 (W)50m: 00:35,26 (00:35,26)          ← Staffelmitglied
     *
     * @return array{individual: list<array>, relays: list<array>}
     */
    private function extractResults(array $lines): array
    {
        $individual   = [];
        $relays       = [];
        $currentEvent = null;
        $ageGroup     = null;
        $relayIndex   = null;

        foreach ($lines as $line) {
            // Wettkampf-Überschrift
            $event = $this->detectEventHeader($line);
            if ($event !== null) {
                $currentEvent = $event;
                $ageGroup     = null;
                $relayIndex   = null;
                continue;
            }

            // Sieht aus wie eine Überschrift, liess sich aber nicht klassifizieren:
            // currentEvent bewusst verwerfen. Sonst landen die folgenden Zeilen beim
            // VORHERIGEN Wettkampf – die Ursache vertauschter Lagen/Freistil-Zuordnungen.
            if ($this->looksLikeEventHeader($line)) {
                $currentEvent = null;
                $relayIndex   = null;
                continue;
            }

            if ($currentEvent === null) continue;

            // Wertungsgruppe ("Jahrgang 2017", "Offene Wertung", "AK 45")
            $group = $this->detectAgeGroup($line);
            if ($group !== null) {
                $ageGroup   = $group;
                $relayIndex = null;
                continue;
            }

            // Tabellenkopf und reine Zwischenzeit-Zeilen tragen keine Ergebnisse
            if ($this->isTableHeader($line) || $this->isSplitTimeLine($line)) continue;

            if ($currentEvent['relay_legs'] !== null) {
                $relay = $this->detectRelayRow($line, $currentEvent, $ageGroup);
                if ($relay !== null) {
                    $relays[]   = $relay;
                    $relayIndex = count($relays) - 1;
                    continue;
                }
                // Folgezeilen einer Staffel sind ihre Schwimmer
                if ($relayIndex !== null) {
                    $member = $this->detectRelayMemberRow($line, $currentEvent);
                    if ($member !== null) $relays[$relayIndex]['members'][] = $member;
                }
                continue;
            }

            $result = $this->detectResultRow($line, $currentEvent, $ageGroup);
            if ($result !== null) $individual[] = $result;
        }

        return ['individual' => $individual, 'relays' => $relays];
    }

    /**
     * "Wettkampf 3 - 4x50m Lagen mixed" / "Wettkampf 2 - 200m Schmetterling männlich"
     * distance ist die Strecke PRO BAHN, relay_legs die Anzahl der Wiederholungen.
     */
    private function detectEventHeader(string $line): ?array
    {
        if (!preg_match(
            '/^\s*(?:Wettkampf|WK)\s*(\d+)\s*[-–—]\s*(?:(\d+)\s*[x×]\s*)?(\d{2,4})\s*m\b\s*(.*)$/iu',
            $line, $m
        )) {
            return null;
        }

        $rest       = trim($m[4]);
        $discipline = $this->disciplineFromText($rest);
        $distance   = (int) $m[3];
        $legs       = ($m[2] !== '') ? (int) $m[2] : null;

        if (!$discipline || $distance <= 0) return null;
        if ($legs !== null && $legs < 2)    return null;

        return [
            'event_number' => (int) $m[1],
            'discipline'   => $discipline,
            'distance'     => $distance,
            'relay_legs'   => $legs,
            'gender'       => $this->genderFromText($rest),
        ];
    }

    private function looksLikeEventHeader(string $line): bool
    {
        return preg_match('/^\s*(?:Wettkampf|WK)\s*\d+\s*[-–—]/iu', $line) === 1;
    }

    /** "Jahrgang 2017", "Jahrgang 2009-2011", "AK 45", "Offene Wertung" */
    private function detectAgeGroup(string $line): ?string
    {
        $t = trim($line);
        if (preg_match('/^Jahrgang\s*:?\s*(\d{4}(?:\s*[-–]\s*\d{4})?)$/iu', $t, $m)) {
            return 'JG ' . preg_replace('/\s+/', '', $m[1]);
        }
        if (preg_match('/^(AK\s*\d+)$/iu', $t, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }
        if (preg_match('/^Offene\s+Wertung$/iu', $t)) return 'Offen';
        return null;
    }

    private function isTableHeader(string $line): bool
    {
        return preg_match('/^\s*Platz\b/iu', $line) === 1;
    }

    /** "100m: 01:11,03 (01:11,03) | 200m: 02:27,35 (01:16,32)" */
    private function isSplitTimeLine(string $line): bool
    {
        return preg_match('/^\s*\d{2,4}\s*m\s*:/iu', $line) === 1;
    }

    private function disciplineFromText(string $text): ?string
    {
        $t = mb_strtolower($text);
        foreach (self::DISCIPLINE_STEMS as $stem => $code) {
            if (str_contains($t, $stem)) return $code;
        }
        return null;
    }

    /** 'X' steht – wie in DSV7 – für gemischte Wertung (mixed). */
    private function genderFromText(string $line): string
    {
        if (preg_match('/mixed|gemischt/iu', $line)) return 'X';
        // Weiblich zuerst: "female" enthält "male".
        if (preg_match('/weiblich|frauen|mädchen|maedchen|female/iu', $line)) return 'F';
        if (preg_match('/männlich|maennlich|männer|maenner|jungen|male/iu', $line)) return 'M';
        return 'X';
    }

    /**
     * "1. Leif Bennet Möller<TAB>2007 TSV RW Niebüll<TAB>02:27,35"
     * Masters kleben Altersklasse und Verein zusammen: "1980/AK 45SV Wiking Kiel".
     * Optionale WA-Punkte am Zeilenende.
     */
    private function detectResultRow(string $line, array $event, ?string $ageGroup): ?array
    {
        if (!preg_match(
            '/^\s*(\d{1,3})\.\s+(.+?)[\s\t]+(\d{4})(?:\s*\/\s*AK\s*\d+)?\s*(.+?)[\s\t]+'
            . '(\d{1,2}:\d{2}[,.]\d{2})(?:\s+\d+)?\s*$/u',
            $line, $m
        )) {
            return null;
        }

        $timeMs  = $this->parseTimeString($m[5]);
        $rawName = trim($m[2]);
        if ($timeMs <= 0 || $rawName === '') return null;

        [$firstname, $lastname] = $this->splitName($rawName);
        if (!$firstname || !$lastname) return null;

        return [
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'place'      => (int) $m[1],
            'birth_year' => (int) $m[3],
            'club'       => trim($m[4]),
            'time_ms'    => $timeMs,
            'discipline' => $event['discipline'],
            'distance'   => $event['distance'],
            'gender'     => $event['gender'],
            'age_group'  => $ageGroup,
        ];
    }

    /** "1. 1. Mannschaft<TAB>MTV von 1860 e.V. Heide 02:20,60" */
    private function detectRelayRow(string $line, array $event, ?string $ageGroup): ?array
    {
        if (!preg_match(
            '/^\s*(\d{1,3})\.\s+(.+?)[\s\t]+(\d{1,2}:\d{2}[,.]\d{2})(?:\s+\d+)?\s*$/u',
            $line, $m
        )) {
            return null;
        }

        $timeMs = $this->parseTimeString($m[3]);
        if ($timeMs <= 0) return null;

        // Mannschaftsbezeichnung ("1. Mannschaft") vom Vereinsnamen trennen
        $teamRaw  = trim($m[2]);
        $clubName = trim(preg_replace('/^\d+\.\s*Mannschaft\s*/iu', '', $teamRaw));
        if ($clubName === '') $clubName = $teamRaw;

        return [
            'place'      => (int) $m[1],
            'club'       => $clubName,
            'team_raw'   => $teamRaw,
            'time_ms'    => $timeMs,
            'discipline' => $event['discipline'],
            'distance'   => $event['distance'],
            'relay_legs' => $event['relay_legs'],
            'gender'     => $event['gender'],
            'age_group'  => $ageGroup,
            'members'    => [],
        ];
    }

    /**
     * "Mia Rehse<TAB>2011 (W)50m: 00:35,26 (00:35,26)"
     * Die Streckenangabe ist kumuliert – daraus ergibt sich die Bahnnummer.
     */
    private function detectRelayMemberRow(string $line, array $event): ?array
    {
        if (!preg_match(
            '/^\s*(.+?)[\s\t]+(\d{4})\s*\(([MWmw])\)\s*(\d{2,4})\s*m\s*:/u',
            $line, $m
        )) {
            return null;
        }

        $rawName = trim($m[1]);
        if ($rawName === '') return null;

        [$firstname, $lastname] = $this->splitName($rawName);
        if (!$firstname || !$lastname) return null;

        $legDistance = max(1, (int) $event['distance']);
        $leg         = (int) round((int) $m[4] / $legDistance);

        return [
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'birth_year' => (int) $m[2],
            'gender'     => strtoupper($m[3]) === 'W' ? 'F' : 'M',
            'leg'        => max(1, $leg),
        ];
    }

    // ── DB-Persistenz ───────────────────────────────────────────────────────────

    private function persistMeet(array $meetData, string $pdfUrl, string $importHash): Competition
    {
        return Competition::firstOrCreate(
            ['name' => $meetData['name'], 'date' => $meetData['date']],
            [
                'location'    => $meetData['city'],
                'course'      => $meetData['course'],
                'type'        => 'regional',
                'date_end'    => $meetData['date_end'],
                'source_url'  => $pdfUrl,
                'import_hash' => $importHash,
            ]
        );
    }

    private function persistResults(Competition $competition, array $results): int
    {
        $swimmers   = User::where('role', 'schwimmer')->where('active', true)->get();
        $poolLength = $this->waScoring->poolLengthFromCourse($competition->course ?? '');
        $waYear     = $this->waScoring->latestYear($poolLength) ?? (int) substr($competition->date, 0, 4);

        $count = 0;
        foreach ($results as $result) {
            $userId = $this->matchSwimmer($result, $swimmers);
            if (!$userId) continue;

            // Lagen unter 100 m gibt es als Einzelstrecke nicht – das sind
            // Abschnitte einer Lagenstaffel und gehoeren nach relay_results.
            if ($result['discipline'] === 'L' && $result['distance'] < 100) continue;

            $existing = CompetitionResult::where([
                'competition_id' => $competition->id,
                'user_id'        => $userId,
                'discipline'     => $result['discipline'],
                'distance'       => $result['distance'],
            ])->first();

            // Schon vorhanden: nicht still ueberspringen, sondern gegen die
            // Meldung dieser Quelle abgleichen.
            if ($existing) {
                $this->reconciler->reconcile($existing, [
                    'discipline' => $result['discipline'],
                    'distance'   => $result['distance'],
                    'time_ms'    => $result['time_ms'],
                    'placement'  => $result['place'] ?? null,
                    'age_group'  => $result['age_group'] ?? null,
                ], $this->getSourceId());
                continue;
            }

            $isPb = false;
            if ($result['time_ms'] > 0) {
                $best = CompetitionResult::where('user_id', $userId)
                    ->where('discipline', $result['discipline'])
                    ->where('distance', $result['distance'])
                    ->where('time_ms', '>', 0)
                    ->min('time_ms');
                $isPb = !$best || $result['time_ms'] < $best;
            }

            $gender   = ($result['gender'] !== 'X') ? $result['gender'] : null;
            $waPoints = null;
            $usedYear = null;
            if ($waYear && $gender && $result['time_ms'] > 0) {
                $waPoints = $this->waScoring->calculatePoints(
                    $result['discipline'], $result['distance'], $gender,
                    $result['time_ms'], $waYear, $poolLength
                );
                $usedYear = $waPoints !== null ? $waYear : null;
            }

            CompetitionResult::create([
                'competition_id'   => $competition->id,
                'user_id'          => $userId,
                'source'           => $this->getSourceId(),
                'discipline'       => $result['discipline'],
                'distance'         => $result['distance'],
                'time_ms'          => $result['time_ms'],
                'placement'        => $result['place'] ?? null,
                'is_personal_best' => $isPb,
                'gender'           => $gender,
                'age_group'        => $result['age_group'] ?? null,
                'is_final'         => true,
                'wa_points'        => $waPoints,
                'wa_table_year'    => $usedYear,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Speichert Staffelergebnisse des eigenen Vereins.
     * Konvention wie im DSV7-Pfad: distance = Strecke pro Bahn, 'L' = Lagenstaffel,
     * Geschlecht 'X' (mixed) wird als NULL abgelegt.
     */
    private function persistRelayResults(Competition $competition, array $relays): int
    {
        if (empty($relays)) return 0;

        $ownClubNames = Setting::getJson('crawler.own_club_names', self::DEFAULT_OWN_CLUB_NAMES);

        $existingKeys = RelayResult::where('competition_id', $competition->id)
            ->get(['discipline', 'distance', 'club_name', 'time_ms'])
            ->mapWithKeys(fn($r) => ["{$r->discipline}_{$r->distance}_{$r->club_name}_{$r->time_ms}" => true]);

        $count = 0;
        foreach ($relays as $relay) {
            if (!$this->isOwnClub($relay['club'] ?? '', $ownClubNames)) continue;

            $key = "{$relay['discipline']}_{$relay['distance']}_{$relay['club']}_{$relay['time_ms']}";
            if (isset($existingKeys[$key])) continue;
            $existingKeys[$key] = true;

            $gender = $relay['gender'] ?? null;
            if ($gender === 'X') $gender = null;

            RelayResult::create([
                'competition_id' => $competition->id,
                'discipline'     => $relay['discipline'],
                'distance'       => $relay['distance'],
                'club_name'      => $relay['club'],
                'time_ms'        => $relay['time_ms'],
                'placement'      => $relay['place'] ?? null,
                'age_group'      => $relay['age_group'] ?? null,
                'gender'         => $gender,
                'status'         => 'OK',
            ]);
            $count++;
        }

        return $count;
    }

    private function isOwnClub(string $clubName, array $ownClubNames): bool
    {
        foreach ($ownClubNames as $own) {
            $own = trim((string) $own);
            if ($own !== '' && mb_stripos($clubName, $own) !== false) return true;
        }
        return false;
    }

    private function matchSwimmer(array $result, \Illuminate\Support\Collection $swimmers): ?int
    {
        $first = mb_strtolower(trim($result['firstname']));
        $last  = mb_strtolower(trim($result['lastname']));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->firstname)) === $first
                && mb_strtolower(trim($swimmer->lastname)) === $last) {
                return $swimmer->id;
            }
        }
        return null;
    }

    // ── Hilfsmethoden ───────────────────────────────────────────────────────────

    /** Namenszusätze gehören zum Nachnamen ("Lea von der Heide"). */
    private const NAME_PARTICLES = [
        'von', 'van', 'de', 'der', 'den', 'del', 'di', 'da', 'dos',
        'le', 'la', 'zu', 'zur', 'ten', 'ter',
    ];

    private function splitName(string $raw): array
    {
        $raw = trim($raw);

        // "Nachname, Vorname"
        if (str_contains($raw, ',')) {
            [$last, $first] = array_map('trim', explode(',', $raw, 2));
            return [$first, $last];
        }

        // "Vorname(n) Nachname" – das LETZTE Wort ist der Nachname.
        // Mehrteilige Vornamen sind hier die Regel ("Leif Bennet Möller",
        // "Estelle Milou Joost"), daher darf nicht nach dem ersten Wort getrennt werden.
        $parts = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) return ['', ''];

        $lastParts = [array_pop($parts)];
        while ($parts && in_array(mb_strtolower(end($parts)), self::NAME_PARTICLES, true)) {
            array_unshift($lastParts, array_pop($parts));
        }

        $firstname = implode(' ', $parts);
        if ($firstname === '') return ['', ''];

        return [$firstname, implode(' ', $lastParts)];
    }

    private function parseTimeString(string $time): int
    {
        $time = trim($time);
        // Komma → Punkt für einheitliche Verarbeitung
        $time = str_replace(',', '.', $time);

        if (preg_match('/^(\d+):(\d{2})\.(\d{2})$/', $time, $m)) {
            return (int)$m[1] * 60_000 + (int)$m[2] * 1_000 + (int)$m[3] * 10;
        }
        if (preg_match('/^(\d{1,2})\.(\d{2})$/', $time, $m)) {
            return (int)$m[1] * 1_000 + (int)$m[2] * 10;
        }
        return 0;
    }

    private function downloadPdf(string $url): ?string
    {
        $response = $this->httpGet($url);
        if (!$response || $response->failed()) return null;

        $content = $response->body();

        // Prüfen ob echtes PDF (Magic Bytes %PDF)
        if (!str_starts_with($content, '%PDF')) return null;

        return $content;
    }

    private function httpGet(string $url): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; WaRa-Portal-Crawler/1.0)',
                'Accept'          => 'text/html,application/pdf,*/*',
                'Accept-Language' => 'de-DE,de;q=0.9',
            ])->withOptions([
                'verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false),
            ])->timeout(45)->get($url);
        } catch (\Throwable $e) {
            Log::warning("DsvDataCrawler: HTTP-Fehler", ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function logImport(string $meetId, string $url, ?int $competitionId, string $status, string $message): void
    {
        ImportLog::create([
            'source'         => $this->getSourceId(),
            'source_url'     => $url,
            'filename'       => "MeetID={$meetId}",
            'status'         => $status,
            'competition_id' => $competitionId,
            'message'        => $message,
        ]);
    }
}
