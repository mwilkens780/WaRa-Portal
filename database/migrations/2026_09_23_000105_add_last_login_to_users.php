<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitpunkt der letzten Anmeldung.
 *
 * Bewusst nur der Zeitpunkt, keine IP-Adresse: In der Datenschutzerklaerung
 * steht, dass das Portal selbst keine IP-Adressen speichert - daran halten
 * wir uns auch hier.
 *
 * Zweck ist nicht Ueberwachung, sondern Verwaltung: Wer hat den Zugang nie
 * benutzt und braucht eine neue Einladung? Welche Konten sind tot?
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('mail_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
