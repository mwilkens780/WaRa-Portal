<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leistungskriterien (training_group_goals) werden pro Saison bewertet.
 *
 * Die Erfuellung entscheidet ueber Verbleib oder Wechsel der Trainingsgruppe
 * und muss jede Saison neu erbracht werden. Bisher gab es pro Schwimmer und
 * Kriterium genau eine Bewertung fuer alle Zeiten.
 *
 * Ausserdem: Bewertung ist nur noch erreicht / nicht erreicht. Die alten
 * Spalten rating (1-5) und current_value bleiben stehen, damit nichts verloren
 * geht, werden aber nicht mehr beschrieben.
 *
 * Und: Die "Teamziele" aus group_goals werden als Leistungskriterien
 * uebernommen. Sie wurden auf der Ziele-Seite angelegt, landeten aber in einer
 * anderen Tabelle als die bewerteten Kriterien und tauchten deshalb nie in der
 * Bewertung auf. group_goals selbst bleibt unangetastet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_group_goal_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('training_group_goal_evaluations', 'season_id')) {
                $table->foreignId('season_id')->nullable()->after('user_id')
                      ->constrained('seasons')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('training_group_goal_evaluations', 'achieved')) {
                $table->boolean('achieved')->nullable()->after('evaluation_type');
            }
        });

        // ── Saison nachtragen: anhand des Bewertungsdatums ───────────────────
        DB::statement('
            UPDATE training_group_goal_evaluations e
            JOIN seasons s ON e.evaluated_at BETWEEN s.start_date AND s.end_date
            SET e.season_id = s.id
            WHERE e.season_id IS NULL
        ');

        // Datum in einer Luecke zwischen zwei Saisons: die zuletzt begonnene
        DB::statement('
            UPDATE training_group_goal_evaluations e
            SET e.season_id = (
                SELECT s.id FROM seasons s
                WHERE s.start_date <= e.evaluated_at
                ORDER BY s.start_date DESC LIMIT 1
            )
            WHERE e.season_id IS NULL
        ');

        // Vor der ersten Saison bewertet: der ersten Saison zuordnen
        $first = DB::table('seasons')->orderBy('start_date')->value('id');
        if ($first) {
            DB::table('training_group_goal_evaluations')
                ->whereNull('season_id')
                ->update(['season_id' => $first]);
        }

        // ── Sterne in ja/nein uebersetzen ────────────────────────────────────
        // 5 = "Erreicht" bzw. "Erfuellt", alles darunter war noch nicht erreicht.
        DB::table('training_group_goal_evaluations')
            ->whereNull('achieved')->where('rating', 5)
            ->update(['achieved' => true]);
        DB::table('training_group_goal_evaluations')
            ->whereNull('achieved')->whereBetween('rating', [1, 4])
            ->update(['achieved' => false]);

        // ── Eindeutigkeit pro Saison ─────────────────────────────────────────
        // Neuen Index zuerst anlegen: Er beginnt ebenfalls mit
        // training_group_goal_id und traegt damit den Fremdschluessel, sodass
        // MySQL den alten Index freigibt.
        Schema::table('training_group_goal_evaluations', function (Blueprint $table) {
            $table->unique(
                ['training_group_goal_id', 'user_id', 'evaluation_type', 'season_id'],
                'goal_eval_season_unique'
            );
        });
        Schema::table('training_group_goal_evaluations', function (Blueprint $table) {
            $table->dropUnique('goal_eval_unique');
        });

        // ── Teamziele als Leistungskriterien uebernehmen ─────────────────────
        if (Schema::hasTable('group_goals')) {
            $teamGoals = DB::table('group_goals')
                ->orderBy('training_group_id')->orderBy('created_at')->get();

            foreach ($teamGoals as $tg) {
                // Gleicher Titel in derselben Gruppe (z.B. aus mehreren Saisons) nur einmal
                $exists = DB::table('training_group_goals')
                    ->where('training_group_id', $tg->training_group_id)
                    ->where('title', $tg->title)
                    ->exists();
                if ($exists) continue;

                $sort = (int) DB::table('training_group_goals')
                    ->where('training_group_id', $tg->training_group_id)
                    ->max('sort_order');

                DB::table('training_group_goals')->insert([
                    'training_group_id' => $tg->training_group_id,
                    'title'             => $tg->title,
                    'description'       => $tg->description,
                    'type'              => $tg->target_count ? 'quantitative' : 'qualitative',
                    'target_value'      => $tg->target_count ? (string) $tg->target_count : null,
                    'sort_order'        => $sort + 1,
                    'active'            => true,
                    'created_at'        => $tg->created_at ?? now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Uebernommene Teamziele bleiben stehen - sie sind von echten
        // Leistungskriterien nicht mehr zu unterscheiden.
        Schema::table('training_group_goal_evaluations', function (Blueprint $table) {
            $table->unique(['training_group_goal_id', 'user_id', 'evaluation_type'], 'goal_eval_unique');
        });
        Schema::table('training_group_goal_evaluations', function (Blueprint $table) {
            $table->dropUnique('goal_eval_season_unique');
            $table->dropConstrainedForeignId('season_id');
            $table->dropColumn('achieved');
        });
    }
};
