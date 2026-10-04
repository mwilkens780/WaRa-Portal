<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Models\Setting;
use App\Services\Dsv7Parser;

/**
 * Gemeinsame Bausteine zum Schreiben von Dateien im DSV-Standard 7 und 8.
 *
 * Format laut Standard: eine Zeile je Element "ELEMENT:Attr1;Attr2;…;",
 * jedes Attribut mit Semikolon abgeschlossen, UTF-8 ohne BOM, Zeiten als
 * HH:MM:SS,hh, Datum TT.MM.JJJJ, Geschlecht M/W/D/X.
 */
class DsvWriter
{
    /** Landesschwimmverband laut Anhang des Standards: 14 = Schleswig-Holsteinischer SV */
    const LSV_SHSV = 14;

    /** Ab 2027 ist nur noch DSV8 zulässig (DSV7 gilt bis 31.12.2026). */
    const DSV8_REQUIRED_FROM = '2027-01-01';

    private array $lines = [];

    public function __construct(public readonly int $version)
    {
    }

    /**
     * Version für eine Datei zu diesem Wettkampf: die Version der Ausschreibung
     * des Ausrichters (dessen Software liest sie sicher), sonst nach Datum –
     * bis Ende 2026 DSV7, das jede Software noch kennt, ab 2027 DSV8.
     */
    public static function versionFor(Competition $competition): int
    {
        $fromFile = (int) ($competition->dsv_header_data['dsv_version'] ?? 0);
        if (in_array($fromFile, [7, 8], true)) {
            return $fromFile;
        }

        $lastDay = $competition->date_end ?? $competition->date;

        return $lastDay && $lastDay->format('Y-m-d') >= self::DSV8_REQUIRED_FROM ? 8 : 7;
    }

    public static function clubName(): string
    {
        return (string) (Setting::getCached('club.name') ?: 'SG Wasserratten Norderstedt');
    }

    /** Vierstellige Vereinskennzahl des DSV (Admin → Einstellungen). */
    public static function clubNumber(): ?string
    {
        $nr = trim((string) Setting::getCached('club.dsv_number', ''));

        return $nr !== '' ? $nr : null;
    }

    public function add(string $element, array $attributes = []): self
    {
        $this->lines[] = $attributes === []
            ? $element
            : $element . ':' . implode('', array_map(fn($a) => $this->clean($a) . ';', $attributes));

        return $this;
    }

    public function content(): string
    {
        return implode("\r\n", $this->lines) . "\r\n";
    }

    // ── Werte ───────────────────────────────────────────────────────────────

    /** Zeit nach Standard (HH:MM:SS,hh); ohne Zeit der Unterlassungswert. */
    public static function time(?int $ms): string
    {
        $ms = max(0, (int) $ms);

        return sprintf('%02d:%02d:%02d,%02d',
            intdiv($ms, 3_600_000), intdiv($ms % 3_600_000, 60_000),
            intdiv($ms % 60_000, 1_000), intdiv($ms % 1_000, 10));
    }

    /** Portal-Geschlecht (M/F/X/mixed) → Standard (M/W/X). */
    public static function gender(?string $g): string
    {
        return match (strtoupper((string) $g)) {
            'F', 'W' => 'W',
            'M'      => 'M',
            'D'      => 'D',
            default  => 'X',
        };
    }

    public static function stroke(?string $discipline): string
    {
        return array_flip(Dsv7Parser::STROKE_MAP)[$discipline] ?? 'X';
    }

    public static function amount(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    /** "Nachname, Vorname" wie im Standard verlangt */
    public static function personName(?string $lastname, ?string $firstname): string
    {
        return trim(($lastname ?? '') . ', ' . ($firstname ?? ''), ', ');
    }

    /** Semikolon und Zeilenumbruch würden die Zeile zerstören */
    private function clean(mixed $value): string
    {
        return trim(str_replace([';', "\r", "\n"], [',', ' ', ' '], (string) $value));
    }

    // ── Wettkämpfe und Abschnitte ───────────────────────────────────────────

    /**
     * Wettkämpfe des Wettkampfs: bevorzugt genau so, wie der Ausrichter sie in
     * der Ausschreibung definiert hat, sonst aus den Wertungen im Portal.
     *
     * @return array<int, array> nach Wettkampfnummer
     */
    public static function wettkaempfe(Competition $competition): array
    {
        $out = [];
        foreach ($competition->dsv_header_data['wettkaempfe'] ?? [] as $wk) {
            if (!empty($wk['nr'])) $out[(int) $wk['nr']] = $wk;
        }
        if ($out) {
            ksort($out);
            return $out;
        }

        foreach ($competition->events->sortBy('event_number')->groupBy('event_number') as $nr => $wertungen) {
            $e    = $wertungen->first();
            $legs = (int) ($e->relay_legs ?? 0);
            $out[(int) $nr] = [
                'nr'          => (int) $nr,
                'art'         => 'E',
                'abschnitt'   => $e->session_number ?: 1,
                'starter'     => $legs > 1 ? $legs : '',
                // Staffeln: im Portal Gesamtstrecke, im Standard die Einzelstrecke
                'strecke'     => $legs > 1 ? intdiv((int) $e->distance, $legs) : (int) $e->distance,
                'technik'     => self::stroke($e->discipline),
                'ausuebung'   => 'GL',
                'geschlecht'  => self::gender($e->gender),
                'bestenliste' => 'SW',
                'quali_nr'    => '',
                'quali_art'   => '',
            ];
        }

        return $out;
    }

    /** @return array<int, array{nr:int, date:string, start:string, relative:string}> */
    public static function abschnitte(Competition $competition): array
    {
        $out = [];
        foreach ($competition->dsv_header_data['sessions'] ?? [] as $s) {
            if (empty($s['nr'])) continue;
            $relative = empty($s['start_time']) && !empty($s['pause_after']);
            $out[(int) $s['nr']] = [
                'nr'       => (int) $s['nr'],
                'date'     => $s['date'] ?? '',
                'einlass'  => $s['warmup_start'] ?? '',
                'kari'     => $s['warmup_end'] ?? '',
                'start'    => $relative ? $s['pause_after'] : ($s['start_time'] ?? ''),
                'relative' => $relative ? 'J' : '',
            ];
        }
        if ($out) {
            ksort($out);
            return $out;
        }

        foreach ($competition->events->groupBy('session_number') as $nr => $events) {
            $date = $events->first()->session_date ?? $competition->date;
            $out[(int) $nr] = [
                'nr' => (int) $nr, 'date' => $date?->format('d.m.Y') ?? '',
                'einlass' => '', 'kari' => '', 'start' => '', 'relative' => '',
            ];
        }
        ksort($out);

        return $out;
    }

    public static function poolLength(Competition $competition): string
    {
        $fromFile = $competition->dsv_header_data['pool_length'] ?? '';
        if ($fromFile !== '') return $fromFile;

        return match ($competition->course) { 'Langbahn' => '50', 'Kurzbahn' => '25', default => 'X' };
    }

    public static function timing(Competition $competition): string
    {
        $t = strtoupper($competition->dsv_header_data['timing'] ?? '');

        return in_array($t, ['HANDZEIT', 'AUTOMATISCH', 'HALBAUTOMATISCH'], true) ? $t : 'AUTOMATISCH';
    }
}
