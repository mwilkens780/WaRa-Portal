<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Models\User;
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

    public function __construct(private WaScoringService $waScoring) {}

    public function getSourceId(): string { return 'dsvdata'; }

    // ── Public entry point ──────────────────────────────────────────────────────

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        $years    = $this->relevantYears();
        $stateIds = Setting::getJson('crawler.dsvdata.state_ids', self::DEFAULT_STATE_IDS);
        $parser   = new PdfParser();

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

    private function relevantYears(): array
    {
        $current = (int) date('Y');
        return [$current - 1, $current];
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

        $meetData = $this->parsePdfText($rawText, $meetId, $year);
        if (!$meetData || empty($meetData['results'])) {
            if (!$existing) {
                $this->logImport($meetId, $pdfUrl, null, 'skipped', 'Keine Ergebnisse im PDF erkannt');
            }
            return 'skipped';
        }

        // Competition erstellen (falls neu) oder vorhandene verwenden
        $competition = $existing ?? $this->persistMeet($meetData, $pdfUrl, $importHash);
        $count       = $this->persistResults($competition, $meetData['results']);

        if ($count > 0) {
            $this->logImport($meetId, $pdfUrl, $competition->id, 'success',
                ($existing ? 'Nachgezogen: ' : '') . "{$count} eigene Ergebnisse importiert"
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

    private function extractResults(array $lines): array
    {
        $results         = [];
        $currentEvent    = null;
        $eventHasResults = false;

        foreach ($lines as $line) {
            // 1) Ergebniszeilen haben Vorrang – sie dürfen nie als Header gelesen werden.
            if ($currentEvent !== null) {
                $result = $this->detectResultRow($line, $currentEvent);
                if ($result) {
                    $results[]       = $result;
                    $eventHasResults = true;
                    continue;
                }
            }

            // 2) Überschrift erkannt. Kann sie nicht eindeutig klassifiziert werden
            //    (Staffel, unbekannte Disziplin), wird currentEvent bewusst auf null
            //    gesetzt. Sonst würden die folgenden Ergebnisse dem VORHERIGEN
            //    Wettkampf zugeordnet – die Ursache für vertauschte Lagen/Freistil-Zeiten.
            if ($this->looksLikeEventHeader($line)) {
                $currentEvent    = $this->detectEventHeader($line);
                $eventHasResults = false;
                continue;
            }

            // 3) Das Geschlecht steht oft erst in der Folgezeile der Überschrift
            //    ("WK 12  200 m Lagen" / "weiblich  Jahrgang: 2009-2011").
            //    Nur zwischen Überschrift und erstem Ergebnis nachtragen, damit
            //    spätere Zeilen (Vereinsnamen, Fußzeilen) nichts überschreiben.
            if ($currentEvent !== null && !$eventHasResults && $currentEvent['gender'] === 'X') {
                $gender = $this->genderFromText($line);
                if ($gender !== 'X') $currentEvent['gender'] = $gender;
            }
        }

        return $results;
    }

    /**
     * Erkennt, ob eine Zeile eine Wettkampf-Überschrift ankündigt – unabhängig
     * davon, ob sie anschließend klassifiziert werden kann.
     */
    private function looksLikeEventHeader(string $line): bool
    {
        if ($this->parseDistance($line) === null) return false;

        // Staffeln gelten als Überschrift (und führen zum Reset).
        if ($this->isRelay($line)) return true;

        // "WK 12 ..." / "Wettkampf 12 ..." am Zeilenanfang
        if (preg_match('/^\s*(WK|Wettkampf)\s*\d+/iu', $line)) return true;

        return $this->disciplineFromText($line) !== null;
    }

    private function detectEventHeader(string $line): ?array
    {
        // Staffeln liefern keine Einzelergebnisse – bewusst verwerfen.
        if ($this->isRelay($line)) return null;

        $distance   = $this->parseDistance($line);
        $discipline = $this->disciplineFromText($line);

        if (!$discipline || $distance === null) return null;

        return [
            'discipline' => $discipline,
            'distance'   => $distance,
            'gender'     => $this->genderFromText($line),
        ];
    }

    /**
     * Liest die Streckenangabe ("100 m", "100m", "1500 m").
     * Das 'm' muss von Trennzeichen oder Zeilenende gefolgt sein, damit
     * Vereinsnamen wie "SV 1900 München" nicht als Strecke gelesen werden.
     * Geschützte Leerzeichen (U+00A0) kommen in PDF-Extrakten häufig vor.
     */
    private function parseDistance(string $line): ?int
    {
        if (!preg_match('/\b(\d{2,4})[\s\x{00A0}]*m(?=[\s\x{00A0},.:;]|$)/iu', $line, $m)) {
            return null;
        }
        $distance = (int) $m[1];
        return $distance > 0 ? $distance : null;
    }

    private function isRelay(string $line): bool
    {
        return mb_stripos($line, 'staffel') !== false
            || preg_match('/\b\d\s*[x×]\s*\d+/iu', $line) === 1;
    }

    private function disciplineFromText(string $text): ?string
    {
        $t = mb_strtolower($text);
        foreach (self::DISCIPLINE_STEMS as $stem => $code) {
            if (str_contains($t, $stem)) return $code;
        }
        return null;
    }

    private function genderFromText(string $line): string
    {
        // Weiblich zuerst: "female" enthält "male".
        if (preg_match('/weiblich|frauen|mädchen|maedchen|female/iu', $line)) return 'F';
        if (preg_match('/männlich|maennlich|männer|maenner|jungen|male/iu', $line)) return 'M';
        return 'X';
    }

    private function detectResultRow(string $line, array $event): ?array
    {
        // Zeitformat: M:SS,cc oder SS,cc (deutsches Format mit Komma)
        // Platz am Anfang der Zeile, dann Name, Jahrgang, Verein, Zeit
        $timePattern = '(\d{1,2}:\d{2}[,\.]\d{2}|\d{2}[,\.]\d{2})';

        if (!preg_match('/^(\d{1,3})\s+(.+?)\s+(\d{2})\s+(.+?)\s+' . $timePattern . '/u', $line, $m)) {
            return null;
        }

        $place    = (int) $m[1];
        $rawName  = trim($m[2]);
        $timeMs   = $this->parseTimeString($m[5]);

        if ($timeMs <= 0 || !$rawName) return null;

        // Name-Format: "Nachname, Vorname" oder "Vorname Nachname"
        [$firstname, $lastname] = $this->splitName($rawName);
        if (!$firstname || !$lastname) return null;

        return [
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'place'      => $place,
            'time_ms'    => $timeMs,
            'discipline' => $event['discipline'],
            'distance'   => $event['distance'],
            'gender'     => $event['gender'],
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

            $exists = CompetitionResult::where([
                'competition_id' => $competition->id,
                'user_id'        => $userId,
                'discipline'     => $result['discipline'],
                'distance'       => $result['distance'],
            ])->exists();
            if ($exists) continue;

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
                'discipline'       => $result['discipline'],
                'distance'         => $result['distance'],
                'time_ms'          => $result['time_ms'],
                'placement'        => $result['place'] ?? null,
                'is_personal_best' => $isPb,
                'gender'           => $gender,
                'is_final'         => true,
                'wa_points'        => $waPoints,
                'wa_table_year'    => $usedYear,
            ]);

            $count++;
        }

        return $count;
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

    private function splitName(string $raw): array
    {
        $raw = trim($raw);
        if (str_contains($raw, ',')) {
            [$last, $first] = array_map('trim', explode(',', $raw, 2));
            return [$first, $last];
        }
        // "Vorname Nachname" – letztes Wort = Nachname
        $parts = preg_split('/\s+/', $raw, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
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
