<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE competitions MODIFY COLUMN `type` ENUM('vereinsintern','regional','national','international','meisterschaften','einladung') NOT NULL DEFAULT 'regional'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE competitions MODIFY COLUMN `type` ENUM('vereinsintern','regional','national','international') NOT NULL DEFAULT 'regional'");
    }
};
