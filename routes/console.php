<?php

use App\Models\Setting;
use App\Services\Crawler\DsvCrawler;
use App\Services\Crawler\DsvDataCrawler;
use App\Services\Crawler\NsvCrawler;
use App\Services\Crawler\ShsvCrawler;
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
    'shsv'    => ['class' => ShsvCrawler::class,    'days' => [1, 2, 4], 'time' => '06:00'],
    'nsv'     => ['class' => NsvCrawler::class,     'days' => [1],       'time' => '06:30'],
    'dsvdata' => ['class' => DsvDataCrawler::class, 'days' => [1, 3, 5], 'time' => '07:00'],
    'dsv'     => ['class' => DsvCrawler::class,     'days' => [7],       'time' => '00:00'],
];

foreach ($crawlerDefs as $source => $def) {
    try {
        $enabled = Setting::getBool("crawler.{$source}.enabled", true);
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

// Saison-Score-Cache wöchentlich neu berechnen
Schedule::call(function () {
    $year    = now()->month >= 9 ? now()->year : now()->year - 1;
    $service = app(SaisonAuswertungService::class);
    $service->recalculate($year);
    if (now()->month >= 1 && now()->month <= 8) {
        $service->recalculate($year - 1);
    }
})->weekly()->name('season-scores-recalc');
