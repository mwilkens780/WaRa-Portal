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
        // Beweis: wkfLAGE=4 erscheint für 1500m, 5000m, 800m → nur Freistil existiert
        // für diese Distanzen. L↔F-Tausch für alle WebClub-importierten Wettkämpfe.
        // DSV7-/manuell importierte Wettkämpfe (webclub_competition_id IS NULL) werden
        // nicht angefasst.

        $webclubCompIds = DB::table('competitions')
            ->whereNotNull('webclub_competition_id')
            ->pluck('id');

        if ($webclubCompIds->isEmpty()) return;

        // Temporäres Zeichen für den Tausch: L→X, F→L, X→F
        foreach (['competition_events', 'competition_results', 'competition_entries'] as $table) {
            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'L')
                ->update(['discipline' => 'X']);

            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'F')
                ->update(['discipline' => 'L']);

            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'X')
                ->update(['discipline' => 'F']);
        }
    }

    public function down(): void
    {
        // Rückgängig: F↔L erneut tauschen für WebClub-Wettkämpfe
        $webclubCompIds = DB::table('competitions')
            ->whereNotNull('webclub_competition_id')
            ->pluck('id');

        if ($webclubCompIds->isEmpty()) return;

        foreach (['competition_events', 'competition_results', 'competition_entries'] as $table) {
            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'F')
                ->update(['discipline' => 'X']);

            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'L')
                ->update(['discipline' => 'F']);

            DB::table($table)
                ->whereIn('competition_id', $webclubCompIds)
                ->where('discipline', 'X')
                ->update(['discipline' => 'L']);
        }
    }
};
