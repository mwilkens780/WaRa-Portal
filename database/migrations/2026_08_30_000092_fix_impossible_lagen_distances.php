<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * In competitive swimming, Lagen (IM) only exists at distances 100, 200, 400.
     * Any record with discipline='L' and distance NOT IN (100, 200, 400) is
     * factually impossible and must be Freistil (F) — a leftover from the
     * L↔F mapping bug in the WebClub crawler that previous migrations missed.
     */
    public function up(): void
    {
        foreach (['competition_events', 'competition_results', 'competition_entries'] as $table) {
            DB::statement("
                UPDATE {$table}
                SET discipline = 'F'
                WHERE discipline = 'L'
                  AND distance NOT IN (100, 200, 400)
            ");
        }
    }

    public function down(): void
    {
        // Not reversible without a backup — these records were factually wrong
    }
};
