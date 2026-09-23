<?php

use App\Models\Setting;
use App\Services\Crawler\DsvCrawler;
use App\Services\Crawler\DsvDataCrawler;
use App\Services\Crawler\NsvCrawler;
use App\Services\Crawler\ShsvCrawler;
use App\Services\Crawler\WebClubCrawler;
use App\Services\GroupRoster;
use App\Services\Ranking\SaisonAuswertungService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scraper-Scheduler ──────────────────────────────────────────────────────
// Alle Zeiten / Tage aus der DB; Standardwerte greifen, wenn noch nichts konfiguriert.
// Cron auf dem Server: GET https://wara-portal.de/cron/run/{token} (minütlich)

$crawlerDefs = [
    'shsv'            => ['class' => ShsvCrawler::class,    'days' => [1, 2, 4], 'time' => '06:00'],
    'nsv'             => ['class' => NsvCrawler::class,     'days' => [1],       'time' => '06:30'],
    'dsvdata'         => ['class' => DsvDataCrawler::class, 'days' => [1, 3, 5], 'time' => '07:00'],
    'dsv'             => ['class' => DsvCrawler::class,     'days' => [7],       'time' => '00:00'],
    'webclub_crawler' => ['class' => WebClubCrawler::class, 'days' => [1, 4],    'time' => '04:00', 'default_enabled' => false],
];

foreach ($crawlerDefs as $source => $def) {
    try {
        $enabled = Setting::getBool("crawler.{$source}.enabled", $def['default_enabled'] ?? true);
        $days    = Setting::getJson("crawler.{$source}.schedule_days", $def['days']);
        $time    = Setting::getCached("crawler.{$source}.schedule_time", $def['time']);

        if (!$enabled || empty($days)) continue;

        [$h, $m] = array_pad(explode(':', $time, 2), 2, '00');
        // ISO (1=Mo…7=So) → Cron (0=So…6=Sa): 7 → 0
        $cronDays = implode(',', array_map(fn($d) => $d === 7 ? 0 : (int) $d, $days));
        $cronExpr = (int)$m . ' ' . (int)$h . ' * * ' . $cronDays;

        $class = $def['class'];
        Schedule::call(fn() => app($class)->run())
            ->cron($cronExpr)
            ->name("{$source}-crawler")
            ->withoutOverlapping();
    } catch (\Throwable) {
        // DB nicht verfügbar (z.B. vor erster Migration) → Standardwert
        [$h, $m]  = array_pad(explode(':', $def['time'], 2), 2, '00');
        $cronDays = implode(',', array_map(fn($d) => $d === 7 ? 0 : $d, $def['days']));
        $class    = $def['class'];
        Schedule::call(fn() => app($class)->run())
            ->cron((int)$m . ' ' . (int)$h . ' * * ' . $cronDays)
            ->name("{$source}-crawler")
            ->withoutOverlapping();
    }
}

// Gruppenaufstellung der laufenden Saison fortschreiben. Spaet am Abend,
// damit der Lauf am letzten Saisontag den Stand zum Saisonende einfriert.
Artisan::command('groups:snapshot-roster', function () {
    $n = app(GroupRoster::class)->snapshotRunningSeason();
    $this->info("{$n} Gruppenzugehoerigkeiten fuer die laufende Saison gespeichert.");
})->purpose('Gruppenaufstellung der laufenden Saison fuer die Leistungskriterien sichern');

Schedule::call(fn() => app(GroupRoster::class)->snapshotRunningSeason())
    ->dailyAt('23:45')
    ->name('group-roster-snapshot')
    ->withoutOverlapping();

// Anmeldeabfragen nach Wettkampfende automatisch schliessen - kurz nach
// Mitternacht, dann ist der letzte Wettkampftag vorbei. Jede geschlossene
// Abfrage bekommt einen Vermerk (close_reason / close_note).
Artisan::command('signups:close-expired', function () {
    $n = \App\Models\CompetitionSignupRequest::closeAfterCompetitionEnd();
    $this->info("{$n} Anmeldeabfrage(n) nach Wettkampfende geschlossen.");
})->purpose('Aktive Anmeldeabfragen vergangener Wettkaempfe schliessen');

Schedule::call(fn() => \App\Models\CompetitionSignupRequest::closeAfterCompetitionEnd())
    ->dailyAt('00:15')
    ->name('signups-close-expired')
    ->withoutOverlapping();

// Wartende Mails verschicken. Laeuft jede Minute mit dem Cron mit und nimmt
// sich je Lauf nur einen Block vor - so blockiert ein Massenversand weder den
// Webserver noch den Mailserver.
Artisan::command('mails:process', function () {
    $result = app(\App\Services\Mailer::class)->processQueue();
    $this->info("{$result['sent']} verschickt, {$result['failed']} fehlgeschlagen, {$result['remaining']} warten noch.");
})->purpose('Wartende Mails aus der Warteschlange verschicken');

Schedule::call(fn() => app(\App\Services\Mailer::class)->processQueue())
    ->everyMinute()
    ->name('mail-queue')
    ->withoutOverlapping();

// Versandprotokoll nach zwoelf Monaten loeschen - so steht es in der
// Datenschutzerklaerung, also muss es auch geschehen.
Artisan::command('mails:purge-log', function () {
    $n = \App\Models\MailMessage::where('created_at', '<', now()->subMonths(12))->delete();
    $this->info("{$n} Protokolleintraege geloescht.");
})->purpose('Mail-Versandprotokoll nach 12 Monaten bereinigen');

Schedule::call(fn() => \App\Models\MailMessage::where('created_at', '<', now()->subMonths(12))->delete())
    ->monthlyOn(1, '03:30')
    ->name('mail-log-purge')
    ->withoutOverlapping();

// Saison-Score-Cache wöchentlich neu berechnen
Schedule::call(function () {
    $year    = now()->month >= 9 ? now()->year : now()->year - 1;
    $service = app(SaisonAuswertungService::class);
    $service->recalculate($year);
    if (now()->month >= 1 && now()->month <= 8) {
        $service->recalculate($year - 1);
    }
})->weekly()->name('season-scores-recalc');
