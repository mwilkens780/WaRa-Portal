<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Services\Crawler\DsvCrawler;
use App\Services\Crawler\DsvDataCrawler;
use App\Services\Crawler\NsvCrawler;
use App\Services\Crawler\ShsvCrawler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportLogController extends Controller
{
    private const CRAWLERS = [
        'shsv' => [
            'label'    => 'SHSV',
            'class'    => ShsvCrawler::class,
            'url'      => 'https://www.shsv.de/vereinswettkaempfe',
            'schedule' => 'Mo / Di / Do, 06:00 Uhr',
        ],
        'nsv' => [
            'label'    => 'NSV',
            'class'    => NsvCrawler::class,
            'url'      => 'https://www.nsv-schwimmen.de/wettkampfsport/ergebnisse',
            'schedule' => 'Mo, 06:30 Uhr',
        ],
        'dsvdata' => [
            'label'    => 'DSV-Daten',
            'class'    => DsvDataCrawler::class,
            'url'      => 'https://dsvdaten.dsv.de',
            'schedule' => 'Mi, 07:00 Uhr (via Einstellungen konfigurierbar)',
        ],
        'dsv' => [
            'label'    => 'DSV National',
            'class'    => DsvCrawler::class,
            'url'      => null,
            'schedule' => 'Wöchentlich (So)',
            'note'     => 'DSV nutzt JS-Rendering – keine URLs konfiguriert, tut derzeit nichts',
        ],
    ];

    public function index(Request $request)
    {
        $filters = $request->only(['source', 'status', 'von', 'bis']);

        $query = ImportLog::with('competition')->orderByDesc('id');

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['von'])) {
            $query->whereDate('imported_at', '>=', $filters['von']);
        }
        if (!empty($filters['bis'])) {
            $query->whereDate('imported_at', '<=', $filters['bis']);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Per-crawler stats for the status cards
        $crawlerStats = [];
        foreach (self::CRAWLERS as $source => $info) {
            $last   = ImportLog::where('source', $source)->orderByDesc('id')->first();
            $counts = ImportLog::where('source', $source)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $crawlerStats[$source] = [
                ...$info,
                'last_entry'    => $last,
                'count_success' => (int)($counts['success'] ?? 0),
                'count_skipped' => (int)($counts['skipped'] ?? 0),
                'count_errors'  => (int)($counts['error']   ?? 0),
            ];
        }

        return view('admin.import-log.index', compact('logs', 'filters', 'crawlerStats'));
    }

    public function run(string $source)
    {
        if (!isset(self::CRAWLERS[$source])) {
            return back()->with('error', 'Unbekannte Crawler-Quelle: ' . $source);
        }

        $info = self::CRAWLERS[$source];

        try {
            $stats = app($info['class'])->run();
            $msg   = "Crawler \"{$info['label']}\" abgeschlossen: "
                   . "{$stats['imported']} importiert, "
                   . "{$stats['skipped']} übersprungen, "
                   . "{$stats['errors']} Fehler.";
            return back()->with('crawler_result', $msg);
        } catch (\Throwable $e) {
            Log::error("Manueller Crawler-Aufruf fehlgeschlagen: {$source}", ['error' => $e->getMessage()]);
            return back()->with('error', "Crawler-Fehler ({$info['label']}): " . $e->getMessage());
        }
    }
}
