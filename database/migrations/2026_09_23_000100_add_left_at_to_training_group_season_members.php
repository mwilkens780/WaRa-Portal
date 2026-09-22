<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wer eine Gruppe waehrend der Saison verlaesst, wurde bisher aus der
 * Saisonaufstellung geloescht und verschwand damit samt Bewertungen aus der
 * Rueckschau. Jetzt bleibt die Zeile stehen und bekommt das Austrittsdatum.
 *
 * left_at NULL      = am Saisonende (bzw. heute) in der Gruppe
 * left_at gesetzt   = waehrend der Saison ausgeschieden; zaehlt nicht zur
 *                     Gruppengroesse, bleibt aber mit Bewertung sichtbar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_group_season_members', function (Blueprint $table) {
            $table->date('left_at')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_group_season_members', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
