<?php

namespace App\Services\Import;

/**
 * Wandelt das Zellraster des Hallenbelegungsplans in Belegungseintraege um.
 *
 * Aufbau der Vorlage (an der Datei "Hallenbelegung SuV" verifiziert):
 *
 *   Zeile 3  Wochentage, dazu mehrere Bloecke "Uhrzeit"
 *   Zeile 4  je Tag fuenf Ressourcen: Bahn 4 | Bahn 3 | Bahn 2 | Bahn 1 | NS
 *            sowie je Zeitblock ein Spaltenpaar von/bis
 *   ab 5     Raster im 15-Minuten-Takt
 *   unten    Zusatzzeilen im Format  Label | Zeit | Gruppe | Trainer
 *
 * Ein Block ist ein zusammenhaengender Lauf gleicher Farbsignatur. Da direkt
 * untereinander liegende Bloecke dieselbe Farbe haben koennen (z.B. Delfine und
 * Robben), zaehlt zusaetzlich die obere Rahmenkante als Blockbeginn.
 * Innerhalb eines Blocks steht in der ersten belegten Zeile die Gruppe,
 * darunter die Betreuer.
 */
class HallPlanParser
{
    /** Wochentage → ISO-Nummer (1 = Montag). */
    private const DAYS = [
        'montag' => 1, 'dienstag' => 2, 'mittwoch' => 3, 'donnerstag' => 4,
        'freitag' => 5, 'samstag' => 6, 'sonntag' => 7,
    ];

    /**
     * Farbsignatur → Kategorie.
     * 'booking' entspricht hall_bookings.type, 'session' sagt, ob zusaetzlich
     * eine Trainingsserie entstehen soll.
     */
    private const CATEGORIES = [
        '0070C0' => ['key' => 'leistungssport', 'label' => 'Leistungssport', 'booking' => 'training', 'session' => true],
        '00B050' => ['key' => 'breitensport',   'label' => 'Breitensport',   'booking' => 'training', 'session' => true],
        '002060' => ['key' => 'masters',        'label' => 'Masters',        'booking' => 'training', 'session' => true],
        'FF0000' => ['key' => 'triathlon',      'label' => 'Triathlon',      'booking' => 'training', 'session' => true],
        'A6CAF0' => ['key' => 'nachwuchs',      'label' => 'Delfine/Robben', 'booking' => 'training', 'session' => true],
        '99CCFF' => ['key' => 'nachwuchs',      'label' => 'Delfine/Robben', 'booking' => 'training', 'session' => true],
        'CCFFCC' => ['key' => 'kurs',           'label' => 'Kurs',           'booking' => 'course',   'session' => false],
        'E3E3E3' => ['key' => 'dlrg',           'label' => 'DLRG',           'booking' => 'external', 'session' => false],
        'CC99FF' => ['key' => 'tgw',            'label' => 'TGW',            'booking' => 'external', 'session' => false],
        '33CCCC' => ['key' => 'svf',            'label' => 'SVF',            'booking' => 'external', 'session' => false],
    ];

    private const SYNCHRO = ['key' => 'synchro', 'label' => 'Synchro', 'booking' => 'training', 'session' => true];
    private const WARA    = ['key' => 'kurs',    'label' => 'Kurs',    'booking' => 'course',   'session' => false];

    /** Nur dieses Label aus dem Zusatzbereich wird uebernommen. */
    private const EXTRA_LABEL    = 'kraftraum';
    private const EXTRA_RESOURCE = 'Mehrzweckraum';

    private array $warnings = [];

    /**
     * @param array<int,array<string,array>> $cells Raster aus HallPlanReader
     * @return array{entries: list<array>, warnings: list<string>}
     */
    public function parse(array $cells): array
    {
        $this->warnings = [];

        $timeCols = $this->findTimeColumns($cells);
        $days     = $this->findDayColumns($cells, $timeCols);

        if (!$days) {
            $this->warnings[] = 'Keine Wochentage in Zeile 3 gefunden – Aufbau der Datei unerwartet.';
            return ['entries' => [], 'warnings' => $this->warnings];
        }

        $entries = [];
        foreach ($days as $day) {
            // Das Raster endet dort, wo die Uhrzeit-Spalte des Tages endet.
            // Darunter stehen Anmerkungen und der Kraftraum-Bereich, die sonst
            // als Blöcke mitgelesen würden.
            $lastRow = $this->lastGridRow($cells, $day['von']);

            foreach ($day['resources'] as $col => $resource) {
                foreach ($this->blocksInColumn($cells, $col, $lastRow) as $block) {
                    $entry = $this->buildEntry($cells, $block, $day, $col, $resource);
                    if ($entry) $entries[] = $entry;
                }
            }
        }

        foreach ($this->parseExtraRows($cells, $days) as $entry) {
            $entries[] = $entry;
        }

        usort($entries, fn($a, $b) => [$a['day'], $a['start'], $a['resource']]
                                  <=> [$b['day'], $b['start'], $b['resource']]);

        return ['entries' => $entries, 'warnings' => $this->warnings];
    }

