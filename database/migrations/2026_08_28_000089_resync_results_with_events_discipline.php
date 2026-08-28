<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Gleicht competition_results mit den (jetzt korrekten) competition_events ab.
     *
     * Nach dem 1825-Tage-Crawler-Lauf mit fixiertem Disziplin-Mapping (4→F, 5→L) hat
     * syncCompetitionEvents() alle competition_events per updateOrCreate aktualisiert.
     * competition_results wurden dabei nicht angefasst (nur INSERT, kein UPDATE).
     *
     * Diese Migration prüft: hat ein competition_result discipline='L' (oder 'F'), aber
     * das zugehörige competition_event für dieselbe competition+distance sagt 'F' (oder 'L')
     * und es gibt kein Event mit der alten Disziplin – dann ist das Ergebnis noch falsch.
     *
     * Konservative Logik: Tausch nur wenn das Ergebnis auf ein nicht-existierendes Event
     * zeigt, aber ein Event mit getauschter Disziplin für dieselbe Distanz existiert.
     */
    public function up(): void
    {
        // Alle WebClub-Wettkämpfe (jetzt vollständig mit webclub_event_id nach Crawler-Lauf)
        $webclubIds = DB::table('competitions')->whereNotNull('webclub_event_id')->pluck('id');

        // Zusätzlich: via import_log gefundene, falls noch ohne webclub_event_id
        $logIds = DB::table('import_log')
            ->whereIn('source', ['webclub_crawler'])
            ->whereNotNull('competition_id')
            ->distinct()
            ->pluck('competition_id');

        $competitionIds = $webclubIds->merge($logIds)->unique()->values();

        if ($competitionIds->isEmpty()) return;

        $idList = $competitionIds->implode(',');

        // Finde alle competition_results, bei denen discipline='L' oder 'F',
        // aber kein competition_event mit gleicher competition_id+distance+discipline existiert,
        // JEDOCH ein Event mit der jeweils anderen Disziplin (F↔L) vorhanden ist.
        // In diesem Fall ist das Ergebnis ein Überbleibsel des alten falschen Mappings.
        DB::statement("
            UPDATE competition_results cr
            JOIN competition_events ce_swap
                ON ce_swap.competition_id = cr.competition_id
               AND ce_swap.distance       = cr.distance
               AND ce_swap.discipline     = CASE cr.discipline WHEN 'L' THEN 'F' WHEN 'F' THEN 'L' END
            LEFT JOIN competition_events ce_own
                ON ce_own.competition_id = cr.competition_id
               AND ce_own.distance       = cr.distance
               AND ce_own.discipline     = cr.discipline
            SET cr.discipline = CASE cr.discipline WHEN 'L' THEN 'F' WHEN 'F' THEN 'L' END
            WHERE cr.competition_id IN ({$idList})
              AND cr.discipline IN ('L', 'F')
              AND ce_own.id IS NULL
        ");
    }

    public function down(): void
    {
        // Nicht rückgängig machbar ohne Backup der Originaldaten
    }
};
