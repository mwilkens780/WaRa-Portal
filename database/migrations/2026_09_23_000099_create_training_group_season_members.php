<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gruppenaufstellung je Saison.
 *
 * training_group_swimmer kennt nur den heutigen Stand. Fuer die Leistungs-
 * kriterien vergangener Saisons muss aber sichtbar bleiben, wer damals in der
 * Gruppe war - auch wenn Sportler inzwischen gewechselt, den Verein verlassen
 * haben oder neu dazugekommen sind.
 *
 * Die Zeilen der laufenden Saison pflegt ein taeglicher Job (GroupRoster).
 * Nach Saisonende schreibt ihn niemand mehr fort - damit ist der Stand zum
 * Saisonende eingefroren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_group_season_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_group_id', 'season_id', 'user_id'], 'group_season_member_unique');
        });

        // Laufende Saison sofort erfassen, nicht erst beim ersten Nachtlauf
        app(\App\Services\GroupRoster::class)->snapshotRunningSeason();
    }

    public function down(): void
    {
        Schema::dropIfExists('training_group_season_members');
    }
};
