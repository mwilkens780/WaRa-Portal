<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->string('club_name', 200);
            $table->string('athlete_lastname', 100);
            $table->string('athlete_firstname', 100);
            $table->smallInteger('birth_year');
            $table->char('gender', 1);
            $table->string('dsv_id', 20)->nullable();
            $table->unsignedSmallInteger('event_number');
            $table->unsignedInteger('entry_time_ms')->nullable();
            $table->timestamp('imported_at')->useCurrent();

            $table->foreign('competition_id')->references('id')->on('competitions')->cascadeOnDelete();
            $table->index(['competition_id', 'event_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_entries');
    }
};
