<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_competition_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('athlete_id');
            $table->string('discipline', 20);
            $table->unsignedSmallInteger('distance');
            $table->unsignedInteger('time_ms')->nullable();
            $table->enum('status', ['OK', 'DNS', 'DNF', 'DQ'])->default('OK');
            $table->unsignedSmallInteger('placement')->nullable();
            $table->string('age_group', 50)->nullable();
            $table->char('gender', 1)->nullable();
            $table->boolean('is_final')->default(false);
            $table->unsignedSmallInteger('dsv_points')->nullable();

            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->foreign('athlete_id')->references('id')->on('athletes');

            $table->unique(['competition_id', 'athlete_id', 'discipline', 'distance', 'age_group'], 'uq_ext_result');
            $table->index(['athlete_id', 'discipline', 'distance'], 'idx_ext_athlete_discipline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_competition_results');
    }
};
