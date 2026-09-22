<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bestenlisten: offene Top 10 statt Listen je Jahrgang.
 *
 * Die Listen werden jetzt bei der Anzeige berechnet - aus den
 * Wettkampfergebnissen des Portals und den historischen Eintraegen in
 * best_list_entries. Die frueher gespeicherten, aus Ergebnissen abgeleiteten
 * Zeilen (competition_result_id gesetzt) werden nicht mehr gebraucht und
 * wuerden sonst doppelt zaehlen.
 *
 * Neue Spalte source: 'import' (aus einer Datei) oder 'manual' (von Hand).
 * Ein erneuter Import ersetzt die importierten Eintraege der Bahn, von Hand
 * angelegte bleiben erhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('best_list_entries', function (Blueprint $table) {
            $table->string('source', 10)->nullable()->after('competition_result_id');
        });

        // Aus Ergebnissen abgeleitete Zeilen: werden jetzt live berechnet
        DB::table('best_list_entries')->whereNotNull('competition_result_id')->delete();

        // Alles Verbleibende stammt aus fruehreren Importen bzw. Handeintraegen
        DB::table('best_list_entries')->whereNull('source')->update(['source' => 'import']);
    }

    public function down(): void
    {
        Schema::table('best_list_entries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
