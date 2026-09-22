<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BestListEntry;
use App\Models\CompetitionResult;
use App\Models\Record;
use App\Services\RecordCheckService;
use App\Services\TimePlausibility;
use Illuminate\Http\Request;

/**
 * Korrekturseite: Zeiten, die ueber ihre Strecke unmoeglich sind.
 *
 * Solche Zeilen entstehen durch falsche Zuordnungen beim Import - etwa eine
 * 50-m-Zwischenzeit, die als 200-m-Ergebnis gespeichert wurde. Der Import
 * laesst sie inzwischen nicht mehr durch; diese Seite ist fuer den Altbestand
 * und fuer von Hand gepflegte Eintraege.
 *
 * Gezeigt wird alles, was in Rekorde und Bestenlisten einfliessen koennte:
 * Wettkampfergebnisse, extern gepflegte Rekorde und historische
 * Bestenlisten-Eintraege.
 */
class TimeCheckController extends Controller
{
    public function __construct(private RecordCheckService $records) {}

    public function index()
    {
        $results = CompetitionResult::with(['user', 'competition'])
            ->whereRaw(TimePlausibility::sqlCondition('competition_results'))
            ->get()
            ->sortBy(fn($r) => $r->distance > 0 ? $r->time_ms / $r->distance : 0)
            ->values();

        $records = Record::whereRaw(TimePlausibility::sqlCondition('records'))
            ->orderBy('discipline')->orderBy('distance')->get();

        $entries = BestListEntry::whereNull('competition_result_id')
            ->whereRaw(TimePlausibility::sqlCondition('best_list_entries'))
            ->orderBy('discipline')->orderBy('distance')->get();

        return view('admin.corrections.times', compact('results', 'records', 'entries'));
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'results'   => ['array'],
            'results.*' => ['integer'],
            'records'   => ['array'],
            'records.*' => ['integer'],
            'entries'   => ['array'],
            'entries.*' => ['integer'],
        ]);

        // Nur loeschen, was auch jetzt noch unplausibel ist: ein veraltetes
        // Formular soll keine inzwischen korrigierte Zeile mitreissen.
        $deletedResults = CompetitionResult::whereIn('id', $data['results'] ?? [])
            ->whereRaw(TimePlausibility::sqlCondition('competition_results'))
            ->delete();

        $deletedRecords = Record::whereIn('id', $data['records'] ?? [])
            ->whereRaw(TimePlausibility::sqlCondition('records'))
            ->delete();

        $deletedEntries = BestListEntry::whereIn('id', $data['entries'] ?? [])
            ->whereNull('competition_result_id')
            ->whereRaw(TimePlausibility::sqlCondition('best_list_entries'))
            ->delete();

        if ($deletedResults + $deletedRecords + $deletedEntries === 0) {
            return back()->with('error', 'Es war nichts zum Löschen ausgewählt.');
        }

        $parts = [];
        if ($deletedResults) $parts[] = $deletedResults . ($deletedResults === 1 ? ' Ergebnis' : ' Ergebnisse');
        if ($deletedRecords) $parts[] = $deletedRecords . ($deletedRecords === 1 ? ' Rekord' : ' Rekorde');
        if ($deletedEntries) $parts[] = $deletedEntries . ($deletedEntries === 1 ? ' Bestenlisten-Eintrag' : ' Bestenlisten-Einträge');

        $msg = implode(', ', $parts) . ' gelöscht.';

        // Rekorde haengen an den Ergebnissen - nach dem Loeschen neu aufbauen
        if ($deletedResults > 0 || $deletedRecords > 0) {
            $this->records->recheckAll();
            $msg .= ' Rekorde und Bestenlisten wurden neu berechnet.';
        }

        return redirect()->route('admin.corrections.times.index')->with('success', $msg);
    }
}
