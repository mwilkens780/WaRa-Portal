<?php

namespace App\Support;

/**
 * Rudolph-Punkte: altersgerechte Einschätzung einer Leistung (1–20 Punkte).
 *
 * Grundlage ist die Punktetabelle des DSV ("Rudolph-Tabelle", Stand 2025,
 * gilt laut DSV unverändert 2026), abgelegt in resources/data/rudolph-2025.json.
 * Fachliche Vorgaben (Martin, 05.10.2026):
 *   - nur Langbahnzeiten werden bewertet
 *   - Alter nach Jahrgang: Wettkampfjahr − Geburtsjahr; unter 8 keine Punkte,
 *     ab 19 die Spalte "offen"
 * Für divers gibt es keine Tabelle.
 *
 * Bei einigen Strecken junger Jahrgänge weist die Tabelle selbst darauf hin,
 * dass sie statistisch unzureichend gesichert sind ("unreliable").
 */
final class RudolphTable
{
    const FILE = 'data/rudolph-2025.json';
    const EDITION = '2025 (gilt 2026)';

    private static ?array $data = null;

    /**
     * @return object{points:int, age:string, unreliable:bool}|null
     *         points 0 = langsamer als die 1-Punkt-Zeit
     */
    public static function score(?string $gender, ?int $birthYear, int $year, string $discipline, int $distance, int $timeMs): ?object
    {
        if (!in_array($gender, ['M', 'F'], true) || !$birthYear || $timeMs <= 0) return null;

        $ageYears = $year - $birthYear;
        if ($ageYears < 8) return null;
        $age = $ageYears >= 19 ? 'offen' : (string) $ageYears;

        $data   = self::data();
        $key    = $discipline . '_' . $distance;
        $limits = $data['tables'][$gender][$age][$key] ?? null;
        if (!$limits) return null;

        $points = 0;
        foreach ($limits as $i => $limitMs) {
            if ($timeMs <= $limitMs) { $points = 20 - $i; break; }
        }

        return (object) [
            'points'     => $points,
            'age'        => $age === 'offen' ? 'offene Klasse' : "{$age} Jahre",
            'unreliable' => in_array($key, $data['unreliable'][$gender][$age] ?? [], true),
        ];
    }

    private static function data(): array
    {
        return self::$data ??= json_decode(file_get_contents(resource_path(self::FILE)), true);
    }
}
