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
 *   4. On results pages: download DSV7, import results, save top-level PDFs as documents
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
        'dsv.de',
    ];

    private const SKIP_DOC_PATTERNS = [
        '/melde_wk\d+/i',
        '/proto_wk\d+/i',
        '/quali_wk\d+/i',
        '/melde_abs\d+/i',
        '/proto_abs\d+/i',
    ];

    public function __construct(private DsvImportService $importService) {}

    public function getSourceId(): string { return 'nsv'; }

    /** Interface requirement — not used directly; logic lives in run(). */
    public function fetchFiles(): iterable { return []; }

    // ── Public entry point ──────────────────────────────────────────────────

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        // Step 1: collect post URLs (with diagnostic logging)
        $postUrls = $this->collectPostUrls();

        // Step 2: for each post, find DSV7 files (direct + via external results pages)
        $diag = [
            'posts'       => count($postUrls),
            'ext_checked' => 0,
            'ext_errors'  => 0,
            'dsv7_found'  => 0,
        ];

        $files           = [];   // [ {content, filename, url, results_page_url?, results_page_html?} ]
        $visitedExternal = [];

        foreach ($postUrls as $postUrl) {
            $response = $this->http($postUrl);
            if (!$response) continue;
            $html = $response->body();

            // Direct DSV7 links in the post body
            foreach ($this->downloadDsv7Links($html, $postUrl) as $f) {
                $diag['dsv7_found']++;
                $files[] = $f;
            }

            // External event-homepage links — follow one level deep
            foreach ($this->extractExternalLinks($html) as $extUrl) {
                if (isset($visitedExternal[$extUrl])) continue;
                $visitedExternal[$extUrl] = true;
                $diag['ext_checked']++;

                $extResp = $this->http($extUrl);
                if (!$extResp) {
                    $diag['ext_errors']++;
                    ImportLog::create([
                        'source'     => $this->getSourceId(),
                        'source_url' => $extUrl,
                        'status'     => 'error',
                        'message'    => 'Externe Veranstaltungsseite nicht erreichbar',
                    ]);
                    continue;
                }

                $extHtml    = $extResp->body();
                $extDsv7    = $this->downloadDsv7Links($extHtml, $extUrl);
                $diag['dsv7_found'] += count($extDsv7);

                foreach ($extDsv7 as $f) {
                    $files[] = array_merge($f, [
                        'results_page_url'  => $extUrl,
                        'results_page_html' => $extHtml,
                    ]);
                }
            }
        }

        // Diagnostic summary in import log
        ImportLog::create([
            'source'     => $this->getSourceId(),
            'source_url' => self::CATEGORY_URL,
            'status'     => 'skipped',
            'message'    => sprintf(
                'Diagnose: %d Posts gefunden, %d externe Seiten geprüft (%d Fehler), %d DSV7-Dateien entdeckt',
                $diag['posts'], $diag['ext_checked'], $diag['ext_errors'], $diag['dsv7_found']
            ),
        ]);
        $stats['skipped']++;

        // Step 3: process each discovered DSV7 file
        foreach ($files as $file) {
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

                // Still try to add missing documents even if DSV7 already known
                if (!empty($file['results_page_url'])) {
                    $competition = Competition::where('import_hash', $hash)->first();
                    if ($competition) {
                        $this->importDocuments($competition, $file['results_page_url'], $file['results_page_html'] ?? '');
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

                if (!empty($file['results_page_url'])) {
                    $this->importDocuments($competition, $file['results_page_url'], $file['results_page_html'] ?? '');
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

        return $stats;
    }

    // ── Document import ─────────────────────────────────────────────────────

    private function importDocuments(Competition $competition, string $pageUrl, string $html): void
    {
        if (!$html) return;

        $cutAt      = $this->findSectionStart($html);
        $headerHtml = $cutAt !== false ? substr($html, 0, $cutAt) : $html;

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

            if ($this->isPerEventFile($filename)) continue;

            if (CompetitionDocument::where('competition_id', $competition->id)
                ->where('original_name', $filename)->exists()) {
                continue;
            }

            $fileResp = $this->http($absUrl);
            if (!$fileResp) continue;

            $content = $fileResp->body();
            $path    = "competition-docs/{$competition->id}/{$filename}";

            Storage::disk('local')->put($path, $content);

            CompetitionDocument::create([
                'competition_id' => $competition->id,
                'category'       => $this->guessCategory($filename, $linkText),
                'original_name'  => $filename,
                'path'           => $path,
                'size'           => strlen($content),
                'created_by_id'  => null,
            ]);

            Log::info('NsvCrawler: Dokument gespeichert', [
                'competition' => $competition->name,
                'file'        => $filename,
            ]);
        }
    }

    // ── Step 1: Collect post URLs ────────────────────────────────────────────

    private function collectPostUrls(): array
    {
        $postUrls = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $page === 1
                ? self::CATEGORY_URL
                : rtrim(self::CATEGORY_URL, '/') . '/page/' . $page . '/';

            $response = $this->http($url);
            if (!$response) {
                ImportLog::create([
                    'source'     => $this->getSourceId(),
                    'source_url' => $url,
                    'status'     => 'error',
                    'message'    => 'Kategorie-Seite nicht erreichbar (HTTP-Fehler oder SSL)',
                ]);
                break;
            }

            // WordPress post URLs: domain/YYYY/MM/DD/slug/ or domain/YYYY/slug/
            preg_match_all(
                '#href=["\'](' . preg_quote('https://' . self::NSV_HOST, '#') . '/\d{4}/[^"\'#?]+)["\']#i',
                $response->body(),
                $matches
            );

            $found = array_unique($matches[1] ?? []);

            // Also try relative post URLs
            preg_match_all('#href=["\'](/\d{4}/[^"\'#?]+)["\']#i', $response->body(), $rel);
            foreach (array_unique($rel[1] ?? []) as $relPath) {
                $found[] = 'https://' . self::NSV_HOST . $relPath;
            }
            $found = array_unique($found);

            if (empty($found) && $page > 1) break;

            $postUrls = array_merge($postUrls, $found);
            Log::debug("NsvCrawler: Seite {$page} → " . count($found) . ' Posts', ['url' => $url]);
        }

        return array_unique($postUrls);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function downloadDsv7Links(string $html, string $baseUrl): array
    {
        preg_match_all('/<a[^>]+href=["\']([^"\']*\.dsv7)["\'][^>]*>/i', $html, $matches);

        $files = [];
        foreach (array_unique($matches[1] ?? []) as $link) {
            $url  = $this->absoluteUrl($link, $baseUrl);
            $resp = $this->http($url);
            if (!$resp) continue;

            $files[] = [
                'content'  => $resp->body(),
                'filename' => basename(parse_url($url, PHP_URL_PATH) ?? 'result.DSV7'),
                'url'      => $url,
            ];
        }

        return $files;
    }

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
                if (str_contains($host, $pattern)) { $skip = true; break; }
            }
            if ($skip) continue;
            $links[] = $href;
        }

        return $links;
    }

    private function findSectionStart(string $html): int|false
    {
        if (preg_match('/<h[23][^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $m[0][1];
        }
        return false;
    }

    private function isPerEventFile(string $filename): bool
    {
        foreach (self::SKIP_DOC_PATTERNS as $pattern) {
            if (preg_match($pattern, $filename)) return true;
        }
        return false;
    }

    private function guessCategory(string $filename, string $linkText): string
    {
        $f = strtolower($filename);
        $t = strtolower($linkText);

        if (str_contains($t, 'ausschreibung') || str_contains($f, 'ausschreibung')) return 'ausschreibung';
        if (str_contains($t, 'meldeergebnis') || str_contains($t, 'meldung') || str_contains($t, 'zeitplan')
            || str_contains($f, 'melde_')) return 'meldeergebnis';
        if (str_contains($t, 'protokoll') || str_contains($t, 'medaillienspiegel') || str_contains($t, 'enm')
            || str_contains($f, 'proto_')) return 'protokoll';

        return 'sonstige';
    }

    private function http(string $url): ?\Illuminate\Http\Client\Response
    {
        try {
            $resp = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; WaRa-Portal-Crawler/1.0)',
                'Accept'          => 'text/html,application/pdf,application/octet-stream,*/*',
                'Accept-Language' => 'de-DE,de;q=0.9',
            ])->withOptions([
                'verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false),
            ])->timeout(30)->get($url);

            return $resp->successful() ? $resp : null;
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
