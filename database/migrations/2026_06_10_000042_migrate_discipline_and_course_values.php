<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrates all discipline and course values to the new canonical DSV codes.
 *
 * Discipline: freistil→F, brust→B, ruecken→R, schmetterling→S, lagen→L
 * Course:     LCM→Langbahn, SCM→Kurzbahn (all tables + competitions.course varchar widened)
 *
 * Strategy: convert affected columns to VARCHAR, UPDATE data, then restore to ENUM.
 * This avoids MySQL rejecting the ALTER when existing rows still hold old enum values.
 */
return new class extends Migration
{
    private const DISC_TABLES = [
        'swimming_times',
        'competition_results',
        'competition_events',
        'records',
    ];

    public function up(): void
    {
        // ── 1. Widen competitions.course varchar(3) → varchar(10) ─────────────
        DB::statement("ALTER TABLE competitions MODIFY COLUMN course VARCHAR(10) NULL");

        // ── 2. Discipline columns ──────────────────────────────────────────────
        foreach (self::DISC_TABLES as $table) {
            // Step 2a: relax to VARCHAR so existing old values are acceptable
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `discipline` VARCHAR(20) NOT NULL");
            // Step 2b: UPDATE old → new codes
            DB::statement("UPDATE `{$table}` SET `discipline` = CASE `discipline`
                WHEN 'freistil'      THEN 'F'
                WHEN 'brust'         THEN 'B'
                WHEN 'ruecken'       THEN 'R'
                WHEN 'schmetterling' THEN 'S'
                WHEN 'lagen'         THEN 'L'
                ELSE `discipline` END");
            // Step 2c: re-apply ENUM constraint with new values
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `discipline` ENUM('F','B','R','S','L') NOT NULL");
        }

        // ── 3. Course: competitions (plain VARCHAR) ────────────────────────────
        DB::statement("UPDATE `competitions` SET `course` = CASE `course`
            WHEN 'LCM' THEN 'Langbahn'
            WHEN 'SCM' THEN 'Kurzbahn'
            ELSE `course` END
            WHERE `course` IS NOT NULL");

        // ── 4. Course: records ENUM ────────────────────────────────────────────
        DB::statement("ALTER TABLE `records` MODIFY COLUMN `course` VARCHAR(10) NOT NULL DEFAULT 'Langbahn'");
        DB::statement("UPDATE `records` SET `course` = CASE `course`
            WHEN 'LCM' THEN 'Langbahn'
            WHEN 'SCM' THEN 'Kurzbahn'
            ELSE `course` END");
        DB::statement("ALTER TABLE `records` MODIFY COLUMN `course` ENUM('Langbahn','Kurzbahn') NOT NULL DEFAULT 'Langbahn'");

        // ── 5. Course: swimmer_goals ENUM ─────────────────────────────────────
        if (Schema::hasColumn('swimmer_goals', 'course')) {
            DB::statement("ALTER TABLE `swimmer_goals` MODIFY COLUMN `course` VARCHAR(10) NULL");
            DB::statement("UPDATE `swimmer_goals` SET `course` = CASE `course`
                WHEN 'LCM' THEN 'Langbahn'
                WHEN 'SCM' THEN 'Kurzbahn'
                ELSE `course` END
                WHERE `course` IS NOT NULL");
            DB::statement("ALTER TABLE `swimmer_goals` MODIFY COLUMN `course` ENUM('Kurzbahn','Langbahn') NULL");
        }
    }

    public function down(): void
    {
        // Revert discipline columns
        foreach (self::DISC_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `discipline` VARCHAR(20) NOT NULL");
            DB::statement("UPDATE `{$table}` SET `discipline` = CASE `discipline`
                WHEN 'F' THEN 'freistil'
                WHEN 'B' THEN 'brust'
                WHEN 'R' THEN 'ruecken'
                WHEN 'S' THEN 'schmetterling'
                WHEN 'L' THEN 'lagen'
                ELSE `discipline` END");
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `discipline`
                ENUM('freistil','brust','ruecken','schmetterling','lagen') NOT NULL");
        }

        // Revert course columns
        DB::statement("UPDATE `competitions` SET `course` = CASE `course`
            WHEN 'Langbahn' THEN 'LCM'
            WHEN 'Kurzbahn' THEN 'SCM'
            ELSE `course` END
            WHERE `course` IS NOT NULL");
        DB::statement("ALTER TABLE `competitions` MODIFY COLUMN `course` VARCHAR(3) NULL");

        DB::statement("ALTER TABLE `records` MODIFY COLUMN `course` VARCHAR(10) NOT NULL DEFAULT 'LCM'");
        DB::statement("UPDATE `records` SET `course` = CASE `course`
            WHEN 'Langbahn' THEN 'LCM'
            WHEN 'Kurzbahn' THEN 'SCM'
            ELSE `course` END");
        DB::statement("ALTER TABLE `records` MODIFY COLUMN `course` ENUM('LCM','SCM') NOT NULL DEFAULT 'LCM'");

        if (Schema::hasColumn('swimmer_goals', 'course')) {
            DB::statement("ALTER TABLE `swimmer_goals` MODIFY COLUMN `course` VARCHAR(10) NULL");
            DB::statement("UPDATE `swimmer_goals` SET `course` = CASE `course`
                WHEN 'Langbahn' THEN 'LCM'
                WHEN 'Kurzbahn' THEN 'SCM'
                ELSE `course` END
                WHERE `course` IS NOT NULL");
            DB::statement("ALTER TABLE `swimmer_goals` MODIFY COLUMN `course` ENUM('SCM','LCM') NULL");
        }
    }
};
