<?php

namespace App\Services;

use App\Models\SwimmingTime;

/**
 * Parses record list files (XLSX, DOCX, CSV, PDF) into a preview array.
 *
 * Supported formats:
 *   xlsx — parsed via ZipArchive + XML (no Composer dependency)
 *   docx — parsed via ZipArchive + XML
 *   csv / txt — parsed via fgetcsv
 *   pdf  — best-effort text extraction from text-based PDFs
 *   xls / doc — requires phpoffice/phpspreadsheet; shows an error if missing
 */
class RecordImportService
{
    const DISCIPLINE_MAP = [
        'F' => ['frei', 'crawl', 'free', 'freestyle'],
        'B' => ['brust', 'breast'],
        'R' => ['rück', 'rueck', 'back'],
        'S' => ['schmetterling', 'butterfly', 'fly', 'delphin'],
        'L' => ['lagen', 'medley', 'mixed', 'im '],
    ];

    const STANDARD_DISTANCES = [25, 50, 100, 200, 400, 800, 1500];

    public function parse(string $path, string $defaultCourse = 'Langbahn'): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'])) {
            $structured = $this->parseCsvBlocks($path);
            if (!empty($structured)) return $structured;
            // Fallback: generic heuristic parser
            return $this->detectRecords($this->parseCsv($path), $defaultCourse);
        }

        $rows = match($ext) {
            'xlsx'       => $this->parseXlsx($path),
            'docx'       => $this->parseDocx($path),
            'pdf'        => $this->parsePdf($path),
            'xls', 'doc' => $this->parseLegacyOffice($path, $ext),
            default      => throw new \RuntimeException(
                "Format .$ext wird nicht unterstützt. Bitte als .xlsx oder .csv speichern."
            ),
        };

        return $this->detectRecords($rows, $defaultCourse);
    }

    // ── File readers ────────────────────────────────────────────────────────

    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('XLSX-Datei konnte nicht geöffnet werden.');
        }

        // Load shared strings (cell values referenced by index)
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)($r->t ?? '');
                    }
                    if ($text === '') $text = (string)($si->t ?? '');
                    $sharedStrings[] = $text;
                }
            }
        }

        // Find all sheet names
        $sheets = [];
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if ($wbXml) {
            $wb = simplexml_load_string($wbXml);
            if ($wb) {
                foreach ($wb->sheets->sheet ?? [] as $sheet) {
                    $sheets[] = (string)$sheet['name'];
                }
            }
        }

        // Read first sheet
        $rows  = [];
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet) {
            // Try rId-based name
            $sheet = $zip->getFromName('xl/worksheets/Sheet1.xml');
        }

        if ($sheet) {
            $sx = simplexml_load_string($sheet);
            if ($sx) {
                foreach ($sx->sheetData->row ?? [] as $row) {
                    $rowData = [];
                    foreach ($row->c as $cell) {
                        $type  = (string)($cell['t'] ?? '');
                        $value = (string)($cell->v ?? '');

                        if ($type === 's') {
                            // shared string
                            $value = $sharedStrings[(int)$value] ?? '';
                        } elseif ($type === 'str' || $type === 'inlineStr') {
                            $value = (string)($cell->is->t ?? $cell->v ?? '');
                        }
                        $rowData[] = $value;
                    }
                    $rows[] = implode("\t", $rowData);
                }
            }
        }

        $zip->close();
        return $rows;
    }

    private function parseDocx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('DOCX-Datei konnte nicht geöffnet werden.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            throw new \RuntimeException('word/document.xml nicht gefunden.');
        }

        $doc  = simplexml_load_string(
            preg_replace('/(<\/?)(\w+):/', '$1', $xml) // strip namespaces
        );

        $lines = [];
        // Each paragraph <p> becomes one line
        foreach ($doc->body->p ?? [] as $p) {
            $text = '';
            foreach ($p->r ?? [] as $r) {
                $text .= (string)($r->t ?? '');
            }
            // Also handle nested runs
            $text = strip_tags(str_replace(['</w:t>', '</t>'], ' ', $xml));
            $lines[] = trim($text);
            break; // fallback below
        }

        // Simpler fallback: strip all XML tags and split by newlines / table cells
        $plain = preg_replace('/<w:t[^>]*>/', "\x01", $xml);
        $plain = preg_replace('/<\/w:t>/', "\x02", $plain);
        $plain = strip_tags($plain);
        $lines = [];
        foreach (explode("\x02", $plain) as $segment) {
            $text = trim(str_replace("\x01", '', $segment));
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    private function parseCsv(string $path): array
    {
        $raw  = file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252') ?: $raw;
        }

        $lines = [];
        $handle = fopen('data://text/plain,' . urlencode($raw), 'r');
        if ($handle) {
            while (($fields = fgetcsv($handle, 0, ';')) !== false) {
                $lines[] = implode("\t", array_map('trim', $fields));
            }
            fclose($handle);
        }
        return $lines;
    }

    /**
     * Parses the structured block CSV format used by club record lists.
     *
     * Format:
     *   Line 1:  Listentyp (e.g. "Rekordliste, gesamt;")
     *   Block header: ";Weiblich, Kurzbahn, Offen;" (empty first field)
     *   Column header: "Strecke;Name;Jg;Gruppe;Zeit;Datum;Ort;Veranstaltung;World Aquatics;"
     *   Data rows:     "100 F;Max Mustermann;2000;Gruppe A;01:05,23;01.01.2024;Hamburg;Meisterschaft;650;"
     *   Multiple blocks per file are supported.
     *
     * Returns [] if the file does not match this format (fallback to heuristic parser).
     */
    private function parseCsvBlocks(string $path): array
    {
        $raw = file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252') ?: $raw;
        }

        $allRows = [];
        $handle  = fopen('data://text/plain;base64,' . base64_encode($raw), 'r');
        if ($handle) {
            while (($fields = fgetcsv($handle, 0, ';')) !== false) {
                $allRows[] = array_map('trim', $fields);
            }
            fclose($handle);
        }

        if (count($allRows) < 3) return [];

        // Structured format check:
        //   Row 0: non-empty first field (Listentyp)
        //   Row 1: empty first field + non-empty second field (first block header)
        if (($allRows[0][0] ?? '') === ''
            || ($allRows[1][0] ?? '') !== ''
            || ($allRows[1][1] ?? '') === '') {
            return [];
        }

        $records     = [];
        $gender      = null;
        $course      = 'Langbahn';
        $ageGroup    = null;
        $inDataBlock = false;

        foreach ($allRows as $idx => $fields) {
            if ($idx === 0) continue; // skip Listentyp

            $first  = $fields[0] ?? '';
            $second = $fields[1] ?? '';

            // Block header: empty first field, content in second
            if ($first === '' && $second !== '') {
                [$gender, $course, $ageGroup] = $this->parseBlockHeader($second);
                $inDataBlock = false;
                continue;
            }

            // Column header row
            if ($first === 'Strecke') {
                $inDataBlock = true;
                continue;
            }

            // Skip empty rows
            if (trim(implode('', $fields)) === '') continue;

            // Data row
            if (!$inDataBlock || !$gender) continue;

            [$distance, $discipline] = $this->parseStrecke($fields[0] ?? '');
            $timeMs = $this->parseTimeStr($fields[4] ?? '');

            $date     = null;
            $datumRaw = $fields[5] ?? '';
            if ($datumRaw && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $datumRaw, $dm)) {
                $date = "{$dm[3]}-{$dm[2]}-{$dm[1]}";
            }

            $records[] = [
                'discipline'   => $discipline,
                'distance'     => $distance,
                'gender'       => $gender,
                'age_group'    => $ageGroup,
                'course'       => $course,
                'swimmer_name' => $fields[1] ?? '',
                'time_ms'      => $timeMs,
                'time_str'     => $fields[4] ?? '',
                'set_date'     => $date,
                'location'     => ($fields[6] ?? '') ?: null,
                'raw_line'     => implode(';', $fields),
            ];
        }

        return $records;
    }

    /** Parses ";Weiblich, Kurzbahn, Offen;" block descriptor into [gender, course, age_group]. */
    private function parseBlockHeader(string $header): array
    {
        $gender   = null;
        $course   = 'Langbahn';
        $ageGroup = null;

        if (preg_match('/weiblich/iu', $header))              $gender = 'F';
        elseif (preg_match('/männlich|maennlich/iu', $header)) $gender = 'M';

        if (preg_match('/kurzbahn/iu', $header))    $course = 'Kurzbahn';
        elseif (preg_match('/langbahn/iu', $header)) $course = 'Langbahn';

        if (preg_match('/AK\s*(\d+)/i', $header, $m)) $ageGroup = 'AK' . $m[1];
        // "Offen" → $ageGroup stays null

        return [$gender, $course, $ageGroup];
    }

    /**
     * Parses the Strecke field (e.g. "100 F", "200 R", "25 DB") into [distance, discipline].
     * Returns [null, null] for unrecognised or non-standard codes.
     */
    private function parseStrecke(string $strecke): array
    {
        if (!preg_match('/^(\d+)\s+([A-ZÄÖÜ]+)$/iu', trim($strecke), $m)) {
            return [null, null];
        }

        $distance = (int)$m[1];
        $code     = strtoupper(trim($m[2]));

        // Standard discipline codes (German swimming federation notation)
        // Leg-only youth disciplines are mapped to the parent stroke
        $map = [
            'F'   => 'F',
            'FR'  => 'F',
            'KB'  => 'F',   // Kraul-Bein
            'B'   => 'B',
            'BR'  => 'B',
            'BB'  => 'B',   // Brust-Bein
            'R'   => 'R',
            'RU'  => 'R',
            'RB'  => 'R',   // Rücken-Bein
            'S'   => 'S',
            'SCH' => 'S',
            'DB'  => 'S',   // Delfin-Bein
            'L'   => 'L',
        ];

        $discipline = $map[$code] ?? null;
        if (!$discipline || !in_array($distance, [25, 50, 100, 200, 400, 800, 1500])) {
            return [null, null];
        }

        return [$distance, $discipline];
    }

    private function parsePdf(string $path): array
    {
        $raw = file_get_contents($path);

        // Extract text from PDF text streams (works for text-based, not scanned PDFs)
        $lines = [];

        // Method 1: BT...ET blocks (standard PDF text objects)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                // Extract strings in parentheses (PDF string literals)
                if (preg_match_all('/\(([^)]{2,})\)/', $block, $strings)) {
                    $line = implode(' ', array_map('trim', $strings[1]));
                    if (strlen($line) > 3) $lines[] = $line;
                }
            }
        }

        // Method 2: Fall back to extracting all readable strings
        if (empty($lines)) {
            $text    = preg_replace('/[^\x20-\x7E\xC0-\xFF\x0A\x0D]/', ' ', $raw);
            $chunks  = preg_split('/\s{3,}/', $text);
            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if (strlen($chunk) > 4) $lines[] = $chunk;
            }
        }

        return $lines;
    }

    private function parseLegacyOffice(string $path, string $ext): array
    {
        // Try phpoffice/phpspreadsheet if installed
        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $rows  = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $cells[] = (string)$cell->getFormattedValue();
                    }
                    $rows[] = implode("\t", $cells);
                }
                return $rows;
            } catch (\Exception $e) {
                throw new \RuntimeException("Fehler beim Lesen der Datei: " . $e->getMessage());
            }
        }

        throw new \RuntimeException(
            ".$ext-Dateien werden ohne phpoffice/phpspreadsheet nicht unterstützt. " .
            "Bitte speichere die Datei als .xlsx oder .csv und lade sie erneut hoch."
        );
    }

    // ── Record detector ─────────────────────────────────────────────────────

    private function detectRecords(array $lines, string $defaultCourse): array
    {
        $records          = [];
        $contextGender    = null;
        $contextDiscipline= null;
        $contextAgeGroup  = null;

        foreach ($lines as $rawLine) {
            // Flatten multi-value lines (tab-separated from spreadsheet rows)
            $line = trim($rawLine);
            if ($line === '') continue;

            // --- Detect context headers (lines without a time) ---
            $hasTime = (bool)preg_match('/\b(\d{1,2}:)?(\d{1,2})[,.](\d{2})\b/', $line);

            if (!$hasTime) {
                // Gender context
                if (preg_match('/\b(männer|männlich|herren|male|men)\b/iu', $line)) {
                    $contextGender = 'M';
                } elseif (preg_match('/\b(frauen|weiblich|damen|female|women)\b/iu', $line)) {
                    $contextGender = 'F';
                }
                // Age group context
                if (preg_match('/\b(AK\s*\d+|Altersklasse\s*\d+)\b/i', $line, $m)) {
                    $contextAgeGroup = strtoupper(preg_replace('/\s+/', '', $m[1]));
                }
                // Discipline context (standalone header)
                foreach (self::DISCIPLINE_MAP as $disc => $keywords) {
                    foreach ($keywords as $kw) {
                        if (mb_stripos($line, $kw) !== false) {
                            $contextDiscipline = $disc;
                            break 2;
                        }
                    }
                }
                continue;
            }

            // --- Parse a data line ---
            $row = $this->extractRow($line, $defaultCourse);

            // Fill from context if not detected in line
            if (!$row['gender'] && $contextGender)       $row['gender']     = $contextGender;
            if (!$row['age_group'] && $contextAgeGroup)  $row['age_group']  = $contextAgeGroup;
            if (!$row['discipline'] && $contextDiscipline) $row['discipline'] = $contextDiscipline;

            // Only include if we have at minimum time + name
            if ($row['time_ms'] > 0 && $row['swimmer_name'] !== '') {
                $records[] = $row;
            }
        }

        return $records;
    }

    private function extractRow(string $line, string $defaultCourse): array
    {
        $working = $line;

        // Extract time
        $timeMs  = 0;
        $timeStr = '';
        if (preg_match('/\b(\d{1,2}:)?(\d{1,2})[,.](\d{2})\b/', $working, $tm)) {
            $timeStr = $tm[0];
            $timeMs  = $this->parseTimeStr($timeStr);
            $working = str_replace($timeStr, '', $working);
        }

        // Extract date
        $date = null;
        if (preg_match('/\b(\d{2})\.(\d{2})\.(\d{4})\b/', $working, $dm)) {
            $date    = "{$dm[3]}-{$dm[2]}-{$dm[1]}";
            $working = str_replace($dm[0], '', $working);
        }

        // Extract distance (numbers near "m")
        $distance = null;
        if (preg_match('/\b(1500|800|400|200|100|50|25)\s*m?\b/i', $working, $distm)) {
            $distance = (int)$distm[1];
            $working  = preg_replace('/\b' . $distm[1] . '\s*m?\b/i', '', $working, 1);
        }

        // Extract discipline
        $discipline = null;
        foreach (self::DISCIPLINE_MAP as $disc => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_stripos($working, $kw) !== false) {
                    $discipline = $disc;
                    $working    = preg_replace('/' . preg_quote($kw, '/') . '/iu', '', $working, 1);
                    break 2;
                }
            }
        }

        // Extract gender
        $gender = null;
        if (preg_match('/\b(M|Männlich|Männer|Herren|Male)\b/iu', $working, $gm)) {
            $gender  = 'M';
            $working = str_replace($gm[0], '', $working);
        } elseif (preg_match('/\b(W|F|Weiblich|Frauen|Damen|Female)\b/iu', $working, $gm)) {
            $gender  = 'F';
            $working = str_replace($gm[0], '', $working);
        }

        // Extract age group
        $ageGroup = null;
        if (preg_match('/\bAK\s*(\d+)\b/i', $working, $akm)) {
            $ageGroup = 'AK' . $akm[1];
            $working  = str_replace($akm[0], '', $working);
        }

        // Remaining text = name (clean up whitespace, separators, numbers)
        $name = preg_replace('/[\t|;]+/', ' ', $working);
        $name = preg_replace('/\s{2,}/', ' ', trim($name));
        // Remove single-digit or short tokens that are clearly not names
        $name = implode(' ', array_filter(
            explode(' ', $name),
            fn($t) => strlen(trim($t, '.,;:-')) > 1
        ));
        $name = trim($name);

        return [
            'discipline'   => $discipline,
            'distance'     => $distance,
            'gender'       => $gender,
            'age_group'    => $ageGroup,
            'course'       => $defaultCourse,
            'swimmer_name' => $name,
            'time_ms'      => $timeMs,
            'time_str'     => $timeStr,
            'set_date'     => $date,
            'location'     => null,
            'raw_line'     => $line,
        ];
    }

    private function parseTimeStr(string $time): int
    {
        $time  = str_replace(',', '.', trim($time));
        $parts = explode(':', $time);
        return count($parts) === 2
            ? (int)$parts[0] * 60000 + (int)round(floatval($parts[1]) * 1000)
            : (int)round(floatval($parts[0]) * 1000);
    }
}
