<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_events', 'qualifying_time_ms')) {
                $table->unsignedInteger('qualifying_time_ms')->nullable()->after('age_group');
            }
            if (!Schema::hasColumn('competition_events', 'meldegeld')) {
                $table->decimal('meldegeld', 8, 2)->nullable()->after('qualifying_time_ms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn(['qualifying_time_ms', 'meldegeld']);
        });
    }
};