    // ── Kopfzeilen ──────────────────────────────────────────────────────────

    /** @return list<array{von:string,bis:string,index:int}> nach Spalte sortiert */
    private function findTimeColumns(array $cells): array
    {
        $pairs = [];
        foreach ($cells[4] ?? [] as $col => $cell) {
            if (mb_strtolower($cell['v']) !== 'von') continue;
            $next = $this->nextColumn($col);
            if (mb_strtolower($cells[4][$next]['v'] ?? '') !== 'bis') continue;
            $pairs[] = ['von' => $col, 'bis' => $next, 'index' => $this->colIndex($col)];
        }
        usort($pairs, fn($a, $b) => $a['index'] <=> $b['index']);
        return $pairs;
    }

    /** @return list<array{day:int,name:string,resources:array<string,string>,von:string,bis:string}> */
    private function findDayColumns(array $cells, array $timeCols): array
    {
        $days = [];
        foreach ($cells[3] ?? [] as $col => $cell) {
            $key = mb_strtolower(trim($cell['v']));
            if (!isset(self::DAYS[$key])) continue;

            // Ressourcen stehen in Zeile 4 ab der Tagesspalte.
            //
            // Der Lauf muss zweifach begrenzt werden: Die Tagesbloecke stossen
            // direkt aneinander (nach "NS" des Montags folgt "Bahn 4" des
            // Dienstags), und jede Ressource kommt je Tag genau einmal vor.
            // Ohne beide Abbruchbedingungen zieht der erste Tag die Spalten
            // aller folgenden an sich.
            $resources = [];
            $cursor    = $col;
            while (true) {
                if ($cursor !== $col && $this->isDayHeader($cells, $cursor)) break;

                $name = trim($cells[4][$cursor]['v'] ?? '');
                if (!$this->isResourceName($name)) break;

                $normalized = $this->normalizeResource($name);
                if (in_array($normalized, $resources, true)) break;

                $resources[$cursor] = $normalized;
                $cursor = $this->nextColumn($cursor);
            }

            if (!$resources) {
                $this->warnings[] = "Für {$cell['v']} wurden keine Bahnen in Zeile 4 gefunden.";
                continue;
            }

            // Zugehoeriges Zeitspaltenpaar: das naechstgelegene links davon
            $dayIndex = $this->colIndex($col);
            $pair     = null;
            foreach ($timeCols as $p) {
                if ($p['index'] < $dayIndex) $pair = $p; else break;
            }
            if (!$pair) {
                $this->warnings[] = "Für {$cell['v']} wurde keine Uhrzeit-Spalte gefunden.";
                continue;
            }

            $days[] = [
                'day'       => self::DAYS[$key],
                'name'      => trim($cell['v']),
                'resources' => $resources,
                'von'       => $pair['von'],
                'bis'       => $pair['bis'],
            ];
        }
        return $days;
    }

    private function isDayHeader(array $cells, string $col): bool
    {
        $v = mb_strtolower(trim($cells[3][$col]['v'] ?? ''));
        return $v !== '' && isset(self::DAYS[$v]);
    }

    private function isResourceName(string $name): bool
    {
        $n = mb_strtolower($name);
        return $n !== '' && (str_starts_with($n, 'bahn') || $n === 'ns' || str_contains($n, 'nichtschwimmer'));
    }

    private function normalizeResource(string $name): string
    {
        $n = mb_strtolower(trim($name));
        if ($n === 'ns' || str_contains($n, 'nichtschwimmer')) return 'Nichtschwimmerbecken';
        if (preg_match('/bahn\s*(\d)/', $n, $m)) return 'Bahn ' . $m[1];
        return trim($name);
    }

