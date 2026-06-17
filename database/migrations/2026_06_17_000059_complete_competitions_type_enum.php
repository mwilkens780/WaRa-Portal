<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // All values from Competition::TYPE_LABELS — must stay in sync with that constant.
    private const ALL_TYPES = "ENUM('vereinsintern','regional','national','international','meisterschaften','einladung','nop','dms','shsv')";

    public function up(): void
    {
        DB::statement("ALTER TABLE competitions MODIFY COLUMN `type` " . self::ALL_TYPES . " NOT NULL DEFAULT 'regional'");
    }

    public function down(): void
    {
        // Revert to the state left by 2024_01_05_000001_extend_competition_type_enum
        DB::statement("ALTER TABLE competitions MODIFY COLUMN `type` ENUM('vereinsintern','regional','national','international','meisterschaften','einladung') NOT NULL DEFAULT 'regional'");
    }
};
