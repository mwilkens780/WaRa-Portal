<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->string('discipline', 20);
            $table->unsignedSmallInteger('distance');
            $table->string('club_name', 200);
            $table->unsignedInteger('time_ms')->nullable();
            $table->enum('status', ['OK', 'DNS', 'DNF', 'DQ'])->default('OK');
            $table->unsignedSmallInteger('placement')->nullable();
            $table->string('age_group', 50)->nullable();
            $table->char('gender', 1)->nullable();

            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
        });

        Schema::create('relay_members', function (Blueprint $table) {
            $table->unsignedBigInteger('relay_result_id');
            $table->unsignedBigInteger('athlete_id');
            $table->unsignedTinyInteger('leg');

            $table->primary(['relay_result_id', 'leg']);
            $table->foreign('relay_result_id')->references('id')->on('relay_results')->cascadeOnDelete();
            $table->foreign('athlete_id')->references('id')->on('athletes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_members');
        Schema::dropIfExists('relay_results');
    }
};
