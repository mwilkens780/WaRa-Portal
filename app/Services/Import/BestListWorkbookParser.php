<?php

namespace App\Services\Import;

use App\Models\Record;
use App\Services\WaScoringService;

/**
 * Liest die Vereins-Bestenlisten aus der Excel-Vorlage des Vereins.
 *
 * Aufbau der Datei (je Blatt eine Bahn):
 *   Kopfzeile   "50m Bahn - offene Klasse - Weiblich (Stand nach Saison 2025/2026)"
 *   Streckenzeile  A:"50m Freistil"   H:"100m Freistil"   O:"200m Freistil"
 *   Datenzeilen    A:Platz B:Name C:Jahrgang(2-stellig) D:Zeit F:Jahr
 *                  (dasselbe ab H und ab O - drei Strecken nebeneinander)
 *
 * Die Plaetze in der Datei sind teilweise fehlerhaft (z.B. zweimal "7." mit
 * unterschiedlichen Zeiten) und werden deshalb nicht uebernommen - das Portal
 * bildet die Reihenfolge selbst aus den Zeiten.
 */
class BestListWorkbookParser
{
    /** Spaltenabstand innerhalb eines Blocks, ausgehend von der Platz-Spalte */
    private const OFFSET_NAME = 1;
    private const OFFSET_YEAR_OF_BIRTH = 2;
    private const OFFSET_TIME = 3;
    private const OFFSET_YEAR = 5;

    private const DISCIPLINES = [
        'freistil'      => 'F',
        'brust'         => 'B',
        'rücken'        => 'R',
        'ruecken'       => 'R',
        'schmetterling' => 'S',
        'lagen'         => 'L',
        'delfin'        => 'S',
    ];

    /**
     * @return array{entries: array<int, array>, warnings: array<int, string>, sheets: array<int, string>}
     */
    public function parse(string $path, ?string $sheetName = null): array
    {
        $reader  = new HallPlanReader($path);
        $sheets  = array_map(fn($s) => $s['name'], $reader->sheetNames());
        $entries = [];
        $warnings = [];

        foreach ($sheetName ? [$sheetName] : $sheets as $name) {
            $data = $reader->read($name);
            $this->parseSheet($data['cells'], $data['sheet'], $entries, $warnings);
        }

        return ['entries' => $entries, 'warnings' => $warnings, 'sheets' => $sheets];
    }

    private function parseSheet(array $cells, string $sheet, array &$entries, array &$warnings): void
    {
        ksort($cells);
        $course = $this->courseFromSheetName($sheet);
        $gender = null;
        $blocks = [];   // Platz-Spalte => ['discipline' => …, 'distance' => …]

        foreach ($cells as $row => $cols) {
            ksort($cols);
            $values = [];
            foreach ($cols as $col => $cell) {
                $values[$col] = trim((string) ($cell['v'] ?? ''));
            }

            // Kopfzeile: Bahn und Geschlecht
            foreach ($values as $v) {
                if ($v === '') continue;
                if (preg_match('/(\d{2})\s*m\s*Bahn/iu', $v, $m)) {
                    $course = ((int) $m[1]) === 25 ? 'Kurzbahn' : 'Langbahn';
                }
                if (preg_match('/\b(weiblich|m(ä|ae)nnlich)\b/iu', $v, $m)) {
                    $gender = str_starts_with(mb_strtolower($m[1]), 'w') ? 'F' : 'M';
                }
            }

            // Streckenzeile: startet neue Bloecke
            $newBlocks = [];
            foreach ($values as $col => $v) {
                if ($v === '' || !preg_match('/^([\d\.]+)\s*m\s+(\p{L}+)$/u', $v, $m)) continue;
                $discipline = self::DISCIPLINES[mb_strtolower($m[2])] ?? null;
                if (!$discipline) {
                    $warnings[] = "Unbekannte Disziplin „{$v}“ (Blatt {$sheet}, Zeile {$row}) – übersprungen.";
                    continue;
                }
                $newBlocks[$col] = [
                    'discipline' => $discipline,
                    'distance'   => (int) str_replace('.', '', $m[1]),   // "1.500m" → 1500
                ];
            }
            if ($newBlocks) {
                $blocks = $newBlocks;
                continue;
            }

            if (!$blocks) continue;

            // Datenzeilen der aktuellen Bloecke
            foreach ($blocks as $startCol => $block) {
                $name = $values[$this->shift($startCol, self::OFFSET_NAME)] ?? '';
                $time = $values[$this->shift($startCol, self::OFFSET_TIME)] ?? '';
                if ($name === '' && $time === '') continue;

                $timeMs = $time === '' ? 0 : (int) (WaScoringService::parseTimeInput($time) ?? 0);
                if ($name === '' || $timeMs <= 0) {
                    $warnings[] = "Zeile {$row}, Spalte {$startCol} ({$block['distance']} m "
                        . "{$block['discipline']}): unvollständig (Name „{$name}“, Zeit „{$time}“) – übersprungen.";
                    continue;
                }

                if (!$course || !$gender) {
                    $warnings[] = "Zeile {$row}: Bahn oder Geschlecht unbekannt – übersprungen.";
                    continue;
                }

                if (!Record::isVrEvent($block['discipline'], $block['distance'], $course)) {
                    $warnings[] = "{$block['distance']} m {$block['discipline']} ({$course}) gehört nicht zur "
                        . "Streckenliste – Zeile {$row} übersprungen.";
                    continue;
                }

                $entries[] = [
                    'course'       => $course,
                    'gender'       => $gender,
                    'discipline'   => $block['discipline'],
                    'distance'     => $block['distance'],
                    'swimmer_name' => $name,
                    'birth_year'   => $this->birthYear($values[$this->shift($startCol, self::OFFSET_YEAR_OF_BIRTH)] ?? ''),
                    'time_ms'      => $timeMs,
                    'set_year'     => $this->year($values[$this->shift($startCol, self::OFFSET_YEAR)] ?? ''),
                    'sheet'        => $sheet,
                    'row'          => $row,
                ];
            }
        }
    }

    /** "Langbahn"/"Kurzbahn" aus dem Blattnamen, falls die Kopfzeile fehlt */
    private function courseFromSheetName(string $sheet): ?string
    {
        $s = mb_strtolower($sheet);
        if (str_contains($s, 'kurzbahn') || str_contains($s, '25')) return 'Kurzbahn';
        if (str_contains($s, 'langbahn') || str_contains($s, '50')) return 'Langbahn';
        return null;
    }

    /**
     * Zweistellige Jahrgaenge: "87" → 1987, "09" → 2009. Grenze ist das
     * aktuelle Jahr; "?" oder leer bleibt offen.
     */
    private function birthYear(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^\d{2}$|^\d{4}$/', $raw)) return null;

        $n = (int) $raw;
        if (strlen($raw) === 4) return $n;

        return $n <= (int) date('y') ? 2000 + $n : 1900 + $n;
    }

    private function year(string $raw): ?int
    {
        return preg_match('/^(19|20)\d{2}$/', trim($raw)) ? (int) trim($raw) : null;
    }

    /** Spaltenbuchstabe um n Spalten verschieben: A+1 = B, Z+1 = AA */
    private function shift(string $col, int $n): string
    {
        $index = 0;
        foreach (str_split(strtoupper($col)) as $c) {
            $index = $index * 26 + (ord($c) - 64);
        }
        $index += $n;

        $out = '';
        while ($index > 0) {
            $rest  = ($index - 1) % 26;
            $out   = chr(65 + $rest) . $out;
            $index = intdiv($index - 1, 26);
        }

        return $out;
    }
}
