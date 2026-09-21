<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Liest eine .xlsx so weit, wie der Hallenbelegungsplan es braucht: Wert,
 * Fuellfarbe, Schriftfarbe und Rahmen je Zelle.
 *
 * Bewusst ohne PhpSpreadsheet: benoetigt wird nur ein schmaler Ausschnitt des
 * Formats, und die Fallstricke sind bekannt und abgedeckt –
 *
 *   - Farben stehen als "indexed" in einer eigenen Palette der Datei. Der Index
 *     verweist DIREKT auf die Position; die Positionen 0-7 duplizieren 8-15
 *     (Excel-Altlast). Ein Versatz faellt bei kleinen Indizes nicht auf und
 *     verfaelscht alle grossen – aus Grau wurde so schon einmal Blau.
 *   - Musterfuellungen (gray0625, lightGrid) tragen ihre Farbe in bgColor
 *     statt fgColor.
 *   - Bei SimpleXML ist der foreach-Schluessel der Elementname, nicht der Index.
 */
class HallPlanReader
{
    /** @var array<int,string> indexed-Palette der Datei */
    private array $palette = [];
    /** @var array<int,array{pattern:string,color:?string}> */
    private array $fills = [];
    /** @var array<int,?string> */
    private array $fontColors = [];
    /** @var array<int,array<string,string>> borderId → Kante => Stil */
    private array $borders = [];
    /** @var array<int,int> */
    private array $xfFill = [], $xfFont = [], $xfBorder = [];
    /** @var array<int,string> */
    private array $sharedStrings = [];

    private string $extractDir;

