<?php

namespace App\Console\Commands;

use App\Services\Crawler\WebClubCrawler;
use Illuminate\Console\Command;

class WebClubCrawlCommand extends Command
{
    protected $signature = 'webclub:crawl
                            {--dry-run : Konfiguration prüfen, keine Daten schreiben}';

    protected $description = 'WebClub.app Playwright-Crawler: Veranstaltungen und Personen synchronisieren';

    public function handle(WebClubCrawler $crawler): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry-Run: Konfiguration wird geprüft, keine Daten werden geschrieben.');
        }

        $this->info('WebClub-Crawler gestartet…');

        try {
            $stats = $crawler->run();
        } catch (\Throwable $e) {
            $this->error('Fataler Fehler: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fertig: %d Wettkämpfe importiert/ergänzt, %d übersprungen, %d Personen synchronisiert, %d Fehler.',
            $stats['imported'],
            $stats['skipped'],
            $stats['persons_synced'] ?? 0,
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
