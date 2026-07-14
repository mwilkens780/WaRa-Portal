<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE import_log MODIFY COLUMN source ENUM('shsv','nsv','dsv','dsvdata','webclub_batch','webclub_crawler','manual') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE import_log MODIFY COLUMN source ENUM('shsv','nsv','dsv','dsvdata','webclub_batch','manual') NOT NULL");
    }
};
