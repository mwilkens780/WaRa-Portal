<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellen-Abgleich: WebClub, DSV-Crawler und manueller DSV7-Import müssen für
 * dasselbe Ergebnis übereinstimmen. Weicht etwas ab, wird die Abweichung hier
 * festgehalten und auf der Ergebnisseite rot markiert.
 *
 * Bewusst eine eigene Tabelle: ein Konflikt kann zu ZWEI Zeilen in
 * competition_results führen (z.B. wenn die Disziplin abweicht und damit der
 * Dedup-Schlüssel auseinanderfällt). Beide werden per Fremdschlüssel verknüpft.
 *
 * Kein Fehler ist:
 *   - wenn eine Quelle zu einem Wettkampf gar nichts liefert
 *   - wenn eine Quelle ein Feld befüllt, das eine andere leer lässt (Anreicherung).
 *     Beispiel: die PB-Markierung ist im WebClub zuverlässiger als im Portal,
 *     weil dort die Historie lückenhaft ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_results', function (Blueprint $table) {
            // Welche Quelle hat diese Zeile angelegt?
            $table->string('source', 40)->nullable()->after('user_id');
        });

        Schema::create('result_discrepancies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Primaere Ergebniszeile und – bei echtem Clash – die zweite Zeile
            $table->foreignId('competition_result_id')->nullable()
                  ->constrained('competition_results')->cascadeOnDelete();
            $table->foreignId('conflicting_result_id')->nullable()
                  ->constrained('competition_results')->cascadeOnDelete();

            // Kontext, damit die Abweichung auch ohne Ergebniszeile lesbar bleibt
            $table->string('discipline', 20)->nullable();
            $table->unsignedSmallInteger('distance')->nullable();

            $table->string('field', 40);              // time_ms, placement, discipline, …
            $table->string('source_a', 40);
            $table->string('value_a', 191)->nullable();
            $table->string('source_b', 40);
            $table->string('value_b', 191)->nullable();

            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'resolved_at']);
            $table->index(['user_id', 'resolved_at']);
            // Dieselbe Abweichung soll bei jedem Crawl-Lauf nur einmal entstehen
            $table->unique(
                ['competition_result_id', 'field', 'source_a', 'source_b'],
                'result_discrepancies_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_discrepancies');

        Schema::table('competition_results', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
