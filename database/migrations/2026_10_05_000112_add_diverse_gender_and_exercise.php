<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Drittes Geschlecht "divers" (D) – im DSV-Standard 8 eingeführt.
 *    Die Enum-Spalten bekommen den Wert D; char(1)-Spalten können ihn schon.
 *    WA-Punktetabellen bleiben M/F: World Aquatics hat keine Tabelle für divers.
 *
 * 2. Übungsform (DSV "Ausübung": Beine, Arme, Kicks …) an Wettkampf und
 *    Ergebnis. null = ganze Lage. Solche Zeiten werden übernommen und
 *    markiert, zählen aber nicht als Zeit der Lage.
 */
return new class extends Migration
{
    private const ENUMS = [
        // Tabelle => [Spalte, Werte vorher, nullable, Default]
        'users'                     => ["'M','F'", "'M','F','D'", true, null],
        'competition_events'        => ["'M','F','X'", "'M','F','D','X'", false, 'X'],
        'athletes'                  => ["'M','F','X'", "'M','F','D','X'", false, null],
        'records'                   => ["'M','F'", "'M','F','D'", false, null],
        'best_list_entries'         => ["'M','F'", "'M','F','D'", false, null],
        'competition_relay_entries' => ["'M','F','mixed'", "'M','F','D','mixed'", false, null],
    ];

    public function up(): void
    {
        foreach (self::ENUMS as $table => [, $after, $nullable, $default]) {
            $this->setEnum($table, $after, $nullable, $default);
        }

        foreach (['competition_events', 'competition_results', 'ext_competition_results'] as $table) {
            if (!Schema::hasColumn($table, 'exercise')) {
                Schema::table($table, function ($t) {
                    $t->string('exercise', 2)->nullable()->after('distance');
                });
            }
        }

        // Fremdergebnisse: 25 m Schmetterling und 25 m Schmetterling Kicks sind zwei Ergebnisse
        // Erst den neuen Index anlegen: der alte dient dem Fremdschlüssel auf competition_id
        if (!Schema::hasIndex('ext_competition_results', 'uq_ext_result_ex')) {
            Schema::table('ext_competition_results', fn($t) => $t->unique(
                ['competition_id', 'athlete_id', 'discipline', 'distance', 'exercise', 'age_group'], 'uq_ext_result_ex'));
        }
        if (Schema::hasIndex('ext_competition_results', 'uq_ext_result')) {
            Schema::table('ext_competition_results', fn($t) => $t->dropUnique('uq_ext_result'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ext_competition_results', 'exercise')) {
            DB::table('ext_competition_results')->whereNotNull('exercise')->delete();
        }
        if (!Schema::hasIndex('ext_competition_results', 'uq_ext_result')) {
            Schema::table('ext_competition_results', fn($t) => $t->unique(
                ['competition_id', 'athlete_id', 'discipline', 'distance', 'age_group'], 'uq_ext_result'));
        }
        if (Schema::hasIndex('ext_competition_results', 'uq_ext_result_ex')) {
            Schema::table('ext_competition_results', fn($t) => $t->dropUnique('uq_ext_result_ex'));
        }

        foreach (['competition_events', 'competition_results', 'ext_competition_results'] as $table) {
            if (Schema::hasColumn($table, 'exercise')) {
                Schema::table($table, fn($t) => $t->dropColumn('exercise'));
            }
        }

        // Divers vor dem Zurückbauen auf "unbekannt" setzen, sonst scheitert das Enum
        DB::table('users')->where('gender', 'D')->update(['gender' => null]);
        foreach (['competition_events', 'athletes'] as $t) {
            DB::table($t)->where('gender', 'D')->update(['gender' => 'X']);
        }
        DB::table('records')->where('gender', 'D')->delete();
        DB::table('best_list_entries')->where('gender', 'D')->delete();
        DB::table('competition_relay_entries')->where('gender', 'D')->update(['gender' => 'mixed']);

        foreach (self::ENUMS as $table => [$before, , $nullable, $default]) {
            $this->setEnum($table, $before, $nullable, $default);
        }
    }

    private function setEnum(string $table, string $values, bool $nullable, ?string $default): void
    {
        if (DB::getDriverName() !== 'mysql') return; // SQLite kennt keine Enums

        $sql = "ALTER TABLE `{$table}` MODIFY `gender` ENUM({$values}) "
            . ($nullable ? 'NULL' : 'NOT NULL')
            . ($default !== null ? " DEFAULT '{$default}'" : '');
        DB::statement($sql);
    }
};
