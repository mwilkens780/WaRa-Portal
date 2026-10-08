<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * iCalendar (RFC 5545) für Abo-Feed, Einzel-Export und Mail-Anhang.
 *
 * Zeiten gehen als UTC raus (…Z) – das verstehen Outlook, Apple und Google
 * ohne eigene Zeitzonen-Definition. Ganztägige Einträge als DATE, das Ende
 * ist dabei der Folgetag (exklusiv).
 *
 * Ein Eintrag (Array):
 *   uid, summary, start (Carbon), end (Carbon), all_day (bool),
 *   location?, description?, url?, updated? (Carbon)
 */
class Ics
{
    public const TZ = 'Europe/Berlin';

    /** @param iterable<array> $items */
    public static function calendar(iterable $items, string $name, bool $feed = false): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SG Wasserratten Norderstedt//WaRa-Portal//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::text($name),
            'X-WR-TIMEZONE:' . self::TZ,
        ];
        if ($feed) {
            // Wunsch an die Kalender-Apps: stündlich nachsehen (Google hält sich nicht daran)
            $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
            $lines[] = 'X-PUBLISHED-TTL:PT1H';
        }

        $stamp = now()->utc()->format('Ymd\THis\Z');
        foreach ($items as $i) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $i['uid'];
            $lines[] = 'DTSTAMP:' . $stamp;
            if ($i['all_day']) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $i['start']->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $i['end']->copy()->addDay()->format('Ymd');
            } else {
                $lines[] = 'DTSTART:' . self::utc($i['start']);
                $lines[] = 'DTEND:' . self::utc($i['end']);
            }
            $lines[] = 'SUMMARY:' . self::text($i['summary']);
            if (!empty($i['location']))    $lines[] = 'LOCATION:' . self::text($i['location']);
            if (!empty($i['description'])) $lines[] = 'DESCRIPTION:' . self::text($i['description']);
            if (!empty($i['url']))         $lines[] = 'URL:' . $i['url'];
            if (!empty($i['updated']))     $lines[] = 'LAST-MODIFIED:' . self::utc($i['updated']);
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** Datum + "HH:MM[:SS]" als Ortszeit Norderstedt */
    public static function local(CarbonInterface $date, ?string $time): Carbon
    {
        return Carbon::parse($date->format('Y-m-d') . ' ' . ($time ? substr($time, 0, 5) : '00:00'), self::TZ);
    }

    private static function utc(CarbonInterface $dt): string
    {
        return $dt->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Text-Werte: Backslash, Semikolon, Komma und Zeilenumbrüche maskieren */
    public static function text(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", trim($s));
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
    }

    /** Zeilen über 75 Byte falten, ohne UTF-8-Zeichen zu zerschneiden */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) return $line;

        $out = ''; $len = 0; $limit = 75;
        foreach (mb_str_split($line) as $ch) {
            $b = strlen($ch);
            if ($len + $b > $limit) {
                $out .= "\r\n ";
                $len = 1; $limit = 75;
            }
            $out .= $ch;
            $len += $b;
        }
        return $out;
    }
}
