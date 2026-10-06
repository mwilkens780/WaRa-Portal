<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung der Kampfrichter durch den Kampfrichterobmann (Vorstand):
 * aus den Rückmeldungen je Abschnitt Person + Einsatzwunsch (DSV-Kürzel)
 * festlegen. Ist die Meldung freigegeben, landen die Kampfrichter als
 * KARIMELDUNG/KARIABSCHNITT in der Vereinsmeldeliste (Auftrag Martin, 07.10.2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_official_requests', function (Blueprint $t) {
            $t->timestamp('finalized_at')->nullable()->after('closed_at');
            $t->foreignId('finalized_by_id')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('competition_official_invitees', function (Blueprint $t) {
            // Kampfrichtergruppe für KARIMELDUNG: WKR | AUS | SCH | SPR
            $t->string('kari_group', 3)->nullable()->after('comment');
        });

        Schema::create('competition_official_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_official_invitee_id')
                ->constrained('competition_official_invitees', 'id', 'fk_official_assignment_invitee')->cascadeOnDelete();
            $t->unsignedTinyInteger('session_number');   // Abschnitt
            $t->string('position', 4);                     // Einsatzwunsch, DSV-Kürzel
            $t->timestamps();

            $t->unique(['competition_official_invitee_id', 'session_number'], 'uq_official_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_official_assignments');
        Schema::table('competition_official_invitees', fn(Blueprint $t) => $t->dropColumn('kari_group'));
        Schema::table('competition_official_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('finalized_by_id');
            $t->dropColumn('finalized_at');
        });
    }
};
