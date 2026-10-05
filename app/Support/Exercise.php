<?php

namespace App\Support;

/**
 * Übungsform eines Wettkampfs ("Ausübung" im DSV-Standard).
 *
 * Normal ist die ganze Lage (GL) – gespeichert als null. Alles andere
 * (z. B. 25 m Schmetterling nur Beine oder Kicks) wird übernommen und
 * markiert, zählt aber nicht als Zeit der Lage: keine Bestzeit, kein Rekord,
 * keine Bestenliste, keine WA-Punkte, keine Meldezeit.
 */
final class Exercise
{
    const LABELS = [
        'BE' => 'Beine',
        'AR' => 'Arme',
        'ST' => 'Start',
        'WE' => 'Wende',
        'GB' => 'Gleitübung',
        'KB' => 'Kicks Bauchlage',
        'KR' => 'Kicks Rückenlage',
        'X'  => 'Sonderform',
    ];

    /** DSV-Code → gespeicherter Wert (null = ganze Lage, unbekannt = Sonderform) */
    public static function normalize(?string $code): ?string
    {
        $c = strtoupper(trim((string) $code));
        if ($c === '' || $c === 'GL') return null;

        return isset(self::LABELS[$c]) ? $c : 'X';
    }

    public static function label(?string $code): ?string
    {
        return $code ? (self::LABELS[$code] ?? 'Sonderform') : null;
    }
}
