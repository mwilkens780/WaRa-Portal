<?php

namespace App\Services;

/**
 * Untergrenze fuer Schwimmzeiten: erkennt unmoegliche Zeiten, nicht langsame.
 *
 * Anlass war der Staffel-Startabschnitt: eine 50-m-Zeit, die als 200-m-Ergebnis
 * gespeichert wurde, ist keine schnelle 200 m, sondern gar keine - und wurde
 * ungeprueft zum Vereinsrekord.
 *
 * Die Schranke ist eine Mindestzeit je 100 m und Lage. Sie liegt unter jeder
 * Weltrekord-Geschwindigkeit, damit sie niemals eine echte Zeit verwirft:
 * gemessen wird am schnellsten Fall ueberhaupt, dem 50-m-Weltrekord der Herren
 * auf der Kurzbahn, der je Lage etwa bei 40 s (Freistil), 43 s (Schmetterling),
 * 44 s (Ruecken) und 50 s (Brust) je 100 m liegt. Die Werte hier liegen
 * darunter.
 *
 * Damit bleibt die Pruefung auf langen Strecken grob - eine 200 m in 1:16
 * faellt durch, eine in 1:20 nicht. Das ist gewollt: sie soll Datenfehler
 * abfangen, nicht Leistungen beurteilen. Engere Grenzen je Strecke waeren eine
 * sportfachliche Festlegung und gehoeren dann hierher, nicht in den Code drumherum.
 */
final class TimePlausibility
{
    /** Mindestzeit je 100 m in Millisekunden, je Lage */
    public const MIN_MS_PER_100M = [
        'F' => 38000,   // Freistil
        'S' => 40000,   // Schmetterling
        'R' => 42000,   // Ruecken
        'B' => 46000,   // Brust
        'L' => 46000,   // Lagen (gibt es erst ab 100 m)
    ];

    /** Schnellste Zeit, die ueber diese Strecke ueberhaupt sein kann. */
    public static function minTimeMs(?string $discipline, ?int $distance): ?int
    {
        $perHundred = self::MIN_MS_PER_100M[$discipline] ?? null;
        if (!$perHundred || !$distance || $distance <= 0) return null;

        return (int) round($distance * $perHundred / 100);
    }

    /**
     * Ist die Zeit fuer diese Strecke unmoeglich?
     *
     * Eine fehlende Zeit (0 = nicht angetreten) und eine unbekannte Lage sind
     * keine unplausiblen Zeiten - dafuer gibt es andere Pruefungen.
     */
    public static function isImplausible(?string $discipline, ?int $distance, ?int $timeMs): bool
    {
        if (!$timeMs || $timeMs <= 0) return false;

        $min = self::minTimeMs($discipline, $distance);

        return $min !== null && $timeMs < $min;
    }

    /**
     * Dieselbe Pruefung als SQL-Bedingung, fuer Abfragen ueber ganze Tabellen.
     * Nur feste Werte aus der Konstante oben - keine Benutzereingabe.
     */
    public static function sqlCondition(string $table): string
    {
        $cases = '';
        foreach (self::MIN_MS_PER_100M as $discipline => $minPerHundred) {
            $cases .= " WHEN '{$discipline}' THEN {$minPerHundred}";
        }

        // time_ms * 100 < distance * Mindestzeit  - ohne Division, damit die
        // Bedingung in jeder Datenbank gleich rundet.
        return "{$table}.time_ms > 0"
             . " AND {$table}.time_ms * 100 < {$table}.distance * (CASE {$table}.discipline{$cases} ELSE 0 END)";
    }

    /** Lesbare Begruendung fuer die Oberflaeche und fuer Import-Protokolle. */
    public static function reason(?string $discipline, ?int $distance, ?int $timeMs): ?string
    {
        if (!self::isImplausible($discipline, $distance, $timeMs)) return null;

        $min = self::minTimeMs($discipline, $distance);

        return sprintf(
            '%d m: schneller als %s - ueber diese Strecke nicht moeglich',
            $distance,
            \App\Models\SwimmingTime::formatMs($min)
        );
    }
}
