<?php

namespace App\Services\Import;

use App\Models\Competition;
use App\Models\ImportLog;
use App\Services\DsvImportService;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ImportReport
{
    public int $imported = 0;
    public int $skipped  = 0;
    public int $errors   = 0;
    public array $errorDetails = [];

    public function total(): int { return $this->imported + $this->skipped + $this->errors; }
}

class BatchImporter
{
    public function __construct(private DsvImportService $importService) {}

    public function importFromZip(string $zipPath, string $source = 'webclub_batch'): ImportReport
    {
        $report  = new ImportReport();
        $tempDir = sys_get_temp_dir() . '/wara_batch_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException("ZIP-Datei konnte nicht geöffnet werden: {$zipPath}");
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $this->processDirectory($tempDir, $source, $report);
        } finally {
            $this->rrmdir($tempDir);
        }

        return $report;
    }

    public function importFromDirectory(string $path, string $source = 'manual'): ImportReport
    {
        $report = new ImportReport();
        $this->processDirectory($path, $source, $report);
        return $report;
    }

    private function processDirectory(string $dir, string $source, ImportReport $report): void
    {
        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->processDirectory($file, $source, $report);
                continue;
            }

            if (!preg_match('/\.(DSV7|dsv7)$/', $file)) {
                continue;
            }

            $this->processFile($file, $source, $report);
        }
    }

    private function processFile(string $filePath, string $source, ImportReport $report): void
    {
        $hash = hash('sha256', file_get_contents($filePath));

        // Already imported?
        if (Competition::where('import_hash', $hash)->exists()) {
            ImportLog::create([
                'source'   => $source,
                'filename' => basename($filePath),
                'status'   => 'skipped',
                'message'  => 'Datei bereits importiert (Hash-Duplikat).',
            ]);
            $report->skipped++;
            return;
        }

        try {
            DB::transaction(function () use ($filePath, $source, $hash) {
                $this->importService->importResultsFile($filePath, $source, $hash);
            });
            $report->imported++;
        } catch (\Throwable $e) {
            ImportLog::create([
                'source'   => $source,
                'filename' => basename($filePath),
                'status'   => 'error',
                'message'  => $e->getMessage(),
            ]);
            $report->errors++;
            $report->errorDetails[] = basename($filePath) . ': ' . $e->getMessage();
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
