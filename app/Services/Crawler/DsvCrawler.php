<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Crawls DSV (Deutscher Schwimm-Verband) for DM/DJM DSV7 result files.
 *
 * DSV event pages are linked from germanaquatics.de / dsv.de.
 * A curated list of known event result pages is maintained here.
 * Add new URLs when new national championships are announced.
 */
class DsvCrawler implements CrawlerInterface
{
    // Known DSV event result listing pages — extend as needed
    private const EVENT_PAGES = [
        'https://www.germanaquatics.de/ergebnisse',
        'https://www.dsv.de/schwimmen/wettkampfsport/ergebnisse',
    ];

    public function __construct(private DsvImportService $importService) {}

    public function getSourceId(): string { return 'dsv'; }

    public function fetchFiles(): iterable
    {
        foreach (self::EVENT_PAGES as $pageUrl) {
            $response = Http::withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])->timeout(30)->get($pageUrl);
            if ($response->failed()) {
                Log::warning('DsvCrawler: Seite nicht erreichbar', ['url' => $pageUrl]);
                continue;
            }

            preg_match_all(
                '/<a[^>]+href=["\']([^"\']*-Pr\.DSV7)["\'][^>]*>/i',
                $response->body(),
                $matches
            );

            foreach (array_unique($matches[1] ?? []) as $link) {
                $url = $this->absoluteUrl($link, $pageUrl);
                $fileResponse = Http::withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])->timeout(30)->get($url);
                if ($fileResponse->failed()) continue;

                yield [
                    'content'  => $fileResponse->body(),
                    'filename' => basename(parse_url($url, PHP_URL_PATH) ?? 'result.DSV7'),
                    'url'      => $url,
                ];
            }
        }
    }

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($this->fetchFiles() as $file) {
            $hash = hash('sha256', $file['content']);
            if (Competition::where('import_hash', $hash)->exists()) {
                $stats['skipped']++;
                continue;
            }

            $tmpPath = sys_get_temp_dir() . '/' . $file['filename'];
            file_put_contents($tmpPath, $file['content']);

            try {
                $this->importService->importResultsFile($tmpPath, $this->getSourceId(), $hash, $file['url']);
                $stats['imported']++;
            } catch (\Throwable $e) {
                ImportLog::create([
                    'source'     => $this->getSourceId(),
                    'source_url' => $file['url'],
                    'filename'   => $file['filename'],
                    'status'     => 'error',
                    'message'    => $e->getMessage(),
                ]);
                $stats['errors']++;
            } finally {
                @unlink($tmpPath);
            }
        }

        return $stats;
    }

    private function absoluteUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http')) return $href;
        $parsed = parse_url($base);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        return str_starts_with($href, '/') ? $origin . $href : rtrim($base, '/') . '/' . $href;
    }
}