    public function __construct(private string $path)
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(
                'Die PHP-Erweiterung "zip" fehlt. Ohne sie lassen sich xlsx-Dateien nicht lesen.'
            );
        }
        if (!is_file($this->path)) {
            throw new RuntimeException("Datei nicht gefunden: {$this->path}");
        }
    }

    /**
     * Liest das angegebene (oder erste sichtbare) Blatt.
     *
     * @return array{sheet:string, cells:array<int,array<string,array{v:string,fill:?string,font:?string,top:bool,bottom:bool}>>}
     */
    public function read(?string $sheetName = null): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('Datei ist kein lesbares xlsx-Archiv.');
        }

        try {
            $this->loadSharedStrings($zip);
            $this->loadStyles($zip);

            [$name, $target] = $this->resolveSheet($zip, $sheetName);
            $xml = $zip->getFromName($target);
            if ($xml === false) {
                throw new RuntimeException("Arbeitsblatt nicht lesbar: {$target}");
            }

            return ['sheet' => $name, 'cells' => $this->parseSheet($xml)];
        } finally {
            $zip->close();
        }
    }

    /** Namen aller Blaetter samt Sichtbarkeit – fuer die Auswahl im Import. */
    public function sheetNames(): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('Datei ist kein lesbares xlsx-Archiv.');
        }
        try {
            $wb  = $zip->getFromName('xl/workbook.xml') ?: '';
            $out = [];
            if (preg_match_all('/<sheet\b[^>]*>/', $wb, $m)) {
                foreach ($m[0] as $tag) {
                    $n = preg_match('/name="([^"]*)"/', $tag, $x) ? $x[1] : '';
                    $out[] = ['name' => $n, 'hidden' => str_contains($tag, 'state="hidden"')];
                }
            }
            return $out;
        } finally {
            $zip->close();
        }
    }

    // ── Bausteine ───────────────────────────────────────────────────────────

    private function loadSharedStrings(\ZipArchive $zip): void
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return;

        $sx = @simplexml_load_string($xml);
        if (!$sx) return;

        foreach ($sx->si as $si) {
            $text = isset($si->t) ? (string) $si->t : '';
            if (isset($si->r)) {
                foreach ($si->r as $run) $text .= (string) $run->t;
            }
            $this->sharedStrings[] = trim(preg_replace('/\s+/', ' ', $text));
        }
    }

    private function loadStyles(\ZipArchive $zip): void
    {
        $raw = $zip->getFromName('xl/styles.xml');
        if ($raw === false) return;

        // Eigene Palette der Datei. indexed=N verweist direkt auf Position N.
        if (preg_match('/<indexedColors>(.*?)<\/indexedColors>/s', $raw, $m)) {
            preg_match_all('/<rgbColor rgb="([0-9A-Fa-f]{6,8})"/', $m[1], $c);
            foreach ($c[1] as $i => $hex) {
                $this->palette[$i] = strtoupper(substr($hex, -6));
            }
        }

        $sx = @simplexml_load_string($raw);
        if (!$sx) return;

        $i = 0;
        foreach ($sx->fills->fill as $fill) {
            $pf      = $fill->patternFill;
            $pattern = isset($pf['patternType']) ? (string) $pf['patternType'] : 'none';
            // Musterfuellungen tragen die Farbe in bgColor.
            $color   = $this->color($pf->fgColor ?? null) ?? $this->color($pf->bgColor ?? null);
            $this->fills[$i++] = ['pattern' => $pattern, 'color' => $color];
        }

        $i = 0;
        foreach ($sx->fonts->font as $font) {
            $this->fontColors[$i++] = $this->color($font->color ?? null);
        }

        $i = 0;
        foreach ($sx->borders->border as $border) {
            $edges = [];
            foreach (['top', 'bottom', 'left', 'right'] as $edge) {
                if (isset($border->$edge['style'])) $edges[$edge] = (string) $border->$edge['style'];
            }
            $this->borders[$i++] = $edges;
        }

        $i = 0;
        foreach ($sx->cellXfs->xf as $xf) {
            $this->xfFill[$i]   = (int) ($xf['fillId']   ?? 0);
            $this->xfFont[$i]   = (int) ($xf['fontId']   ?? 0);
            $this->xfBorder[$i] = (int) ($xf['borderId'] ?? 0);
            $i++;
        }
    }

    private function color(?\SimpleXMLElement $node): ?string
    {
        if (!$node) return null;
        if (isset($node['rgb']))     return strtoupper(substr((string) $node['rgb'], -6));
        if (isset($node['indexed'])) return $this->palette[(int) $node['indexed']] ?? null;
        if (isset($node['theme']))   return 'theme' . (string) $node['theme'];
        return null;
    }

    /** @return array{0:string,1:string} Blattname und Pfad im Archiv */
    private function resolveSheet(\ZipArchive $zip, ?string $wanted): array
    {
        $wb   = $zip->getFromName('xl/workbook.xml') ?: '';
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels') ?: '';

        $relTargets = [];
        if (preg_match_all('/<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $rm, PREG_SET_ORDER)) {
            foreach ($rm as $r) $relTargets[$r[1]] = ltrim($r[2], '/');
        }

        $sheets = [];
        if (preg_match_all('/<sheet\b[^>]*>/', $wb, $m)) {
            foreach ($m[0] as $tag) {
                $name   = preg_match('/name="([^"]*)"/', $tag, $x)   ? $x[1] : '';
                $rid    = preg_match('/r:id="([^"]+)"/', $tag, $y)   ? $y[1] : '';
                $hidden = str_contains($tag, 'state="hidden"');
                $sheets[] = compact('name', 'rid', 'hidden');
            }
        }
        if (!$sheets) throw new RuntimeException('Keine Arbeitsblaetter gefunden.');

        $pick = null;
        if ($wanted !== null && $wanted !== '') {
            foreach ($sheets as $s) if ($s['name'] === $wanted) { $pick = $s; break; }
            if (!$pick) throw new RuntimeException("Arbeitsblatt \"{$wanted}\" nicht gefunden.");
        } else {
            // Ohne Angabe das erste sichtbare – die Datei enthaelt mehrere Altstaende.
            foreach ($sheets as $s) if (!$s['hidden']) { $pick = $s; break; }
            $pick ??= $sheets[0];
        }

        $target = $relTargets[$pick['rid']] ?? '';
        if ($target === '') throw new RuntimeException('Arbeitsblatt-Ziel nicht aufloesbar.');
        if (!str_starts_with($target, 'xl/')) $target = 'xl/' . $target;

        return [$pick['name'], $target];
    }

    /** @return array<int,array<string,array>> [Zeile][Spalte] */
    private function parseSheet(string $xml): array
    {
        $sx = @simplexml_load_string($xml);
        if (!$sx) throw new RuntimeException('Arbeitsblatt nicht parsebar.');

        $cells = [];
        foreach ($sx->sheetData->row as $row) {
            $r = (int) $row['r'];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $s   = (int) ($c['s'] ?? 0);

                $type = (string) $c['t'];
                if ($type === 's') {
                    $value = $this->sharedStrings[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = trim((string) ($c->is->t ?? ''));
                } else {
                    $value = isset($c->v) ? (string) $c->v : '';
                }

                $fill    = $this->fills[$this->xfFill[$s] ?? 0] ?? ['pattern' => 'none', 'color' => null];
                $borders = $this->borders[$this->xfBorder[$s] ?? 0] ?? [];

                $cells[$r][$col] = [
                    'v'      => trim($value),
                    'fill'   => $fill['pattern'] === 'none' ? null : $fill['color'],
                    // Der Mustertyp unterscheidet z.B. "Wara" (weiss MIT Raster)
                    // von den vielen gewoehnlichen weissen Zellen.
                    'pattern' => $fill['pattern'],
                    'font'   => $this->fontColors[$this->xfFont[$s] ?? 0] ?? null,
                    'top'    => isset($borders['top']),
                    'bottom' => isset($borders['bottom']),
                ];
            }
        }

        return $cells;
    }
}
