<?php

namespace App\Support;

/**
 * Geschlecht im Portal – eine Stelle für Codes, Beschriftungen und die
 * Übersetzung aus/in Fremdformate.
 *
 * Portal-Codes: M = männlich, F = weiblich, D = divers (seit DSV-Standard 8),
 * X = gemischt (nur bei Wettkämpfen/Staffeln, nie bei Personen).
 * Staffeln speichern gemischt historisch als 'mixed'.
 */
final class Gender
{
    const MALE   = 'M';
    const FEMALE = 'F';
    const DIVERSE = 'D';
    const MIXED  = 'X';

    /** Geschlechter von Personen, in Anzeigereihenfolge */
    const PERSON = ['F', 'M', 'D'];

    const LABELS = ['F' => 'weiblich', 'M' => 'männlich', 'D' => 'divers', 'X' => 'gemischt'];

    /** Überschriften (Rekorde, Bestenlisten, Wertungen) */
    const TITLES = ['F' => 'Weiblich', 'M' => 'Männlich', 'D' => 'Divers', 'X' => 'Gemischt'];

    /** Kurzform für enge Tabellen */
    const SHORT = ['F' => 'w', 'M' => 'm', 'D' => 'd', 'X' => 'mix'];

    /** Gemischte Wertung (Mixed) – auch in der alten Staffel-Schreibweise 'mixed' */
    public static function isMixed(?string $code): bool
    {
        return self::normalize($code) === self::MIXED;
    }

    public static function label(?string $code): ?string
    {
        return self::LABELS[self::normalize($code) ?? ''] ?? null;
    }

    public static function title(?string $code): ?string
    {
        return self::TITLES[self::normalize($code) ?? ''] ?? null;
    }

    public static function short(?string $code): ?string
    {
        return self::SHORT[self::normalize($code) ?? ''] ?? null;
    }

    /**
     * Beliebige Schreibweise → Portal-Code (M/F/D/X) oder null.
     * Versteht DSV (W), Lenex (F), WebClub/Excel ("weiblich", "w", "divers" …).
     */
    public static function normalize(?string $value): ?string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'm', 'männlich', 'maennlich', 'male', 'mann', 'männer', 'herren', 'h' => self::MALE,
            'f', 'w', 'weiblich', 'female', 'frau', 'frauen', 'damen'            => self::FEMALE,
            'd', 'divers', 'diverse', 'div'                                      => self::DIVERSE,
            'x', 'mixed', 'gemischt', 'mix'                                      => self::MIXED,
            default                                                             => null,
        };
    }

    /** Portal-Code → DSV-Standard (M/W/D/X) */
    public static function toDsv(?string $code): string
    {
        return match (self::normalize($code)) {
            self::FEMALE  => 'W',
            self::MALE    => 'M',
            self::DIVERSE => 'D',
            default       => 'X',
        };
    }

    /** Validierungsregel für das Geschlecht einer Person */
    public static function personRule(): string
    {
        return 'in:' . implode(',', self::PERSON);
    }
}
