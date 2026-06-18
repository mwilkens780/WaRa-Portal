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
// Cron-Eintrag auf dem Server: * * * * * php /pfad/artisan schedule:run >> /dev/null 2>&1

Schedule::call(fn() => app(ShsvCrawler::class)->run())
    ->weeklyOn(1, '06:00')  // Montag (nach Wettkampfwochenenden)
    ->weeklyOn(2, '06:00')  // Dienstag
    ->weeklyOn(4, '06:00')  // Donnerstag (Masters)
    ->name('shsv-crawler')
    ->withoutOverlapping();

Schedule::call(fn() => app(NsvCrawler::class)->run())
    ->weeklyOn(1, '06:30')
    ->name('nsv-crawler')
    ->withoutOverlapping();

Schedule::call(fn() => app(DsvCrawler::class)->run())
    ->weekly()
    ->name('dsv-crawler')
    ->withoutOverlapping();

// DsvData-Crawler: Zeitplan aus Admin-Einstellungen lesen
try {
    $dsvDataEnabled = Setting::getBool('crawler.dsvdata.enabled', true);
    $dsvDataDays    = Setting::getJson('crawler.dsvdata.schedule_days', [3]); // Default: Mittwoch
    $dsvDataTime    = Setting::getCached('crawler.dsvdata.schedule_time', '07:00');

    if ($dsvDataEnabled && !empty($dsvDataDays)) {
        [$h, $m]     = array_pad(explode(':', $dsvDataTime, 2), 2, '00');
        // ISO-Tage (1=Mo…7=So) → Cron-Tage (0=So…6=Sa): 7→0, sonst unverändert
        $cronDays    = implode(',', array_map(fn($d) => $d === 7 ? 0 : (int) $d, $dsvDataDays));
        $cronExpr    = (int)$m . ' ' . (int)$h . ' * * ' . $cronDays;

        Schedule::call(fn() => app(DsvDataCrawler::class)->run())
            ->cron($cronExpr)
            ->name('dsvdata-crawler')
            ->withoutOverlapping();
    }
} catch (\Throwable $e) {
    // DB nicht verfügbar (z.B. vor erster Migration) → Fallback: Mittwoch 07:00
    Schedule::call(fn() => app(DsvDataCrawler::class)->run())
        ->weeklyOn(3, '07:00')
        ->name('dsvdata-crawler')
        ->withoutOverlapping();
}

// Saison-Score-Cache wöchentlich neu berechnen
Schedule::call(function () {
    $year    = now()->month >= 9 ? now()->year : now()->year - 1;  // Saison beginnt September
    $service = app(SaisonAuswertungService::class);
    $service->recalculate($year);
    if (now()->month >= 1 && now()->month <= 8) {
        $service->recalculate($year - 1);  // Vorjahr auffrischen
    }
})->weekly()->name('season-scores-recalc');
