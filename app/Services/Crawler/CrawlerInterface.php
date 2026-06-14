<?php

namespace App\Services\Crawler;

interface CrawlerInterface
{
    /**
     * Yield available DSV7 files from the source.
     *
     * @return iterable<array{content: string, filename: string, url: string}>
     */
    public function fetchFiles(): iterable;

    /**
     * Return the source identifier for import_log.source.
     * One of: 'shsv', 'nsv', 'dsv'
     */
    public function getSourceId(): string;

    /**
     * Run the full crawl-and-import cycle.
     * Internally calls fetchFiles() and processes each file via DsvImportService.
     */
    public function run(): array;
}
