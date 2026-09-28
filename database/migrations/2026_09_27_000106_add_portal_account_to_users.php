<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portal-Zugang getrennt von der Vereinsmitgliedschaft.
 *
 * Bisher entschied ein einziges Feld (`active`) beides: ob jemand Mitglied im
 * Verein ist und ob er sich anmelden darf. Das sind zwei verschiedene Fragen -
 * ein Mitglied kann ohne Portal-Zugang sein, und ein Zugang kann gesperrt
 * werden, ohne jemanden aus dem Verein zu werfen.
 *
 *  - portal_active:       darf sich anmelden (von Administratoren gesetzt)
 *  - portal_activated_at: wann der Zugang fertig eingerichtet wurde, also
 *                         eigenes Passwort gesetzt UND angemeldet
 *
 * Nachtrag fuer den Bestand: Wer sich schon einmal angemeldet hat und kein
 * Initialpasswort mehr hat, ist offensichtlich eingerichtet - fuer diese
 * Konten gilt der Zeitpunkt der letzten Anmeldung als Aktivierung. Genauer
 * laesst sich das rueckwirkend nicht sagen, und ein leeres Feld waere hier
 * schlicht falsch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('portal_active')->default(true)->after('active');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_active');
        });

        DB::table('users')
            ->whereNotNull('last_login_at')
            ->where(fn($q) => $q->whereNull('initial_password')->orWhere('initial_password', ''))
            ->update(['portal_activated_at' => DB::raw('last_login_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['portal_active', 'portal_activated_at']);
        });
    }
};
