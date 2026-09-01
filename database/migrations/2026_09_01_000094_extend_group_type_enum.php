<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE training_groups MODIFY COLUMN group_type ENUM(
            'leistungssport',
            'masters',
            'nachwuchssport',
            'breitensport',
            'triathlon',
            'kurse',
            'synchronschwimmen',
            'dlrg'
        ) NOT NULL DEFAULT 'breitensport'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE training_groups MODIFY COLUMN group_type ENUM(
            'leistungssport',
            'breitensport',
            'triathlon',
            'synchronschwimmen'
        ) NOT NULL DEFAULT 'breitensport'");
    }
};
