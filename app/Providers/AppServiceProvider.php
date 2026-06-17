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

        // Im Wartungsmodus alle E-Mails an die Admin-Adresse umleiten
        Event::listen(MessageSending::class, function (MessageSending $event) {
            try {
                if (!Setting::getBool('maintenance_mode')) return;
            } catch (\Throwable) {
                return; // DB noch nicht verfügbar (z.B. bei migrate)
            }

            $msg = $event->message;
            if (!$msg instanceof Email) return;

            $admin = new Address('administrator@wara-portal.de', 'Administrator WaRa-Portal');
            $msg->getHeaders()->remove('To');
            $msg->getHeaders()->remove('Cc');
            $msg->getHeaders()->remove('Bcc');
            $msg->to($admin);
        });
    }
}
