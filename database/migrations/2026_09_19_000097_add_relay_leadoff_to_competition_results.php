<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennzeichnet Ergebnisse, die aus dem Startabschnitt einer Staffel stammen.
 *
 * Der Startschwimmer startet vom Block, seine Zeit ist damit eine offizielle
 * Einzelzeit und zaehlt fuer Bestzeiten und Rekorde. Die Abloeser (Bahn 2 bis 4)
 * starten fliegend – deren Zeiten zaehlen nicht.
 *
 * Die Zeile liegt bewusst in competition_results, damit Bestzeiten-, Rekord- und
 * Bestenlisten-Logik ohne Sonderfaelle greifen. Das Kennzeichen erlaubt es,
 * solche Zeiten in der Anzeige als Staffel-Startabschnitt auszuweisen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->boolean('relay_leadoff')->default(false)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('relay_leadoff');
        });
    }
};
