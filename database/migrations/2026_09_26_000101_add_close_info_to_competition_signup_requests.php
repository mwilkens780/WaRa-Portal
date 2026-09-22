<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachvollziehbarkeit beim Schliessen einer Anmeldeabfrage.
 *
 * close_reason:  'manual' (durch eine Person) oder 'competition_ended'
 *                (automatisch nach Wettkampfende)
 * closed_by_id:  wer von Hand geschlossen hat; bei automatischem Schliessen NULL
 * close_note:    lesbarer Vermerk, u.a. Stand der Antworten zum Zeitpunkt
 *                des Schliessens
 *
 * Bestehende, bereits geschlossene Abfragen bleiben ohne Grund - es ist
 * nicht bekannt, wer sie geschlossen hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->string('close_reason', 30)->nullable()->after('closed_at');
            $table->foreignId('closed_by_id')->nullable()->after('close_reason')
                  ->constrained('users')->nullOnDelete();
            $table->text('close_note')->nullable()->after('closed_by_id');
        });

        // Liegengebliebene Abfragen vergangener Wettkaempfe sofort schliessen,
        // nicht erst beim ersten Nachtlauf
        \App\Models\CompetitionSignupRequest::closeAfterCompetitionEnd();
    }

    public function down(): void
    {
        Schema::table('competition_signup_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_id');
            $table->dropColumn(['close_reason', 'close_note']);
        });
    }
};
