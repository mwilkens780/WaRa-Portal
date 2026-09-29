<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\ImportLogController;
use App\Models\ImportLog;
use App\Support\ImportCatalog;

/**
 * Import-Center: alle Datei-Importe auf einer Seite, dazu der Stand der
 * automatischen Importe (Crawler).
 */
class ImportCenterController extends Controller
{
    public function index()
    {
        $user    = auth()->user();
        $imports = ImportCatalog::for($user);
        abort_if(empty($imports), 403, 'Für deine Rolle gibt es keine Importe.');

        $areas = collect($imports)->groupBy(fn($i) => $i['area'], preserveKeys: true);

        // Automatische Importe: letzter Lauf je Quelle (nur Admins sehen das Log)
        $crawlers = [];
        if ($user->isAdmin()) {
            $last = ImportLog::whereIn('source', array_keys(ImportLogController::CRAWLERS))
                ->selectRaw('source, MAX(imported_at) as last_at')
                ->groupBy('source')
                ->pluck('last_at', 'source');
            foreach (ImportLogController::CRAWLERS as $key => $c) {
                $crawlers[] = [
                    'label' => $c['label'],
                    // Quelle ohne Eintrag im Log (noch nie gelaufen)
                    'last'  => isset($last[$key]) ? \Illuminate\Support\Carbon::parse($last[$key]) : null,
                    'note'  => $c['note'] ?? null,
                ];
            }
        }

        return view('imports.index', compact('areas', 'crawlers'));
    }

    /** Schritt 1 fuer Importe ohne eigene Seite (z. B. Rekorde) */
    public function show(string $key)
    {
        $import = ImportCatalog::get($key);
        abort_unless($import && isset($import['upload']), 404);
        abort_unless(ImportCatalog::allows(auth()->user(), $import), 403);

        return view('imports.show', compact('key', 'import'));
    }
}
