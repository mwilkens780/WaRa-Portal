<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migration 000087 hat L↔F nur für Wettkämpfe mit webclub_event_id IS NOT NULL gepatcht.
        // Ältere WebClub-Wettkämpfe (vor ~Mai 2026, außerhalb des 90-Tage-Lookback) wurden nie
        // erneut gecrawlt und haben daher webclub_event_id = NULL, obwohl sie via WebClub-Crawler
        // importiert wurden. Diese werden hier über die import_log-Tabelle identifiziert.

        $ids = DB::table('import_log')
            ->where('source', 'webclub_crawler')
            ->whereNotNull('competition_id')
            ->join('competitions', 'competitions.id', '=', 'import_log.competition_id')
            ->whereNull('competitions.webclub_event_id')
            ->distinct()
            ->pluck('import_log.competition_id');

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

    public function down(): void
    {
        $ids = DB::table('import_log')
            ->where('source', 'webclub_crawler')
            ->whereNotNull('competition_id')
            ->join('competitions', 'competitions.id', '=', 'import_log.competition_id')
            ->whereNull('competitions.webclub_event_id')
            ->distinct()
            ->pluck('import_log.competition_id');

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
