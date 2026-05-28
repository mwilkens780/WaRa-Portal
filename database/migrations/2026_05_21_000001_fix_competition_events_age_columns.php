<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            // DSV7 stores birth-year ranges (e.g. 2010, 9999) — TINYINT (0-255) overflows.
            // SMALLINT UNSIGNED handles 0-65535 which covers all practical birth years.
            $table->unsignedSmallInteger('age_min')->nullable()->change();
            $table->unsignedSmallInteger('age_max')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('age_min')->nullable()->change();
            $table->unsignedTinyInteger('age_max')->nullable()->change();
        });
    }
};
