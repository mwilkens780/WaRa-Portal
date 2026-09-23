<?php

namespace App\Services;

use App\Models\MailMessage;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailTopic;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Einziger Weg, auf dem das Portal Mails verschickt.
 *
 * Drei Dinge passieren hier an einer Stelle statt verstreut:
 *
 *  - Opt-in: Ausser Kontomails geht nur raus, was im Profil eingeschaltet ist.
 *  - Wartungsmodus: Dann gehen alle Mails an die hinterlegte Testadresse, mit
 *    dem echten Empfaenger im Betreff. So laesst sich der Versand pruefen,
 *    ohne dass Mitglieder Testmails bekommen.
 *  - Protokoll: Jeder Versuch landet in mail_messages - auch der geschlagene.
 *    Ohne das weiss hinterher niemand, ob eine Mail je unterwegs war.
 *
 * Ein Fehler beim Versand wirft nie in die Anfrage zurueck. Eine nicht
 * zugestellte Mail darf keinen 500er auf einer Seite ausloesen, die inhaltlich
 * laengst fertig ist.
 */
class Mailer
{
    /** Wie viele Mails der Cron je Lauf verschickt. */
    public const BATCH_SIZE = 25;

    /**
     * Verschickt sofort, sofern der Benutzer das Thema bekommen will.
     *
     * @return MailMessage  das Protokoll dieser Mail (auch bei Ablehnung)
     */
    public function send(User $user, string $topic, Mailable $mailable, string $subject): MailMessage
    {
        $log = $this->log($user, $topic, $subject, $mailable);

        if (!$user->email) {
            return $this->skip($log, 'Keine E-Mail-Adresse hinterlegt.');
        }
        if (!MailTopic::wants($user, $topic)) {
            return $this->skip($log, 'Thema im Profil nicht eingeschaltet.');
        }

        return $this->deliver($log, $mailable);
    }

    /**
     * Legt die Mail zum spaeteren Versand ab. Fuer Massenversand: synchron
     * wuerden 200 Willkommensmails die Anfrage in den Zeitueberlauf treiben.
     */
    public function queue(User $user, string $topic, Mailable $mailable, string $subject): MailMessage
    {
        $log = $this->log($user, $topic, $subject, $mailable);

        if (!$user->email) {
            return $this->skip($log, 'Keine E-Mail-Adresse hinterlegt.');
        }
        if (!MailTopic::wants($user, $topic)) {
            return $this->skip($log, 'Thema im Profil nicht eingeschaltet.');
        }

        // Fuer den spaeteren Versand muss die Mailable rekonstruierbar sein
        $log->update([
            'mailable' => get_class($mailable),
            'payload'  => method_exists($mailable, 'queuePayload') ? $mailable->queuePayload() : null,
        ]);

        return $log;
    }

    /** Verschickt offene Mails aus der Warteschlange (Cron). */
    public function processQueue(int $limit = self::BATCH_SIZE): array
    {
        $sent = $failed = 0;

        $pending = MailMessage::where('status', 'pending')
            ->whereNotNull('mailable')
            ->where('attempts', '<', 3)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pending as $log) {
            $mailable = $this->rebuild($log);
            if (!$mailable) {
                $log->update(['status' => 'failed', 'error' => 'Mail konnte nicht rekonstruiert werden.']);
                $failed++;
                continue;
            }

            $this->deliver($log, $mailable);
            $log->status === 'sent' ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'remaining' => MailMessage::where('status', 'pending')->count()];
    }

    /** Testmail an eine beliebige Adresse - umgeht Opt-in und Protokollpflicht nicht. */
    public function sendTest(string $address, Mailable $mailable, string $subject, ?User $triggeredBy = null): MailMessage
    {
        $log = MailMessage::create([
            'user_id'         => null,
            'topic'           => MailTopic::ACCOUNT,
            'recipient_email' => $address,
            'subject'         => $subject,
            'status'          => 'pending',
            'triggered_by'    => $triggeredBy?->id,
        ]);

        return $this->deliver($log, $mailable);
    }

    /** Im Wartungsmodus gehen alle Mails hierhin. */
    public static function testAddress(): ?string
    {
        $address = trim((string) Setting::getCached('mail_test_address', ''));

        return $address !== '' ? $address : null;
    }

    public static function maintenanceActive(): bool
    {
        try {
            return Setting::getBool('maintenance_mode');
        } catch (\Throwable) {
            return false;   // Datenbank nicht erreichbar, z.B. waehrend migrate
        }
    }

    // ── innere Mechanik ──────────────────────────────────────────────────────

    private function deliver(MailMessage $log, Mailable $mailable): MailMessage
    {
        $recipient = $log->recipient_email;
        $subject   = $log->subject;

        // Wartungsmodus: Umleitung auf die Testadresse, echter Empfaenger in
        // den Betreff - sonst laesst sich beim Testen nicht unterscheiden,
        // fuer wen die Mail eigentlich gedacht war.
        if (self::maintenanceActive()) {
            $test = self::testAddress();
            if (!$test) {
                return $this->fail($log, 'Wartungsmodus aktiv, aber keine Testadresse hinterlegt.');
            }
            $recipient = $test;
            $subject   = '[TEST an ' . $log->recipient_email . '] ' . $subject;
        }

        try {
            $mailable->subject($subject);
            Mail::to($recipient)->send($mailable);

            $log->update([
                'status'   => 'sent',
                'sent_to'  => $recipient,
                'subject'  => $subject,
                'sent_at'  => now(),
                'attempts' => $log->attempts + 1,
                'error'    => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Mailversand fehlgeschlagen: ' . $e->getMessage(), ['mail_message_id' => $log->id]);

            $log->update([
                'status'   => $log->attempts + 1 >= 3 ? 'failed' : 'pending',
                'sent_to'  => $recipient,
                'attempts' => $log->attempts + 1,
                'error'    => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }

        return $log->fresh();
    }

    private function log(User $user, string $topic, string $subject, Mailable $mailable): MailMessage
    {
        return MailMessage::create([
            'user_id'         => $user->id,
            'topic'           => $topic,
            'recipient_email' => $user->email,
            'recipient_name'  => trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: null,
            'subject'         => $subject,
            'mailable'        => get_class($mailable),
            'status'          => 'pending',
            'triggered_by'    => auth()->id(),
        ]);
    }

    private function skip(MailMessage $log, string $reason): MailMessage
    {
        $log->update(['status' => 'skipped', 'error' => $reason]);

        return $log;
    }

    private function fail(MailMessage $log, string $reason): MailMessage
    {
        $log->update(['status' => 'failed', 'error' => $reason, 'attempts' => $log->attempts + 1]);

        return $log;
    }

    /** Baut eine wartende Mail aus Klasse und Nutzlast wieder auf. */
    private function rebuild(MailMessage $log): ?Mailable
    {
        $class = $log->mailable;

        if (!$class || !class_exists($class) || !method_exists($class, 'fromQueuePayload')) {
            return null;
        }

        try {
            return $class::fromQueuePayload($log->payload ?? [], $log->user);
        } catch (\Throwable $e) {
            Log::error('Mail konnte nicht rekonstruiert werden: ' . $e->getMessage(), ['mail_message_id' => $log->id]);
            return null;
        }
    }
}
