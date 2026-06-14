<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_scores', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedSmallInteger('podiums')->default(0);
            $table->unsignedSmallInteger('finals')->default(0);
            $table->unsignedSmallInteger('personal_bests')->default(0);
            $table->unsignedSmallInteger('club_records')->default(0);
            $table->dateTime('recalculated_at')->useCurrent();

            $table->primary(['athlete_id', 'season_year']);
            $table->foreign('athlete_id')->references('id')->on('athletes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_scores');
    }
};
