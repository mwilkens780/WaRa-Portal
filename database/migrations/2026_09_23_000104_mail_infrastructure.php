<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail-Versand: Protokoll und Einstellungen je Benutzer.
 *
 * Das Protokoll hat zwei Aufgaben. Erstens Nachvollziehbarkeit: Wer hat wann
 * welche Mail bekommen, und ist sie rausgegangen? Zweitens Warteschlange:
 * Massenversand (Willkommensmails an den Bestand) wuerde synchron in einen
 * Zeitueberlauf laufen, deshalb werden solche Mails als "pending" abgelegt und
 * vom Cron in Haeppchen verschickt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 50);                 // siehe App\Support\MailTopic
            // Darf leer sein: Auch der Versuch, an jemanden ohne hinterlegte
            // Adresse zu schreiben, gehoert ins Protokoll - sonst sucht man
            // spaeter vergeblich nach der ausgebliebenen Mail.
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('sent_to')->nullable();       // tatsaechlich benutzt (Wartungsmodus: Testadresse)
            $table->string('subject');
            $table->string('mailable')->nullable();      // Klassenname, fuer den Nachversand
            $table->json('payload')->nullable();         // Argumente fuer den Nachversand
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['user_id', 'topic']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Opt-in: leer bedeutet "nur Kontomails", nicht "alles"
            $table->json('mail_preferences')->nullable()->after('initial_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_preferences');
        });
        Schema::dropIfExists('mail_messages');
    }
};
