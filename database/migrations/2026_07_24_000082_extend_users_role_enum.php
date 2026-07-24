<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','trainer','schwimmer','elternteil','kampfrichter','vorstand','ernaehrungsberater','teamarzt') DEFAULT 'schwimmer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','trainer','schwimmer','elternteil','kampfrichter','vorstand') DEFAULT 'schwimmer'");
    }
};
