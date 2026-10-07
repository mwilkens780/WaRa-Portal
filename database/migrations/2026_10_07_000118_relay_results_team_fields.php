<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staffelergebnisse für Mannschaftswertungen (DMS-J: Gesamtzeit aller Staffeln
 * einer Vereinsmannschaft je Altersklasse und Geschlecht):
 *  - relay_legs:  Anzahl Schwimmer (distance bleibt die Strecke je Schwimmer)
 *  - team_number: 1. / 2. Mannschaft eines Vereins
 *  - round:       Wettkampfart laut DSV (E = Entscheidung, N = Nachschwimmen …)
 * Dazu Status AB (abgemeldet), den die DSV-Datei kennt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relay_results', function (Blueprint $t) {
            if (!Schema::hasColumn('relay_results', 'relay_legs')) {
                $t->unsignedTinyInteger('relay_legs')->nullable()->after('distance');
                $t->unsignedTinyInteger('team_number')->nullable()->after('club_name');
                $t->char('round', 1)->nullable()->after('team_number');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `relay_results` MODIFY `status` ENUM('OK','DNS','DNF','DQ','AB') NOT NULL DEFAULT 'OK'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('relay_results')->where('status', 'AB')->update(['status' => 'DNS']);
            DB::statement("ALTER TABLE `relay_results` MODIFY `status` ENUM('OK','DNS','DNF','DQ') NOT NULL DEFAULT 'OK'");
        }
        Schema::table('relay_results', fn(Blueprint $t) => $t->dropColumn(['relay_legs', 'team_number', 'round']));
    }
};
