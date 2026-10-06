<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kampfrichter-Abfrage zu einem Wettkampf (Vorstand): alle Kampfrichter oder
 * gezielt einzelne. Rückmeldung je Veranstaltungstag ja/nein mit Kommentar,
 * dazu Wunschpositionen (Entscheidung Martin, 06.10.2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_official_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competition_id')->unique()->constrained()->cascadeOnDelete();
            $t->text('message')->nullable();
            $t->date('deadline')->nullable();
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('competition_official_invitees', function (Blueprint $t) {
            $t->id();
            // Eigener Name: der automatische ist für MySQL zu lang (max. 64 Zeichen)
            $t->foreignId('competition_official_request_id')
                ->constrained('competition_official_requests', 'id', 'fk_official_invitee_request')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            // {"2026-11-14": {"available": true, "comment": "ab 10 Uhr"}, …}
            $t->json('availability')->nullable();
            $t->json('positions')->nullable();   // DSV-Kürzel, z. B. ["ZN","WR"]
            $t->string('comment', 1000)->nullable();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();

            $t->unique(['competition_official_request_id', 'user_id'], 'uq_official_invitee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_official_invitees');
        Schema::dropIfExists('competition_official_requests');
    }
};
