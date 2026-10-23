<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alte wöchentliche Saisonwertung entfernt (Martin, 23.10.2026): Ihre
 * Gesamtpunkt-Formel war nie bestätigt und wurde nirgends angezeigt. Die
 * Wertung für Sportler/Eltern ("Meine Wertung") rechnet live aus den
 * Ergebnissen (App\Services\SeasonStats). down() legt die Tabelle leer wieder an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('season_scores');
    }

    public function down(): void
    {
        Schema::create('season_scores', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedSmallInteger('podiums')->default(0);
            $table->unsignedSmallInteger('finals')->default(0);
            $table->unsignedSmallInteger('personal_bests')->default(0);
            $table->unsignedSmallInteger('club_records')->default(0);
            $table->dateTime('recalculated_at')->useCurrent();

            $table->primary(['user_id', 'season_year']);
        });
    }
};
