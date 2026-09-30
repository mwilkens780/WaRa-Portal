<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trainingsserie als eigener Datensatz (docs/konzept-trainingsserien.md, Schritt 3).
 *
 * Bisher war eine Serie nur die gemeinsame recurrence_group_id vieler Einheiten.
 * Diese Migration legt NUR die Struktur an - sie aendert keine vorhandenen Daten
 * (der Deploy fuehrt migrate automatisch aus). Den Bestand uebernimmt der Befehl
 * `php artisan training:series-backfill` (erst Probelauf, dann --apply).
 *
 * Die ID der Serie ist die bisherige recurrence_group_id - Verweise wie staendige
 * Absagen und Einzelzuweisungen bleiben dadurch gueltig.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_series')) Schema::create('training_series', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type', 30)->default('technik');
            $table->unsignedTinyInteger('day_of_week');            // 1 = Montag … 7 = Sonntag
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('recurrence_type', 20)->default('weekly');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('skip_holidays')->default(true);       // Schulferien Schleswig-Holstein auslassen
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->boolean('registration_open')->default(false);
            $table->foreignId('guest_group_id')->nullable()->constrained('training_groups')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['day_of_week', 'start_time']);
        });

        if (!Schema::hasTable('training_series_group')) Schema::create('training_series_group', function (Blueprint $table) {
            $table->string('training_series_id', 36);
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_series_id', 'training_group_id']);
            $table->foreign('training_series_id')->references('id')->on('training_series')->cascadeOnDelete();
        });

        if (!Schema::hasTable('training_series_trainers')) Schema::create('training_series_trainers', function (Blueprint $table) {
            $table->string('training_series_id', 36);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_series_id', 'user_id']);
            $table->foreign('training_series_id')->references('id')->on('training_series')->cascadeOnDelete();
        });

        // Woechentliche Hallenbelegung gehoert kuenftig der Serie, nicht einer Einheit
        if (!Schema::hasColumn('hall_bookings', 'training_series_id')) Schema::table('hall_bookings', function (Blueprint $table) {
            $table->string('training_series_id', 36)->nullable()->after('training_session_id');
            $table->foreign('training_series_id')->references('id')->on('training_series')->nullOnDelete();
        });

        // Einheit: Ausfall statt Loeschen, bewusste Abweichungen von der Serie
        // (Schutzabfragen: bricht die Migration mittendrin ab, laeuft sie beim naechsten Deploy zu Ende)
        if (!Schema::hasColumn('training_sessions', 'status')) Schema::table('training_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('planned')->after('recurrence_group_id');   // planned | cancelled
            $table->string('cancel_reason')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->json('overridden_fields')->nullable()->after('cancelled_at');              // z. B. ["start_time","location"]
        });

        // Ausnahme-Bahnen: nur fuer diesen einen Termin, erscheinen NICHT im Hallenplan
        if (!Schema::hasTable('training_session_lanes')) Schema::create('training_session_lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['training_session_id', 'hall_resource_id'], 'ts_lanes_session_resource_unique');   // Standardname > 64 Zeichen
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_lanes');
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn(['status', 'cancel_reason', 'cancelled_at', 'overridden_fields']);
        });
        if (Schema::hasColumn('hall_bookings', 'training_series_id')) Schema::table('hall_bookings', function (Blueprint $table) {
            $table->dropForeign(['training_series_id']);
            $table->dropColumn('training_series_id');
        });
        Schema::dropIfExists('training_series_trainers');
        Schema::dropIfExists('training_series_group');
        Schema::dropIfExists('training_series');
    }
};
