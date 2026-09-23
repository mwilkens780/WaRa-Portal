<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Eine Vorlage fuer alle Ereignis-Mails.
 *
 * Neun Ereignisse mit neun fast gleichen Vorlagen zu pflegen, heisst neun
 * Stellen zu aendern, wenn sich die Anrede oder der Fuss aendert. Stattdessen
 * liefert der Ausloeser nur Inhalt: Ueberschrift, ein paar Zeilen, optional
 * Eckdaten als Aufzaehlung und ein Knopf, der ins Portal fuehrt.
 */
class NotificationMail extends Mailable
{
    /**
     * @param  list<string>          $paragraphs  Fliesstext
     * @param  array<string, string> $facts       Eckdaten: Bezeichnung => Wert
     */
    public function __construct(
        public string $subjectText,
        public string $heading,
        public array $paragraphs = [],
        public array $facts = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?string $greetingName = null,
        public ?string $footnote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'heading'      => $this->heading,
                'paragraphs'   => $this->paragraphs,
                'facts'        => $this->facts,
                'actionUrl'    => $this->actionUrl,
                'actionLabel'  => $this->actionLabel,
                'greetingName' => $this->greetingName,
                'footnote'     => $this->footnote,
                'portalUrl'    => config('app.url'),
            ],
        );
    }

    public function defaultSubject(): string
    {
        return $this->subjectText;
    }

    // ── Warteschlange ────────────────────────────────────────────────────────

    public function queuePayload(): array
    {
        return [
            'subject'   => $this->subjectText,
            'heading'   => $this->heading,
            'paragraphs'=> $this->paragraphs,
            'facts'     => $this->facts,
            'url'       => $this->actionUrl,
            'label'     => $this->actionLabel,
            'greeting'  => $this->greetingName,
            'footnote'  => $this->footnote,
        ];
    }

    public static function fromQueuePayload(array $payload, ?User $user): self
    {
        return new self(
            subjectText:  $payload['subject'] ?? 'Nachricht aus dem WaRa-Portal',
            heading:      $payload['heading'] ?? '',
            paragraphs:   $payload['paragraphs'] ?? [],
            facts:        $payload['facts'] ?? [],
            actionUrl:    $payload['url'] ?? null,
            actionLabel:  $payload['label'] ?? null,
            greetingName: $payload['greeting'] ?? $user?->firstname,
            footnote:     $payload['footnote'] ?? null,
        );
    }
}
