<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useTailwind();

        // Konvertiert UTC-Timestamps zur Berliner Ortszeit inkl. automatischem DST-Wechsel
        Carbon::macro('deBerlin', function (string $format = 'd.m.Y H:i:s') {
            /** @var Carbon $this */
            return $this->copy()->setTimezone('Europe/Berlin')->format($format);
        });

        // Im Wartungsmodus geht keine Mail an echte Mitglieder, sondern an die
        // hinterlegte Testadresse. App\Services\Mailer macht das selbst und
        // protokolliert es dabei; dieser Listener ist das Netz fuer Mails, die
        // noch direkt ueber Mail::to() verschickt werden.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            try {
                if (!Setting::getBool('maintenance_mode')) return;
                $testAddress = trim((string) Setting::getCached('mail_test_address', ''));
            } catch (\Throwable) {
                return; // DB noch nicht verfügbar (z.B. bei migrate)
            }

            $msg = $event->message;
            if (!$msg instanceof Email) return;

            // Ohne Testadresse wird gar nicht verschickt: lieber keine Mail als
            // eine echte Mail an ein Mitglied mitten in der Wartung.
            if ($testAddress === '') return false;

            $original = collect($msg->getTo())->map(fn(Address $a) => $a->getAddress())->implode(', ');
            if ($original === $testAddress) return;   // schon umgeleitet

            $msg->getHeaders()->remove('To');
            $msg->getHeaders()->remove('Cc');
            $msg->getHeaders()->remove('Bcc');
            $msg->to(new Address($testAddress));

            if ($original !== '' && !str_starts_with((string) $msg->getSubject(), '[TEST an ')) {
                $msg->subject('[TEST an ' . $original . '] ' . $msg->getSubject());
            }
        });
    }
}
