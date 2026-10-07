<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Kampfrichter-Qualifikationen (Auftrag Martin, 07.10.2026): Kampfrichter
 *    pflegen selbst Qualifikation, Erwerb, Lizenznummer und Ablaufdatum;
 *    Vorstand und Geschäftsstelle pflegen alle. Die Hauptlizenz (is_primary)
 *    ist mit den Lizenzfeldern im Benutzer-Stamm (users.kampfrichter_license_*)
 *    synchron – die liest auch der WebClub-Abgleich.
 *
 * 2. Bedarf je Abschnitt bei der Kampfrichter-Abfrage: Der Obmann legt die
 *    gesuchten Positionen fest und besetzt sie aus den Rückmeldungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_qualifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 120);
            $t->date('acquired_on')->nullable();
            $t->string('license_nr', 50)->nullable();
            $t->date('valid_until')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->index(['valid_until']);
        });

        // Bestehende Lizenzen aus dem Benutzer-Stamm als Hauptlizenz übernehmen
        $rows = DB::table('users')
            ->where(fn($q) => $q->whereNotNull('kampfrichter_license_nr')->orWhereNotNull('kampfrichter_license_valid_until'))
            ->get(['id', 'kampfrichter_license_nr', 'kampfrichter_license_issued', 'kampfrichter_license_valid_until']);
        foreach ($rows as $u) {
            DB::table('official_qualifications')->insert([
                'user_id' => $u->id, 'title' => 'Kampfrichter-Lizenz', 'is_primary' => true,
                'license_nr' => $u->kampfrichter_license_nr, 'acquired_on' => $u->kampfrichter_license_issued,
                'valid_until' => $u->kampfrichter_license_valid_until, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('competition_official_needs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_official_request_id')
                ->constrained('competition_official_requests', 'id', 'fk_official_need_request')->cascadeOnDelete();
            $t->unsignedTinyInteger('session_number');
            $t->string('position', 4);
            $t->unsignedTinyInteger('count');
            $t->timestamps();
            $t->unique(['competition_official_request_id', 'session_number', 'position'], 'uq_official_need');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_official_needs');
        Schema::dropIfExists('official_qualifications');
    }
};
