<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->date('qualifying_period_start')->nullable()->after('deadline');
            $table->date('qualifying_period_end')->nullable()->after('qualifying_period_start');
        });

        Schema::table('competition_events', function (Blueprint $table) {
            $table->date('qualifying_deadline')->nullable()->after('qualifying_time_ms');
        });
    }

    public function down(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->dropColumn(['qualifying_period_start', 'qualifying_period_end']);
        });
        Schema::table('competition_events', function (Blueprint $table) {
            $table->dropColumn('qualifying_deadline');
        });
    }
};
