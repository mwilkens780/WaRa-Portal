<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motto der Woche: gemeinsamer Zyklus zweier Gruppen, Trainerbeteiligung
 * und eine festgelegte Reihenfolge.
 *
 * Gruppen, die zusammen trainieren, sollen einen gemeinsamen Zyklus haben.
 * Die Gruppe, bei der die Partnergruppe eingetragen ist, fuehrt den Zyklus;
 * die Mitglieder beider Gruppen laufen darin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->foreignId('motto_partner_group_id')->nullable()->after('motto_week_enabled')
                  ->constrained('training_groups')->nullOnDelete();
            $table->boolean('motto_include_trainers')->default(true)->after('motto_partner_group_id');
            // Reihenfolge der Beteiligten als Liste von Benutzer-IDs; wer fehlt,
            // haengt alphabetisch hinten an.
            $table->json('motto_order')->nullable()->after('motto_include_trainers');
        });
    }

    public function down(): void
    {
        Schema::table('training_groups', function (Blueprint $table) {
            $table->dropForeign(['motto_partner_group_id']);
            $table->dropColumn(['motto_partner_group_id', 'motto_include_trainers', 'motto_order']);
        });
    }
};
