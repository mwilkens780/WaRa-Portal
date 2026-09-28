<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wer hat dieses Konto angelegt?
 *
 * Gebraucht wird das in der Benutzerverwaltung der Trainer: Sie sehen dort nur
 * die Mitglieder ihrer Gruppen. Ein gerade angelegtes Konto gehoert noch zu
 * keiner Gruppe und waere ohne dieses Feld im selben Moment wieder
 * verschwunden, in dem es entstanden ist.
 *
 * Kein Fremdschluessel: Wird ein Trainerkonto geloescht, soll die Zeile des
 * Mitglieds davon unberuehrt bleiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('portal_activated_at');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
