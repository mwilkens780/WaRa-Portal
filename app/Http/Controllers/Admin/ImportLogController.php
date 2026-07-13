<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Models\Setting;
use App\Services\Crawler\DsvCrawler;
use App\Services\Crawler\DsvDataCrawler;
use App\Services\Crawler\NsvCrawler;
use App\Services\Crawler\ShsvCrawler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportLogController extends Controller
{
    // Verifizierte DSV-Daten StateIDs (Bundesländer)
    public const DSV_STATES = [
        ['id' => 5,  'name' => 'Bremen',                 'short' => 'BSSV'],
        ['id' => 6,  'name' => 'Hamburg',                'short' => 'HSV'],
        ['id' => 8,  'name' => 'Mecklenburg-Vorpommern', 'short' => 'SVMV'],
        ['id' => 9,  'name' => 'Niedersachsen',           'short' => 'NSV'],
        ['id' => 14, 'name' => 'Schleswig-Holstein',      'short' => 'SHSV'],
        ['id' => 17, 'name' => 'Nordrhein-Westfalen',     'short' => 'WDSV'],
        ['id' => 12, 'name' => 'Sachsen',                 'short' => 'SVS'],
    ];

    private const CRAWLERS = [
        'shsv' => [
            'label'        => 'SHSV',
            'class'        => ShsvCrawler::class,
            'url'          => 'https://www.shsv.de/vereinswettkaempfe',
            'default_days' => [1, 2, 4],
            'default_time' => '06:00',
        ],
        'nsv' => [
            'label'        => 'NSV',
            'class'        => NsvCrawler::class,
            'url'          => 'https://www.norddeutscherschwimmverband.de/category/schwimmen/',
            'default_days' => [1],
            'default_time' => '06:30',
        ],
        'dsvdata' => [
            'label'        => 'DSV-Daten',
            'class'        => DsvDataCrawler::class,
            'url'          => 'https://dsvdaten.dsv.de',
            'default_days' => [1, 3, 5],
            'default_time' => '07:00',
            'has_states'   => true,
        ],
        'dsv' => [
            'label'        => 'DSV National',
            'class'        => DsvCrawler::class,
            'url'          => null,
            'default_days' => [7],
            'default_time' => '00:00',
            'note'         => 'DSV nutzt JS-Rendering – tut derzeit nichts.',
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

        // Per-crawler stats + live config from DB
        $crawlerStats = [];
        foreach (self::CRAWLERS as $source => $info) {
            $last   = ImportLog::where('source', $source)->orderByDesc('id')->first();
            $counts = ImportLog::where('source', $source)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $days = Setting::getJson("crawler.{$source}.schedule_days", $info['default_days']);
            $time = Setting::getCached("crawler.{$source}.schedule_time", $info['default_time']);

            $crawlerStats[$source] = [
                ...$info,
                'last_entry'    => $last,
                'count_success' => (int)($counts['success'] ?? 0),
                'count_skipped' => (int)($counts['skipped'] ?? 0),
                'count_errors'  => (int)($counts['error']   ?? 0),
                // Live config
                'cfg_enabled'   => Setting::getBool("crawler.{$source}.enabled", true),
                'cfg_days'      => $days,
                'cfg_time'      => $time,
                'cfg_state_ids' => ($info['has_states'] ?? false)
                    ? Setting::getJson('crawler.dsvdata.state_ids', [14])
                    : [],
                'schedule'      => $this->buildScheduleLabel($days, $time),
            ];
        }

        $dsvStates = self::DSV_STATES;

        return view('admin.import-log.index', compact('logs', 'filters', 'crawlerStats', 'dsvStates'));
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

    public function saveConfig(Request $request, string $source)
    {
        if (!isset(self::CRAWLERS[$source])) {
            return back()->with('error', 'Unbekannte Crawler-Quelle: ' . $source);
        }

        $data = $request->validate([
            'enabled'         => ['boolean'],
            'schedule_days'   => ['nullable', 'array'],
            'schedule_days.*' => ['integer', 'between:1,7'],
            'schedule_time'   => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'state_ids'       => ['nullable', 'array'],
            'state_ids.*'     => ['integer'],
        ]);

        Setting::set("crawler.{$source}.enabled",
            $request->boolean('enabled') ? '1' : '0');
        Setting::set("crawler.{$source}.schedule_days",
            json_encode(array_map('intval', $data['schedule_days'] ?? [])));
        Setting::set("crawler.{$source}.schedule_time",
            $data['schedule_time'] ?? self::CRAWLERS[$source]['default_time']);

        if ($source === 'dsvdata') {
            Setting::set('crawler.dsvdata.state_ids',
                json_encode(array_map('intval', $data['state_ids'] ?? [14])));
        }

        Setting::clearCache();

        $label = self::CRAWLERS[$source]['label'];
        return back()->with('success', "Konfiguration fuer \"{$label}\" gespeichert.");
    }

    private function buildScheduleLabel(array $days, string $time): string
    {
        $dayNames = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $dayStr   = implode(' / ', array_map(fn($d) => $dayNames[$d] ?? $d, $days));
        return $dayStr ? "{$dayStr}, {$time} Uhr" : '–';
    }
}
