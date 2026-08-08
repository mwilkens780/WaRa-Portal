<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            // WebClub-Wertung zum Zeitpunkt des Wettkampfs (unveränderlich nach Import).
            // Mögliche Werte: "PBZ" (Personal Best), "SBZ" (Saison Best), "VR" (Vereinsrekord),
            // "LR" (Landesrekord) – ggf. kombiniert (Leerzeichen-getrennt).
            // Trennung von portal-berechneten Feldern (is_personal_best, breaks_vereinsrekord etc.)
            $table->string('webclub_rek', 50)->nullable()->after('wa_table_year');
        });
    }

    public function down(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('webclub_rek');
        });
    }
};
