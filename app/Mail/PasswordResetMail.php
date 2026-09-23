<?php

namespace App\Mail;

use App\Models\User;
use App\Services\AccountLinkService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Link zum Zuruecksetzen des Passworts.
 *
 * $byAdmin unterscheidet die beiden Faelle: Hat jemand selbst "Passwort
 * vergessen" geklickt, oder hat ein Trainer bzw. Admin zurueckgesetzt? Im
 * zweiten Fall muss in der Mail stehen, dass die Anforderung nicht vom
 * Empfaenger kam - sonst wirkt sie wie ein Angriffsversuch.
 */
class PasswordResetMail extends Mailable
{
    public function __construct(
        public User $user,
        public bool $byAdmin = false,
        public ?string $byName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->defaultSubject());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'user'      => $this->user,
                'resetUrl'  => app(AccountLinkService::class)->resetUrl($this->user),
                'portalUrl' => config('app.url'),
                'byAdmin'   => $this->byAdmin,
                'byName'    => $this->byName,
            ],
        );
    }

    public function defaultSubject(): string
    {
        return 'Passwort für das WaRa-Portal neu setzen';
    }

    public function queuePayload(): array
    {
        return ['by_admin' => $this->byAdmin, 'by_name' => $this->byName];
    }

    public static function fromQueuePayload(array $payload, ?User $user): ?self
    {
        return $user
            ? new self($user, (bool) ($payload['by_admin'] ?? false), $payload['by_name'] ?? null)
            : null;
    }
}
