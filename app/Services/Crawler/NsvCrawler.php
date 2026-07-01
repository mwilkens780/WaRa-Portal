<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Crawls NSV (Norddeutscher Schwimmverband) for DSV7 result files.
 *
 * Strategy:
 *   1. Fetch the Schwimmen category archive (norddeutscherschwimmverband.de/category/schwimmen/)
 *   2. Extract individual post links from the archive pages (WordPress year-based URLs)
 *   3. Fetch each post and look for direct DSV7 download links in the content
 *   4. Also follow external event-homepage links found in posts one level deep
 *      to pick up DSV7 files hosted by meet organisers
 */
class NsvCrawler implements CrawlerInterface
{
    private const CATEGORY_URL = 'https://www.norddeutscherschwimmverband.de/category/schwimmen/';
    private const NSV_HOST     = 'www.norddeutscherschwimmverband.de';

    // How many paginated archive pages to scan (page 1, 2, 3 …)
    private const MAX_PAGES = 3;

    // Domains that are clearly not competition result pages — skip external links to these
    private const SKIP_DOMAINS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'youtube.com', 'google.com', 'google.de', 'maps.google',
        'whatsapp.com', 'linkedin.com', 'xing.com',
        'datenschutz', 'impressum', 'dsv.de', // DSV covered by DsvDataCrawler
    ];

    public function __construct(private DsvImportService $importService) {}

    public function getSourceId(): string { return 'nsv'; }

    // ── Public entry point ──────────────────────────────────────────────────

    public function run(): array
    {
        $stats     = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $filesSeen = 0;

        foreach ($this->fetchFiles() as $file) {
            $filesSeen++;
            $hash = hash('sha256', $file['content']);

            if (Competition::where('import_hash', $hash)->exists()) {
                ImportLog::create([
                    'source'     => $this->getSourceId(),
                    'source_url' => $file['url'],
                    'filename'   => $file['filename'],
                    'status'     => 'skipped',
                    'message'    => 'Hash-Duplikat – bereits importiert',
                ]);
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

        if ($filesSeen === 0) {
            ImportLog::create([
                'source'     => $this->getSourceId(),
                'source_url' => self::CATEGORY_URL,
                'filename'   => null,
                'status'     => 'skipped',
                'message'    => 'Keine DSV7-Ergebnisdateien in den NSV-Beiträgen gefunden',
            ]);
            $stats['skipped']++;
        }

        return $stats;
    }

    // ── File discovery ──────────────────────────────────────────────────────

    public function fetchFiles(): iterable
    {
        $postUrls        = $this->collectPostUrls();
        $visitedExternal = [];

        Log::info('NsvCrawler: ' . count($postUrls) . ' Beiträge gefunden', ['category' => self::CATEGORY_URL]);

        foreach ($postUrls as $postUrl) {
            $response = $this->http($postUrl);
            if (!$response) continue;

            $html = $response->body();

            // Direct DSV7 links inside the post
            foreach ($this->downloadDsv7Links($html, $postUrl) as $file) {
                yield $file;
            }

            // External event-homepage links — follow one level deep
            foreach ($this->extractExternalLinks($html) as $extUrl) {
                if (isset($visitedExternal[$extUrl])) continue;
                $visitedExternal[$extUrl] = true;

                $extResp = $this->http($extUrl);
                if (!$extResp) continue;

                foreach ($this->downloadDsv7Links($extResp->body(), $extUrl) as $file) {
                    yield $file;
                }
            }
        }
    }

    // ── Step 1: Collect post URLs from the category archive ─────────────────

    private function collectPostUrls(): array
    {
        $postUrls = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $page === 1
                ? self::CATEGORY_URL
                : rtrim(self::CATEGORY_URL, '/') . '/page/' . $page . '/';

            $response = $this->http($url);
            if (!$response) {
                Log::warning('NsvCrawler: Kategorie-Seite nicht erreichbar', ['url' => $url]);
                ImportLog::create([
                    'source'     => $this->getSourceId(),
                    'source_url' => $url,
                    'status'     => 'error',
                    'message'    => 'Kategorie-Seite nicht erreichbar',
                ]);
                break;
            }

            // WordPress post URLs contain the year: /2024/01/15/post-slug/
            preg_match_all(
                '#href=["\'](' . preg_quote('https://' . self::NSV_HOST, '#') . '/\d{4}/[^"\']+)["\']#i',
                $response->body(),
                $matches
            );

            $found = array_unique($matches[1] ?? []);

            // Stop paginating if this page has no posts (likely beyond last page)
            if (empty($found) && $page > 1) break;

            $postUrls = array_merge($postUrls, $found);
            Log::debug("NsvCrawler: Seite {$page} → " . count($found) . ' Beitrags-Links', ['url' => $url]);
        }

        return array_unique($postUrls);
    }

    // ── Step 2: Download all DSV7 files linked in an HTML page ──────────────

    private function downloadDsv7Links(string $html, string $baseUrl): array
    {
        preg_match_all(
            '/<a[^>]+href=["\']([^"\']*\.DSV7)["\'][^>]*>/i',
            $html,
            $matches
        );

        $files = [];
        foreach (array_unique($matches[1] ?? []) as $link) {
            $url          = $this->absoluteUrl($link, $baseUrl);
            $fileResponse = $this->http($url);
            if (!$fileResponse) continue;

            $files[] = [
                'content'  => $fileResponse->body(),
                'filename' => basename(parse_url($url, PHP_URL_PATH) ?? 'result.DSV7'),
                'url'      => $url,
            ];
        }

        return $files;
    }

    // ── Step 3: Extract external event-homepage links from a post ───────────

    private function extractExternalLinks(string $html): array
    {
        preg_match_all(
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i',
            $html,
            $matches
        );

        $links = [];
        foreach (array_unique($matches[1] ?? []) as $href) {
            if (!str_starts_with($href, 'http')) continue;

            // Must be external (not NSV itself)
            $host = parse_url($href, PHP_URL_HOST) ?? '';
            if ($host === self::NSV_HOST) continue;

            // Skip obviously non-result domains
            $skip = false;
            foreach (self::SKIP_DOMAINS as $pattern) {
                if (str_contains($host, $pattern) || str_contains($href, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $links[] = $href;
        }

        return $links;
    }

    // ── HTTP helper ──────────────────────────────────────────────────────────

    private function http(string $url): ?\Illuminate\Http\Client\Response
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; WaRa-Portal-Crawler/1.0)',
                'Accept'          => 'text/html,application/octet-stream,*/*',
                'Accept-Language' => 'de-DE,de;q=0.9',
            ])->withOptions([
                'verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false),
            ])->timeout(30)->get($url);

            return $response->successful() ? $response : null;
        } catch (\Throwable $e) {
            Log::warning('NsvCrawler: HTTP-Fehler', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function absoluteUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http')) return $href;
        $parsed = parse_url($base);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        return str_starts_with($href, '/') ? $origin . $href : rtrim($base, '/') . '/' . $href;
    }
}
