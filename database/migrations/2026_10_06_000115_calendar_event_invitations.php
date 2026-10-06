<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalendertermine mit Einladung, Anmeldung, Agenda, Anhängen und Protokollen
 * (Vorstandssitzung, Elternabend, Team-Event/Trainingslager).
 *
 * Entscheidungen Martin, 06.10.2026: Einladungen Standard an (abwählbar),
 * Gäste per Mail mit Zusage-Link ohne Login, Details nur für Eingeladene + Ersteller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $t) {
            if (!Schema::hasColumn('calendar_events', 'location')) {
                $t->string('location', 200)->nullable()->after('description');
                $t->text('agenda')->nullable()->after('location');
                $t->boolean('rsvp_enabled')->default(false)->after('agenda');
                $t->date('rsvp_deadline')->nullable()->after('rsvp_enabled');
                $t->unsignedSmallInteger('capacity')->nullable()->after('rsvp_deadline');
                $t->timestamp('reminder_sent_at')->nullable()->after('capacity');
            }
        });

        Schema::create('calendar_event_invitees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Gäste ohne Portal-Konto
            $t->string('guest_name', 150)->nullable();
            $t->string('guest_email', 190)->nullable();
            $t->string('token', 64)->nullable()->unique();
            // Wie die Person dazukam (Vorstand, Gruppe, Eltern, Gast …) – nur zur Anzeige
            $t->string('source', 20)->default('einzeln');
            $t->string('status', 12)->default('offen');   // offen | zugesagt | abgesagt | vielleicht
            $t->string('comment', 500)->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('invited_at')->nullable();
            $t->timestamps();

            $t->unique(['calendar_event_id', 'user_id']);
            $t->unique(['calendar_event_id', 'guest_email']);
        });

        Schema::create('calendar_event_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $t->string('category', 12);              // agenda | anhang | protokoll
            $t->string('title', 200);
            // Entweder Datei oder Link
            $t->string('path')->nullable();
            $t->string('original_name')->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->string('url', 1000)->nullable();
            // Protokoll einer früheren Sitzung, hier nur verknüpft (keine Kopie)
            $t->foreignId('source_file_id')->nullable()->constrained('calendar_event_files')->nullOnDelete();
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_files');
        Schema::dropIfExists('calendar_event_invitees');
        Schema::table('calendar_events', function (Blueprint $t) {
            $t->dropColumn(['location', 'agenda', 'rsvp_enabled', 'rsvp_deadline', 'capacity', 'reminder_sent_at']);
        });
    }
};
