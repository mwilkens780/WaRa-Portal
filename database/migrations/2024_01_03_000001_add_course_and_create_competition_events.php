<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bahnlänge an den Wettkampf
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('course', 3)->nullable()->after('organizer'); // LCM | SCM
        });

        // Wettkampf-Startliste / Ausschreibungs-Disziplinen
        Schema::create('competition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('event_number');
            $table->unsignedTinyInteger('session_number')->default(1);
            $table->date('session_date')->nullable();
            $table->string('session_name', 100)->nullable();
            $table->enum('discipline', ['freistil', 'ruecken', 'brust', 'schmetterling', 'lagen']);
            $table->unsignedSmallInteger('distance');
            $table->enum('gender', ['M', 'F', 'X'])->default('X');
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('age_group', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_events');
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }
};
