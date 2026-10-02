<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fahrgemeinschaften zu Wettkaempfen: Eltern bieten Plaetze an (carpool_seats gab
 * es schon, wurde aber nirgends verwertet), Schwimmer oder Eltern buchen einen Platz.
 *
 *  - carpool_offered_by_id: wer anbietet (Elternteil) - wird bei den Mitfahrern angezeigt
 *  - carpool_note:          Hinweis zum Angebot (Abfahrtsort, Uhrzeit ...)
 *  - carpool_ride_id:       bei welchem Angebot (Rueckmeldung des Fahrer-Kindes) dieser Schwimmer mitfaehrt
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_signup_responses', function (Blueprint $table) {
            if (!Schema::hasColumn('competition_signup_responses', 'carpool_offered_by_id')) {
                $table->foreignId('carpool_offered_by_id')->nullable()->after('carpool_seats')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('competition_signup_responses', 'carpool_note')) {
                $table->string('carpool_note', 255)->nullable()->after('carpool_offered_by_id');
            }
            if (!Schema::hasColumn('competition_signup_responses', 'carpool_ride_id')) {
                $table->foreignId('carpool_ride_id')->nullable()->after('carpool_note')
                    ->constrained('competition_signup_responses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_signup_responses', function (Blueprint $table) {
            $table->dropForeign(['carpool_ride_id']);
            $table->dropForeign(['carpool_offered_by_id']);
            $table->dropColumn(['carpool_ride_id', 'carpool_note', 'carpool_offered_by_id']);
        });
    }
};
