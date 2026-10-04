<?php

namespace App\Support;

/**
 * Dateien im DSV-Standard (Version 7 und 8) und Lenex lesen.
 *
 * DSV8 (gültig ab 01.08.2026, ab 2027 Pflicht) erlaubt zusätzlich eine
 * gepackte Fassung: ZIP-Archiv mit Endung .DSV8z, darin genau eine
 * DSV8-Datei. Lenex kennt dasselbe als .lxf. Gepackt oder nicht erkennt
 * read() am Inhalt, nicht an der Endung – der Aufrufer muss nichts wählen.
 *
 * ZipArchive ist nicht auf jedem Server vorhanden, deshalb liest
 * unzipSingle() das Archiv selbst (Verfahren "stored" und "deflate").
 */
class DsvFile
{
    /** Erlaubte Endungen beim Hochladen von Ausschreibungen und Ergebnissen. */
    const EXTENSIONS = ['dsv7', 'dsv8', 'dsv8z', 'lef', 'lxf', 'xml', 'txt'];

    /** Für accept-Attribute von Datei-Feldern. */
    const ACCEPT = '.dsv7,.dsv8,.dsv8z,.lef,.lxf,.xml,.txt';

    /** Für Fehlermeldungen und Hinweise. */
    const LABEL = '.dsv7, .dsv8, .dsv8z, .lef, .lxf, .xml oder .txt';

    /**
     * Steht dieselbe Datei als DSV7 und DSV8 (bzw. .DSV8z) zum Download,
     * nur die neueste Fassung behalten – sonst würde sie doppelt importiert.
     */
    public static function preferNewest(array $links): array
    {
        $best = [];
        foreach (array_unique($links) as $link) {
            $path = parse_url($link, PHP_URL_PATH) ?: $link;
            if (!preg_match('/^(.*)\.dsv(7|8)(z?)$/i', $path, $m)) {
                $best[$link] = [$link, 0];
                continue;
            }
            $key  = strtolower($m[1]);
            $rank = (int) $m[2] * 2 + ($m[3] === '' ? 1 : 0); // DSV8 vor DSV8z vor DSV7
            if (!isset($best[$key]) || $rank > $best[$key][1]) {
                $best[$key] = [$link, $rank];
            }
        }

        return array_values(array_map(fn($b) => $b[0], $best));
    }

    public static function allowedExtension(?string $ext): bool
    {
        return in_array(strtolower((string) $ext), self::EXTENSIONS, true);
    }

    /**
     * Inhalt der Datei, bei ZIP-Archiven der Inhalt der einzigen Datei darin.
     *
     * @throws \RuntimeException wenn die Datei nicht lesbar oder das Archiv defekt ist
     */
    public static function read(string $filePath): string
    {
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        }

        return self::isZip($raw) ? self::unzipSingle($raw) : $raw;
    }

    public static function isZip(string $raw): bool
    {
        return str_starts_with($raw, "PK\x03\x04");
    }

    /**
     * Versionsnummer aus der Zeile "FORMAT:Listart;Version;" (7 oder 8),
     * null bei Lenex oder fehlender Angabe.
     */
    public static function version(string $content): ?int
    {
        if (preg_match('/^\s*FORMAT\s*:[^;\r\n]*;\s*(\d+)/mi', $content, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Die einzige Datei eines ZIP-Archivs entpacken.
     *
     * Größen und Offsets stehen verlässlich nur im zentralen Verzeichnis
     * (bei Datenstrom-Archiven sind sie im lokalen Kopf 0), daher von dort.
     */
    public static function unzipSingle(string $raw): string
    {
        $eocd = strrpos($raw, "PK\x05\x06");
        if ($eocd === false || strlen($raw) < $eocd + 22) {
            throw new \RuntimeException('Das ZIP-Archiv ist beschädigt.');
        }

        $end     = unpack('vdisk/vcdDisk/ventriesDisk/ventries/VcdSize/VcdOffset', substr($raw, $eocd + 4, 16));
        $pos     = $end['cdOffset'];
        $entries = [];

        for ($i = 0; $i < $end['entries']; $i++) {
            if (substr($raw, $pos, 4) !== "PK\x01\x02") {
                throw new \RuntimeException('Das ZIP-Archiv ist beschädigt.');
            }
            $h = unpack(
                'vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/VcompSize/Vsize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                substr($raw, $pos + 4, 42)
            );
            $name = substr($raw, $pos + 46, $h['nameLen']);
            $pos += 46 + $h['nameLen'] + $h['extraLen'] + $h['commentLen'];

            // Ordner und macOS-Beigaben ueberspringen
            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) continue;
            $entries[] = $h + ['name' => $name];
        }

        if (count($entries) !== 1) {
            throw new \RuntimeException(count($entries) === 0
                ? 'Das ZIP-Archiv enthält keine Datei.'
                : 'Das ZIP-Archiv enthält mehrere Dateien – erlaubt ist genau eine DSV- bzw. Lenex-Datei.');
        }

        $e     = $entries[0];
        $local = unpack('vnameLen/vextraLen', substr($raw, $e['localOffset'] + 26, 4));
        $data  = substr($raw, $e['localOffset'] + 30 + $local['nameLen'] + $local['extraLen'], $e['compSize']);

        $content = match ($e['method']) {
            0       => $data,
            8       => @gzinflate($data),
            default => throw new \RuntimeException('Das ZIP-Archiv verwendet ein nicht unterstütztes Packverfahren.'),
        };

        if ($content === false || strlen($content) !== $e['size']) {
            throw new \RuntimeException('Das ZIP-Archiv ist beschädigt.');
        }

        return $content;
    }

    /**
     * Dateiname nach DSV-Standard: JJJJ-MM-TT-Ort-Zusatz.DSVn
     * Datum = letzter Veranstaltungstag, Ort max. 8 Zeichen, Verein max. 16,
     * ohne Leerzeichen/Bindestriche, Umlaute ausgeschrieben.
     */
    public static function filename(\DateTimeInterface $lastDay, ?string $place, string $suffix, int $version, ?string $club = null): string
    {
        $parts = [$lastDay->format('Y-m-d'), mb_substr(self::plain($place ?: 'Ort'), 0, 8)];
        if ($club) {
            $parts[] = mb_substr(self::plain($club), 0, 16);
        }
        $parts[] = $suffix;

        return implode('-', $parts) . '.DSV' . $version;
    }

    private static function plain(string $s): string
    {
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);

        return preg_replace('/[^A-Za-z0-9.]/', '', $s);
    }
}
