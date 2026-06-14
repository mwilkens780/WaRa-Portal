<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\Federation;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Crawls NSV (Norddeutscher Schwimmverband) for DSV7 result files.
 *
 * The NSV has no single listing page — URLs are managed via a whitelist stored
 * in the federations table (additional url_list JSON column) or config.
 * In the absence of a persistent list, a default set of known URLs is used.
 */
class NsvCrawler implements CrawlerInterface
{
    private const FALLBACK_URLS = [
        'https://www.nsv-schwimmen.de/wettkampfsport/ergebnisse',
    ];

    public function __construct(private DsvImportService $importService) {}

    public function getSourceId(): string { return 'nsv'; }

    public function fetchFiles(): iterable
    {
        $indexUrls = self::FALLBACK_URLS;

        foreach ($indexUrls as $indexUrl) {
            $response = Http::withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])->timeout(30)->get($indexUrl);
            if ($response->failed()) {
                Log::warning('NsvCrawler: Seite nicht erreichbar', ['url' => $indexUrl]);
                continue;
            }

            preg_match_all(
                '/<a[^>]+href=["\']([^"\']*-Pr\.DSV7)["\'][^>]*>/i',
                $response->body(),
                $matches
            );

            foreach (array_unique($matches[1] ?? []) as $link) {
                $url = $this->absoluteUrl($link, $indexUrl);
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
