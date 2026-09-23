<?php

namespace App\Mail;

use App\Models\User;
use App\Services\AccountLinkService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Willkommensmail mit Einrichtungslink.
 *
 * Der Link wird erst beim Versand erzeugt, nicht beim Einstellen in die
 * Warteschlange - sonst liefe die Frist bereits, waehrend die Mail noch
 * wartet.
 */
class AccountWelcomeMail extends Mailable
{
    public function __construct(
        public User $user,
        public bool $isResend = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->defaultSubject());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-welcome',
            with: [
                'user'      => $this->user,
                'setupUrl'  => app(AccountLinkService::class)->setupUrl($this->user),
                'portalUrl' => config('app.url'),
                'isResend'  => $this->isResend,
            ],
        );
    }

    public function defaultSubject(): string
    {
        return $this->isResend
            ? 'Dein Zugang zum WaRa-Portal'
            : 'Willkommen im WaRa-Portal';
    }

    // ── Warteschlange ────────────────────────────────────────────────────────

    public function queuePayload(): array
    {
        return ['resend' => $this->isResend];
    }

    public static function fromQueuePayload(array $payload, ?User $user): ?self
    {
        return $user ? new self($user, (bool) ($payload['resend'] ?? false)) : null;
    }
}
