<?php

namespace App\Services\Import;

/**
 * Ordnet die Rohtexte des Hallenbelegungsplans den Stammdaten zu.
 *
 * Die Vorlage und das Portal benennen dieselben Dinge unterschiedlich:
 * dort "LG", hier "Leistungsgruppe (LG)"; dort "JUNS", hier "(JUN S)".
 * Trainer stehen in der Vorlage nur mit Vornamen, teils zu zweit
 * ("Ramona/Viggo", "Müge und Carmen") und mit Zusaetzen wie "FSJ".
 *
 * Nicht zuzuordnende Eintraege werden markiert, nie geraten – die Auswahl
 * trifft der Nutzer im Vorschau-Screen.
 */
class HallPlanMatcher
{
    /** @var array<int,string> id => name */
    private array $groups;
    /** @var array<int,array{firstname:string,lastname:string}> */
    private array $trainers;

    /** @var array<string,int> Suchschluessel => group_id */
    private array $groupIndex = [];
    /** @var array<string,list<int>> Vorname => user_ids */
    private array $trainerIndex = [];

    public function __construct(array $groups, array $trainers)
    {
        $this->groups   = $groups;
        $this->trainers = $trainers;
        $this->buildIndexes();
    }

    /**
     * Reichert die Eintraege des Parsers um Zuordnungen an.
     *
     * @param  list<array> $entries
     * @return list<array>
     */
    public function match(array $entries): array
    {
        foreach ($entries as &$entry) {
            [$groupIds, $groupUnresolved] = $this->matchGroups($entry['group_raw'] ?? '');
            $entry['group_ids']        = $groupIds;
            $entry['group_unresolved'] = $groupUnresolved;

            $trainerIds = $unresolvedTrainers = $ambiguous = [];
            foreach ($entry['trainers_raw'] ?? [] as $raw) {
                foreach ($this->splitPeople($raw) as $name) {
                    $hits = $this->trainerIndex[$this->key($this->stripSuffix($name))] ?? [];
                    if (count($hits) === 1)      $trainerIds[] = $hits[0];
                    elseif (count($hits) > 1)    $ambiguous[]  = $name;
                    else                          $unresolvedTrainers[] = $name;
                }
            }

            $entry['trainer_ids']        = array_values(array_unique($trainerIds));
            $entry['trainer_unresolved'] = array_values(array_unique($unresolvedTrainers));
            $entry['trainer_ambiguous']  = array_values(array_unique($ambiguous));

            // Eine Trainingsserie braucht Gruppe UND Trainer. Fehlt eines,
            // bleibt es bei der reinen Belegung, bis der Nutzer ergaenzt.
            $entry['session_ready'] = ($entry['needs_session'] ?? false)
                && $entry['group_ids'] !== []
                && $entry['trainer_ids'] !== [];
        }

        return $entries;
    }

    // ── Gruppen ─────────────────────────────────────────────────────────────

    /** @return array{0:list<int>,1:list<string>} gefundene IDs, nicht zuordenbare Teile */
    private function matchGroups(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [[], []];

        $ids = $open = [];
        foreach ($this->splitGroups($raw) as $part) {
            $id = $this->groupIndex[$this->key($part)] ?? null;
            if ($id !== null) $ids[] = $id; else $open[] = $part;
        }

        return [array_values(array_unique($ids)), array_values(array_unique($open))];
    }

    /** "LG/WG", "WG+LG" nennen zwei Gruppen in einer Zelle. */
    private function splitGroups(string $raw): array
    {
        $parts = preg_split('#\s*[/+]\s*#u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
    }

    // ── Trainer ─────────────────────────────────────────────────────────────

    /** "Ramona/Viggo", "Müge und Carmen", "Kim, Sandra" → einzelne Namen. */
    private function splitPeople(string $raw): array
    {
        $parts = preg_split('#\s*(?:/|,|\+|\bund\b)\s*#ui', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
    }

    /** Zusaetze wie "FSJ" gehoeren nicht zum Namen. */
    private function stripSuffix(string $name): string
    {
        return trim(preg_replace('/\s*\b(FSJ|BFD|Prakt(ikant)?in?)\b\.?\s*$/ui', '', $name));
    }

    // ── Indizes ─────────────────────────────────────────────────────────────

    private function buildIndexes(): void
    {
        foreach ($this->groups as $id => $name) {
            $this->groupIndex[$this->key($name)] = $id;

            // Kuerzel in Klammern: "Leistungsgruppe (LG)" ist in der Vorlage "LG"
            if (preg_match('/\(([^)]+)\)/u', $name, $m)) {
                $this->groupIndex[$this->key($m[1])] = $id;
            }
            // Langform ohne Klammerzusatz
            $plain = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name));
            if ($plain !== '') $this->groupIndex[$this->key($plain)] ??= $id;

            // Einzahl/Mehrzahl: die Vorlage schreibt "Orca", das Portal "Orcas"
            $singular = preg_replace('/s$/u', '', $this->key($name));
            if ($singular !== '') $this->groupIndex[$singular] ??= $id;
        }

        foreach ($this->trainers as $id => $person) {
            $first = trim($person['firstname'] ?? '');
            if ($first === '') continue;

            $this->trainerIndex[$this->key($first)][] = $id;

            // "Kim Kyra" wird in der Vorlage als "Kim" gefuehrt
            $firstWord = preg_split('/\s+/', $first)[0] ?? '';
            if ($firstWord !== '' && $firstWord !== $first) {
                $this->trainerIndex[$this->key($firstWord)][] = $id;
            }
        }
    }

    /**
     * Vergleichsschluessel: ohne Gross-/Kleinschreibung, ohne Leer- und
     * Sonderzeichen. Damit greifen "JUN S"/"JUNS" und "SEN 1"/"SEN1"
     * gleichermassen.
     */
    private function key(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = strtr($v, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return preg_replace('/[^a-z0-9]/u', '', $v) ?? '';
    }
}
