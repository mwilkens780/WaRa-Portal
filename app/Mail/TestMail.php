<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Prueft, ob der Mailversand ueberhaupt funktioniert. */
class TestMail extends Mailable
{
    public function __construct(public ?User $triggeredBy = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Testmail aus dem WaRa-Portal');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            with: [
                'triggeredBy' => $this->triggeredBy,
                'sentAt'      => now()->deBerlin('d.m.Y, H:i:s') . ' Uhr',
                'mailer'      => config('mail.default'),
                'host'        => config('mail.mailers.smtp.host'),
                'from'        => config('mail.from.address'),
                'portalUrl'   => config('app.url'),
            ],
        );
    }
}
