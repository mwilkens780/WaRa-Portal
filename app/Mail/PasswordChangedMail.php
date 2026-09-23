<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Bestaetigung nach einer Passwortaenderung.
 *
 * Kein Werbebrief, sondern eine Sicherheitsmeldung: Wer sein Passwort nicht
 * selbst geaendert hat, erfaehrt hier davon und kann reagieren.
 */
class PasswordChangedMail extends Mailable
{
    public function __construct(
        public User $user,
        public ?string $changedAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->defaultSubject());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            with: [
                'user'      => $this->user,
                'changedAt' => $this->changedAt ?? now()->deBerlin('d.m.Y, H:i') . ' Uhr',
                'portalUrl' => config('app.url'),
            ],
        );
    }

    public function defaultSubject(): string
    {
        return 'Dein Passwort im WaRa-Portal wurde geändert';
    }

    public function queuePayload(): array
    {
        return ['changed_at' => $this->changedAt];
    }

    public static function fromQueuePayload(array $payload, ?User $user): ?self
    {
        return $user ? new self($user, $payload['changed_at'] ?? null) : null;
    }
}
