<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShsvCrawler implements CrawlerInterface
{
    private const INDEX_URL = 'https://www.shsv.de/vereinswettkaempfe';

    public function __construct(private DsvImportService $importService) {}

    public function getSourceId(): string { return 'shsv'; }

    public function fetchFiles(): iterable
    {
        $response = Http::withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])->timeout(30)->get(self::INDEX_URL);

        if ($response->failed()) {
            Log::warning('ShsvCrawler: Index-Seite nicht erreichbar', ['url' => self::INDEX_URL]);
            return;
        }

        preg_match_all(
            '/<a[^>]+href=["\']([^"\']*-Pr\.DSV7)["\'][^>]*>/i',
            $response->body(),
            $matches
        );

        $links = array_unique($matches[1] ?? []);

        foreach ($links as $link) {
            $url = $this->absoluteUrl($link);
            $fileResponse = Http::withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])->timeout(30)->get($url);

            if ($fileResponse->failed()) {
                Log::warning('ShsvCrawler: Datei nicht herunterladbar', ['url' => $url]);
                continue;
            }

            yield [
                'content'  => $fileResponse->body(),
                'filename' => basename(parse_url($url, PHP_URL_PATH) ?? 'result.DSV7'),
                'url'      => $url,
            ];
        }
    }

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($this->fetchFiles() as $file) {
            $hash = hash('sha256', $file['content']);

            if (Competition::where('import_hash', $hash)->exists()) {
                ImportLog::create([
                    'source'   => $this->getSourceId(),
                    'source_url' => $file['url'],
                    'filename' => $file['filename'],
                    'status'   => 'skipped',
                    'message'  => 'Hash-Duplikat',
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

        return $stats;
    }

    private function absoluteUrl(string $href): string
    {
        if (str_starts_with($href, 'http')) return $href;
        $base = rtrim(self::INDEX_URL, '/');
        return $str = str_starts_with($href, '/')
            ? 'https://www.shsv.de' . $href
            : $base . '/' . $href;
    }
}
