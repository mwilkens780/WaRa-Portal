<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\CompetitionDocument;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Crawls NSV (Norddeutscher Schwimmverband) for DSV7 result files.
 *
 * Strategy:
 *   1. Fetch the Schwimmen category archive (norddeutscherschwimmverband.de/category/schwimmen/)
 *   2. Extract individual post links (WordPress year-based URLs)
 *   3. Fetch each post, follow external event-homepage links one level deep
 *   4. On results pages (e.g. nfft.online/ergebnisse.php):
 *      a. Download the DSV7 file and import results
 *      b. Download top-level PDFs (Protokoll, Meldeergebnis, etc.) as CompetitionDocuments
 *         – per-event files (proto_wkNNN, melde_wkNNN …) are skipped
 */
class NsvCrawler implements CrawlerInterface
{
    private const CATEGORY_URL = 'https://www.norddeutscherschwimmverband.de/category/schwimmen/';
    private const NSV_HOST     = 'www.norddeutscherschwimmverband.de';
    private const MAX_PAGES    = 3;

    private const SKIP_DOMAINS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'youtube.com', 'google.com', 'google.de',
        'whatsapp.com', 'linkedin.com', 'xing.com',
        'dsv.de',   // already covered by DsvDataCrawler
    ];

    // Per-event / per-section filename patterns to skip when importing documents
    private const SKIP_DOC_PATTERNS = [
        '/melde_wk\d+/i',
        '/proto_wk\d+/i',
        '/quali_wk\d+/i',
        '/melde_abs\d+/i',
        '/proto_abs\d+/i',
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

                // Even if DSV7 already imported, still try to grab missing documents
                if (!empty($file['results_page_url']) && !empty($file['results_page_html'])) {
                    $competition = Competition::where('import_hash', $hash)->first();
                    if ($competition) {
                        $this->importDocuments($competition, $file['results_page_url'], $file['results_page_html']);
                    }
                }
                continue;
            }

            $tmpPath = sys_get_temp_dir() . '/' . $file['filename'];
            file_put_contents($tmpPath, $file['content']);

            try {
                $competition = $this->importService->importResultsFile(
                    $tmpPath, $this->getSourceId(), $hash, $file['url']
                );
                $stats['imported']++;

                // Import top-level documents from the results page
                if (!empty($file['results_page_url']) && !empty($file['results_page_html'])) {
                    $this->importDocuments($competition, $file['results_page_url'], $file['results_page_html']);
                }
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

            // Direct DSV7 links inside the post (no results-page HTML available)
            foreach ($this->downloadDsv7Links($html, $postUrl) as $file) {
                yield $file;
            }

            // External event-homepage links — follow one level deep
            foreach ($this->extractExternalLinks($html) as $extUrl) {
                if (isset($visitedExternal[$extUrl])) continue;
                $visitedExternal[$extUrl] = true;

                $extResp = $this->http($extUrl);
                if (!$extResp) continue;
                $extHtml = $extResp->body();

                foreach ($this->downloadDsv7Links($extHtml, $extUrl) as $file) {
                    // Attach the results-page URL and cached HTML so run() can import documents
                    yield array_merge($file, [
                        'results_page_url'  => $extUrl,
                        'results_page_html' => $extHtml,
                    ]);
                }
            }
        }
    }

    // ── Document import from a results page ─────────────────────────────────

    /**
     * Downloads top-level PDF documents from an event results page and stores
     * them as CompetitionDocuments.  Per-event files (proto_wkNNN etc.) are skipped.
     * Already-present documents (same original_name) are not duplicated.
     */
    private function importDocuments(Competition $competition, string $pageUrl, string $html): void
    {
        // Only look at the header section (before the first per-section heading)
        $cutAt = $this->findSectionStart($html);
        $headerHtml = $cutAt !== false ? substr($html, 0, $cutAt) : $html;

        // Extract all PDF links with their visible link text
        preg_match_all(
            '/<a[^>]+href=["\']([^"\']*\.pdf)["\'][^>]*>(.*?)<\/a>/is',
            $headerHtml,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $href     = $m[1];
            $linkText = trim(strip_tags($m[2]));
            $absUrl   = $this->absoluteUrl($href, $pageUrl);
            $filename = basename(parse_url($absUrl, PHP_URL_PATH) ?? 'document.pdf');

            // Skip per-event / per-section files
            if ($this->isPerEventFile($filename)) continue;

            // Skip if this document is already attached to this competition
            if (CompetitionDocument::where('competition_id', $competition->id)
                ->where('original_name', $filename)->exists()) {
                continue;
            }

            $fileResp = $this->http($absUrl);
            if (!$fileResp) continue;

            $content  = $fileResp->body();
            $path     = "competition-docs/{$competition->id}/{$filename}";

            Storage::disk('local')->put($path, $content);

            CompetitionDocument::create([
                'competition_id' => $competition->id,
                'category'       => $this->guessCategory($filename, $linkText),
                'original_name'  => $filename,
                'path'           => $path,
                'size'           => strlen($content),
                'created_by_id'  => null,
            ]);

            Log::info("NsvCrawler: Dokument gespeichert", [
                'competition' => $competition->name,
                'file'        => $filename,
                'url'         => $absUrl,
            ]);
        }
    }

    /** Position in HTML where per-section entries begin (first <h2> with "Abschnitt" or similar). */
    private function findSectionStart(string $html): int|false
    {
        if (preg_match('/<h[23][^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $m[0][1];
        }
        return false;
    }

    /** True if a filename belongs to a per-event or per-section result file (skip these). */
    private function isPerEventFile(string $filename): bool
    {
        foreach (self::SKIP_DOC_PATTERNS as $pattern) {
            if (preg_match($pattern, $filename)) return true;
        }
        return false;
    }

    /** Map filename / link text to a CompetitionDocument category. */
    private function guessCategory(string $filename, string $linkText): string
    {
        $f = strtolower($filename);
        $t = strtolower($linkText);

        if (str_contains($t, 'ausschreibung') || str_contains($f, 'ausschreibung')) {
            return 'ausschreibung';
        }
        if (str_contains($t, 'meldeergebnis') || str_contains($t, 'meldung') || str_contains($t, 'zeitplan')
            || str_contains($f, 'melde_')) {
            return 'meldeergebnis';
        }
        if (str_contains($t, 'protokoll') || str_contains($t, 'medaillienspiegel') || str_contains($t, 'enm')
            || str_contains($f, 'proto_')) {
            return 'protokoll';
        }
        return 'sonstige';
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

            preg_match_all(
                '#href=["\'](' . preg_quote('https://' . self::NSV_HOST, '#') . '/\d{4}/[^"\']+)["\']#i',
                $response->body(),
                $matches
            );

            $found = array_unique($matches[1] ?? []);
            if (empty($found) && $page > 1) break;

            $postUrls = array_merge($postUrls, $found);
            Log::debug("NsvCrawler: Seite {$page} → " . count($found) . ' Beitrags-Links', ['url' => $url]);
        }

        return array_unique($postUrls);
    }

    // ── Step 2: Download DSV7 files found in an HTML page ───────────────────

    private function downloadDsv7Links(string $html, string $baseUrl): array
    {
        preg_match_all(
            '/<a[^>]+href=["\']([^"\']*\.dsv7)["\'][^>]*>/i',
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

    // ── Step 3: Extract external event-homepage links ────────────────────────

    private function extractExternalLinks(string $html): array
    {
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        $links = [];
        foreach (array_unique($matches[1] ?? []) as $href) {
            if (!str_starts_with($href, 'http')) continue;
            $host = parse_url($href, PHP_URL_HOST) ?? '';
            if ($host === self::NSV_HOST) continue;

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
                'Accept'          => 'text/html,application/pdf,application/octet-stream,*/*',
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
