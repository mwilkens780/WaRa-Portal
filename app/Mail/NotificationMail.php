<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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
        // Kalendereintrag als .ics-Anhang: ['termin'|'wettkampf', id] – beim Versand erzeugt, damit aktuell
        public ?array $calendar = null,
    ) {}

    public function attachments(): array
    {
        if (!$this->calendar) return [];
        [$type, $id] = $this->calendar;
        $feed = app(\App\Services\CalendarFeed::class);

        $item = match ($type) {
            'termin'    => ($e = \App\Models\CalendarEvent::find($id)) ? $feed->event($e) : null,
            'wettkampf' => ($c = \App\Models\Competition::find($id)) ? $feed->competition($c) : null,
            default     => null,
        };
        if (!$item) return [];

        return [Attachment::fromData(fn() => $feed->single($item), 'termin.ics')->withMime('text/calendar; charset=utf-8; method=PUBLISH')];
    }

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
            'calendar'  => $this->calendar,
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
            calendar:     $payload['calendar'] ?? null,
        );
    }
}
