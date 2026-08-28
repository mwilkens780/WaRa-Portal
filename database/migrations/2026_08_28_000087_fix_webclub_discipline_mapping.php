<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // WebClub-Disziplincodes 4 und 5 waren in der Mapping-Tabelle vertauscht:
        //   Falsch: 4→L (Lagen), 5→F (Freistil)
        //   Korrekt: 4→F (Freistil), 5→L (Lagen)
        //
        // Beweis: wkfLAGE=4 erscheint für 1500m, 5000m, 800m – nur Freistil existiert
        // für diese Distanzen. L↔F-Tausch für alle WebClub-importierten Wettkämpfe.

        $ids = DB::table('competitions')
            ->whereNotNull('webclub_event_id')
            ->pluck('id');

        if ($ids->isEmpty()) return;

        // CASE-Statement tauscht L und F in einem einzigen UPDATE (kein Enum-Zwischenwert nötig)
        foreach (['competition_events', 'competition_results', 'competition_entries'] as $table) {
            DB::statement("
                UPDATE `{$table}`
                SET discipline = CASE discipline
                    WHEN 'L' THEN 'F'
                    WHEN 'F' THEN 'L'
                    ELSE discipline
                END
                WHERE competition_id IN (" . $ids->implode(',') . ")
                  AND discipline IN ('L', 'F')
            ");
        }
    }

    public function down(): void
    {
        // Rückgängig: identischer Tausch F↔L
        $ids = DB::table('competitions')
            ->whereNotNull('webclub_event_id')
            ->pluck('id');

        if ($ids->isEmpty()) return;

        foreach (['competition_events', 'competition_results', 'competition_entries'] as $table) {
            DB::statement("
                UPDATE `{$table}`
                SET discipline = CASE discipline
                    WHEN 'L' THEN 'F'
                    WHEN 'F' THEN 'L'
                    ELSE discipline
                END
                WHERE competition_id IN (" . $ids->implode(',') . ")
                  AND discipline IN ('L', 'F')
            ");
        }
    }
};