    // ── Blockerkennung ──────────────────────────────────────────────────────

    /** Letzte Rasterzeile: die letzte Zeile mit lesbarer Uhrzeit in der von-Spalte. */
    private function lastGridRow(array $cells, string $vonCol): int
    {
        $last = 4;
        foreach ($cells as $r => $row) {
            if ($r < 5) continue;
            if ($this->parseTime($row[$vonCol]['v'] ?? '') !== null) $last = max($last, $r);
        }
        return $last;
    }

    /** @return list<array{from:int,to:int,fill:?string,font:?string,pattern:string}> */
    private function blocksInColumn(array $cells, string $col, int $lastRow): array
    {
        $rows = array_filter(array_keys($cells), fn($r) => $r >= 5 && $r <= $lastRow);
        sort($rows);

        // Bewusst ohne Referenz: $blocks[] = &$current; wuerde beim spaeteren
        // $current = null auch den Eintrag im Array auf null setzen.
        $blocks  = [];
        $open    = false;
        $prevSig = null;

        foreach ($rows as $r) {
            $cell = $cells[$r][$col] ?? null;
            if (!$cell) { $open = false; $prevSig = null; continue; }

            $sig = ($cell['fill'] ?? '-') . '|' . ($cell['font'] ?? '-') . '|' . ($cell['pattern'] ?? '-');

            // Neuer Block: obere Rahmenkante oder Wechsel der Farbsignatur.
            // Ohne die Rahmenkante wuerden gleichfarbige Bloecke verschmelzen.
            if (!$open || $cell['top'] || $sig !== $prevSig) {
                $blocks[] = [
                    'from'    => $r,
                    'to'      => $r,
                    'fill'    => $cell['fill'],
                    'font'    => $cell['font'],
                    'pattern' => $cell['pattern'] ?? 'none',
                ];
                $open = true;
            } else {
                $blocks[count($blocks) - 1]['to'] = $r;
            }

            $prevSig = $sig;
            if ($cell['bottom']) { $open = false; $prevSig = null; }
        }

        return $blocks;
    }

    private function buildEntry(array $cells, array $block, array $day, string $col, string $resource): ?array
    {
        $category = $this->categorize($block);
        if (!$category) return null;

        // Texte des Blocks: erster belegter Wert ist die Gruppe, Rest sind Betreuer
        $texts = [];
        for ($r = $block['from']; $r <= $block['to']; $r++) {
            $v = trim($cells[$r][$col]['v'] ?? '');
            if ($v !== '') $texts[] = $v;
        }
        if (!$texts) return null;

        $start = $this->readTime($cells, $block['from'], $day['von']);
        $end   = $this->readTime($cells, $block['to'],   $day['bis']);

        if ($start === null || $end === null) {
            $this->warnings[] = sprintf(
                '%s %s, Zeilen %d–%d ("%s"): Uhrzeit nicht lesbar – übersprungen.',
                $day['name'], $resource, $block['from'], $block['to'], $texts[0]
            );
            return null;
        }
        if ($end <= $start) {
            $this->warnings[] = sprintf(
                '%s %s ("%s"): Ende %s liegt nicht nach Beginn %s – übersprungen.',
                $day['name'], $resource, $texts[0], $end, $start
            );
            return null;
        }

        return [
            'day'          => $day['day'],
            'day_name'     => $day['name'],
            'resource'     => $resource,
            'start'        => $start,
            'end'          => $end,
            'category'     => $category['key'],
            'category_label' => $category['label'],
            'booking_type' => $category['booking'],
            'needs_session' => $category['session'],
            'group_raw'    => $texts[0],
            'trainers_raw' => array_values(array_slice($texts, 1)),
            'color'        => $block['fill'],
            'source'       => $col . $block['from'] . ':' . $col . $block['to'],
        ];
    }

    private function categorize(array $block): ?array
    {
        $fill = $block['fill'];
        $font = $block['font'];

        // Synchro zuerst: weisse Fuellung, erkennbar allein an der pinken Schrift.
        if ($font === 'FF00FF') return self::SYNCHRO;

        if ($fill !== null && isset(self::CATEGORIES[$fill])) return self::CATEGORIES[$fill];

        // "Wara" ist weiss MIT Raster – ohne den Mustertyp nicht von den vielen
        // gewoehnlichen weissen Zellen zu unterscheiden.
        $patterned = ($block['pattern'] ?? 'none') !== 'none' && $block['pattern'] !== 'solid';
        if ($patterned && ($fill === 'FFFFFF' || $fill === null)) return self::WARA;

        return null;   // Struktur, Notizen, leere Zellen
    }

