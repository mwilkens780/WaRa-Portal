<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalender-Abo (Martin, 08.10.2026):
 *  - persönlicher, geheimer Abo-Link je Benutzer (webcal/ICS)
 *  - Vereinstermine, Meldefristen, Ehrungen optional nur für bestimmte
 *    Trainingsgruppen (ohne Auswahl: für alle)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('calendar_token', 64)->nullable()->unique();
        });

        Schema::create('calendar_event_training_group', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $t->unique(['calendar_event_id', 'training_group_id'], 'uq_cal_event_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_training_group');
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['calendar_token']);
            $t->dropColumn('calendar_token');
        });
    }
};
