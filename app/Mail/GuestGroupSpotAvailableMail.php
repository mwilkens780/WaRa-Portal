<?php

namespace App\Mail;

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestGroupSpotAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TrainingSession $session,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Freier Platz: ' . $this->session->title . ' am ' . $this->session->date->format('d.m.Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest_group_spot_available',
        );
    }
}
