<?php

namespace App\Services\Import;

use App\Models\CompetitionResult;
use App\Models\ResultDiscrepancy;

/**
 * Gleicht ein von einer Quelle gemeldetes Ergebnis mit der bereits gespeicherten
 * Zeile ab (WebClub, DSV-Crawler, manueller DSV7-Import).
 *
 * Grundregeln:
 *   - Gleiche Daten  → nichts tun.
 *   - Eine Quelle füllt ein leeres Feld → ANREICHERUNG, kein Fehler. Wert wird
 *     übernommen. Beispiel: die PB-Markierung ist im WebClub zuverlässiger als
 *     im Portal, weil dort die Historie lückenhaft ist.
 *   - Beide Quellen haben einen Wert und er weicht ab → Abweichung festhalten.
 *     Der bestehende Wert bleibt stehen; entschieden wird von Hand.
 *   - Liefert eine Quelle zu einem Wettkampf gar nichts → kein Fehler. Diese
 *     Klasse wird dann schlicht nicht aufgerufen.
 */
class ResultReconciler
{
    /** Felder, die verglichen werden. */
    private const COMPARED_FIELDS = [
        'time_ms', 'placement', 'discipline', 'distance', 'gender',
        'age_group', 'is_personal_best', 'is_season_best', 'is_final',
        'wa_points', 'breaks_vereinsrekord', 'breaks_landesrekord',
    ];

    /** Boolesche Felder: false/0 bedeutet "unbekannt", nicht "nein". */
    private const FLAG_FIELDS = [
        'is_personal_best', 'is_season_best', 'is_final',
        'breaks_vereinsrekord', 'breaks_landesrekord',
    ];

    /**
     * @param  array<string,mixed>  $incoming  Feldwerte der meldenden Quelle
     * @return int  Anzahl neu erfasster Abweichungen
     */
    public function reconcile(CompetitionResult $existing, array $incoming, string $source): int
    {
        $enrich       = [];
        $discrepancies = 0;

        foreach (self::COMPARED_FIELDS as $field) {
            if (!array_key_exists($field, $incoming)) continue;

            $new = $this->normalize($field, $incoming[$field]);
            if ($new === null) continue;                 // Quelle kennt das Feld nicht

            $old = $this->normalize($field, $existing->{$field});

            if ($old === null) {
                $enrich[$field] = $incoming[$field];     // Anreicherung, kein Fehler
                continue;
            }

            if ($old === $new) continue;                 // identisch

            $discrepancies += $this->record($existing, $field, $old, $new, $source);
        }

        if ($enrich) $existing->update($enrich);

        return $discrepancies;
    }

    /**
     * Legt eine offene Abweichung an. Dieselbe Abweichung entsteht durch den
     * Unique-Index pro Crawl-Lauf nur einmal.
     */
    private function record(
        CompetitionResult $result,
        string $field,
        string $oldValue,
        string $newValue,
        string $source
    ): int {
        $sourceA = $result->source ?: 'unbekannt';

        // Bereits als erledigt markierte Abweichung nicht wieder aufreissen
        $already = ResultDiscrepancy::where('competition_result_id', $result->id)
            ->where('field', $field)
            ->where('source_a', $sourceA)
            ->where('source_b', $source)
            ->first();

        if ($already) {
            // Wert hat sich geaendert → erneut zur Pruefung oeffnen
            if ($already->value_a !== $oldValue || $already->value_b !== $newValue) {
                $already->update([
                    'value_a'     => $oldValue,
                    'value_b'     => $newValue,
                    'resolved_at' => null,
                    'message'     => $this->message($field, $sourceA, $oldValue, $source, $newValue),
                ]);
                return 1;
            }
            return 0;
        }

        ResultDiscrepancy::create([
            'competition_id'        => $result->competition_id,
            'user_id'               => $result->user_id,
            'competition_result_id' => $result->id,
            'discipline'            => $result->discipline,
            'distance'              => $result->distance,
            'field'                 => $field,
            'source_a'              => $sourceA,
            'value_a'               => $oldValue,
            'source_b'              => $source,
            'value_b'               => $newValue,
            'message'               => $this->message($field, $sourceA, $oldValue, $source, $newValue),
        ]);

        return 1;
    }

    private function message(string $field, string $srcA, string $valA, string $srcB, string $valB): string
    {
        $label = (new ResultDiscrepancy(['field' => $field]))->field_label;

        return sprintf(
            '%s weicht ab: %s meldet "%s", %s meldet "%s".',
            $label,
            $this->sourceLabel($srcA), $this->display($field, $valA),
            $this->sourceLabel($srcB), $this->display($field, $valB)
        );
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'webclub_crawler', 'webclub_batch' => 'WebClub',
            'dsvdata'                          => 'DSV',
            'dsv7', 'manual'                   => 'DSV7-Import',
            default                            => $source,
        };
    }

    /** Zeiten lesbar ausgeben, Boolesche als Ja/Nein. */
    private function display(string $field, string $value): string
    {
        if ($field === 'time_ms' && is_numeric($value)) {
            $ms = (int) $value;
            return sprintf('%d:%05.2f', intdiv($ms, 60000), ($ms % 60000) / 1000);
        }
        if (in_array($field, self::FLAG_FIELDS, true)) {
            return $value === '1' ? 'ja' : 'nein';
        }
        return $value;
    }

    /** Leere Werte werden zu null, damit Anreicherung von Konflikt unterscheidbar bleibt. */
    private function normalize(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        // Muss VOR der is_bool-Behandlung stehen: bei Flags bedeutet false/0
        // "nicht gesetzt", nicht "ausdrücklich nein". Sonst meldet eine Quelle,
        // die eine PB schlicht nicht kennt, einen Konflikt gegen eine Quelle,
        // die sie kennt – genau der Fall, in dem WebClub zuverlässiger ist.
        if (in_array($field, self::FLAG_FIELDS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : null;
        }

        if (is_bool($value)) return $value ? '1' : '0';

        return (string) $value;
    }
}
