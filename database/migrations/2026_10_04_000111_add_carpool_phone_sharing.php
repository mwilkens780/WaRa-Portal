<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Handynummer bei Fahrgemeinschaften (Entscheidung Martin, 04.10.2026):
 *  - users.carpool_share_phone: Voreinstellung im Profil ("generell anzeigen")
 *  - competition_signup_responses.carpool_show_phone: Haken am einzelnen Angebot.
 *    Nur wenn er gesetzt ist, sehen Mitfahrer die Nummer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'carpool_share_phone')) {
            Schema::table('users', fn(Blueprint $t) => $t->boolean('carpool_share_phone')->default(false)->after('mobile'));
        }
        if (!Schema::hasColumn('competition_signup_responses', 'carpool_show_phone')) {
            Schema::table('competition_signup_responses', fn(Blueprint $t) => $t->boolean('carpool_show_phone')->default(false)->after('carpool_note'));
        }
    }

    public function down(): void
    {
        Schema::table('competition_signup_responses', fn(Blueprint $t) => $t->dropColumn('carpool_show_phone'));
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('carpool_share_phone'));
    }
};