    // ── Zusatzbereich unterhalb des Rasters ─────────────────────────────────

    /** @return list<array> */
    private function parseExtraRows(array $cells, array $days): array
    {
        $entries = [];

        foreach ($cells as $r => $row) {
            foreach ($days as $day) {
                $labelCol = array_key_first($day['resources']);
                $label    = mb_strtolower(trim($cells[$r][$labelCol]['v'] ?? ''));
                if (!str_starts_with($label, self::EXTRA_LABEL)) continue;

                $timeCol    = $this->nextColumn($labelCol);
                $groupCol   = $this->nextColumn($timeCol);
                $trainerCol = $this->nextColumn($groupCol);

                $rangeRaw = trim($cells[$r][$timeCol]['v'] ?? '');
                $range    = $this->parseTimeRange($rangeRaw);
                $group    = trim($cells[$r][$groupCol]['v'] ?? '');

                if (!$range || $group === '') {
                    $this->warnings[] = sprintf(
                        '%s, Zeile %d (Kraftraum): "%s / %s" nicht auswertbar – übersprungen.',
                        $day['name'], $r, $rangeRaw, $group
                    );
                    continue;
                }

                $trainer = trim($cells[$r][$trainerCol]['v'] ?? '');

                $entries[] = [
                    'day'            => $day['day'],
                    'day_name'       => $day['name'],
                    'resource'       => self::EXTRA_RESOURCE,
                    'start'          => $range[0],
                    'end'            => $range[1],
                    'category'       => 'kraftraum',
                    'category_label' => 'Kraftraum',
                    'booking_type'   => 'training',
                    'needs_session'  => false,
                    'group_raw'      => $group,
                    'trainers_raw'   => $trainer !== '' ? [$trainer] : [],
                    'color'          => null,
                    'source'         => $labelCol . $r,
                ];
            }
        }

        return $entries;
    }

    // ── Zeiten ──────────────────────────────────────────────────────────────

    private function readTime(array $cells, int $row, string $col): ?string
    {
        return $this->parseTime($cells[$row][$col]['v'] ?? '');
    }

    /**
     * Die Vorlage wird von Hand gepflegt: Uhrzeiten stehen mal als Text
     * ("14.00", "07:15"), mal als Excel-Bruchzahl (0.5729…) in derselben Spalte.
     */
    private function parseTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        if (preg_match('/^(\d{1,2})[.:](\d{2})$/', $raw, $m)) {
            $h = (int) $m[1]; $i = (int) $m[2];
            return ($h < 24 && $i < 60) ? sprintf('%02d:%02d', $h, $i) : null;
        }

        if (is_numeric($raw)) {
            $f = (float) $raw;
            if ($f < 0 || $f >= 1) return null;
            $minutes = (int) round($f * 24 * 60);
            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        return null;
    }

    /**
     * "16:45-17:45", "18.30-19.30", auch mit Zusatztext dahinter.
     *
     * Der Doppelpunkt ist als Trenner mit zugelassen: die von Hand gepflegte
     * Vorlage enthaelt Tippfehler wie "16:15:17:15". Da beide Seiten dem Muster
     * HH:MM folgen muessen, bleibt die Bedeutung eindeutig.
     */
    private function parseTimeRange(string $raw): ?array
    {
        if (!preg_match('/(\d{1,2})[.:](\d{2})\s*[-–:]\s*(\d{1,2})[.:](\d{2})/', $raw, $m)) return null;

        $start = $this->parseTime($m[1] . ':' . $m[2]);
        $end   = $this->parseTime($m[3] . ':' . $m[4]);

        return ($start && $end && $end > $start) ? [$start, $end] : null;
    }

    // ── Spaltenhilfen ───────────────────────────────────────────────────────

    private function colIndex(string $col): int
    {
        $n = 0;
        foreach (str_split(strtoupper($col)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n;
    }

    private function nextColumn(string $col): string
    {
        $n = $this->colIndex($col) + 1;
        $s = '';
        while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = intdiv($n - 1 - $m, 26); }
        return $s;
    }
}
