<?php

use App\Services\Crawler\DsvCrawler;
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

// Saison-Score-Cache wöchentlich neu berechnen
Schedule::call(function () {
    $year    = now()->month >= 9 ? now()->year : now()->year - 1;  // Saison beginnt September
    $service = app(SaisonAuswertungService::class);
    $service->recalculate($year);
    if (now()->month >= 1 && now()->month <= 8) {
        $service->recalculate($year - 1);  // Vorjahr auffrischen
    }
})->weekly()->name('season-scores-recalc');
