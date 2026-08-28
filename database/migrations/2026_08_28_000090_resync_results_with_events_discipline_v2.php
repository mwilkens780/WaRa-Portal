<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nach dem 1825-Tage-Crawler-Lauf sind jetzt ALLE WebClub-Wettkämpfe mit
     * webclub_event_id belegt und competition_events auf das korrekte Mapping
     * (4=F, 5=L) aktualisiert. Migration 000089 lief VOR dem Crawler-Lauf und
     * konnte daher nur Wettkämpfe fixen, bei denen competition_events schon
     * korrekt waren.
     *
     * Diese Migration führt den gleichen Event-Abgleich erneut durch – jetzt
     * mit den korrekten competition_events als Ground Truth.
     *
     * Logik: Tausche discipline in competition_results, wenn
     *   - kein competition_event mit gleicher competition_id+distance+discipline existiert
     *   - aber ein Event mit der getauschten Disziplin (F↔L) für dieselbe Distanz vorhanden ist
     */
    public function up(): void
    {
        // Nach 1825-Tage-Crawler sind alle WebClub-Wettkämpfe mit webclub_event_id belegt
        $idList = DB::table('competitions')
            ->whereNotNull('webclub_event_id')
            ->pluck('id')
            ->implode(',');

        if (!$idList) return;

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
        // Nicht rückgängig machbar ohne Backup
    }
};
