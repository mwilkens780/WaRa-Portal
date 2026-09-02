<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_blocks', function (Blueprint $table) {
            $table->unsignedTinyInteger('lanes_per_wave')->nullable()->after('time_tracking');
            $table->unsignedSmallInteger('wave_gap_cs')->nullable()->after('lanes_per_wave');
            $table->json('athlete_order')->nullable()->after('wave_gap_cs');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_blocks', function (Blueprint $table) {
            $table->dropColumn(['lanes_per_wave', 'wave_gap_cs', 'athlete_order']);
        });
    }
};
