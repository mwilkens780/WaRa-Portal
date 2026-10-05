<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Reihenfolge der Wettkampffolge wie in der Quelle (DSV-Datei, Lenex,
 *    WebClub-Sync) statt nach Wettkampfnummer: Finals tragen oft dreistellige
 *    Nummern (101, 102 …) und landeten sonst am Ende.
 *
 * 2. Staffeln: competition_events.distance ist die GESAMTSTRECKE (4×50 → 200),
 *    so liefert es die DSV-Datei und so rechnen Anzeige und Meldedatei. Der
 *    WebClub-Crawler hat die Strecke je Schwimmer gespeichert (4×50 → 50), die
 *    Anzeige teilte noch einmal durch 4: "4×12", "4×6".
 *    Korrigiert werden
 *      a) eindeutig falsche Zeilen: weniger als 25 m je Schwimmer, und
 *      b) alle Staffeln von Wettkämpfen mit WebClub-Verknüpfung und ohne
 *         DSV-Ausschreibung: Der WebClub-Sync überschreibt die Wettkampffolge
 *         je Nummer, deren Staffeln stammen also aus WebClub. Ohne
 *         WebClub-Verknüpfung kommen Staffeln nur aus DSV-Dateien (schon
 *         Gesamtstrecke); Lenex legte bisher gar keine Staffeln an.
 *    Übrige Fälle korrigiert der nächste WebClub-Lauf (schreibt jetzt die Gesamtstrecke).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('competition_events', 'sort_order')) {
            Schema::table('competition_events', function ($t) {
                $t->unsignedInteger('sort_order')->nullable()->after('event_number');
            });
        }

        $relays = DB::table('competition_events as e')
            ->join('competitions as c', 'c.id', '=', 'e.competition_id')
            ->where('e.relay_legs', '>', 1)
            ->select('e.id', 'e.distance', 'e.relay_legs', 'c.dsv_header_data', 'c.webclub_event_id')
            ->get();

        $fixed = 0;
        foreach ($relays as $r) {
            $perLegTooShort = intdiv((int) $r->distance, (int) $r->relay_legs) < 25;
            $fromWebClub    = !empty($r->webclub_event_id)
                && (empty($r->dsv_header_data) || $r->dsv_header_data === 'null');

            if ($perLegTooShort || $fromWebClub) {
                DB::table('competition_events')->where('id', $r->id)
                    ->update(['distance' => (int) $r->distance * (int) $r->relay_legs]);
                $fixed++;
            }
        }

        if ($fixed) {
            \Illuminate\Support\Facades\Log::info("Migration: {$fixed} Staffel-Wettkämpfe auf Gesamtstrecke korrigiert");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('competition_events', 'sort_order')) {
            Schema::table('competition_events', fn($t) => $t->dropColumn('sort_order'));
        }
        // Die Streckenkorrektur wird nicht zurückgenommen – die alten Werte waren falsch.
    }
};
