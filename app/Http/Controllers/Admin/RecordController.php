<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BestListEntry;
use App\Models\Record;
use App\Services\BestListService;
use App\Services\Import\BestListWorkbookParser;
use App\Services\RecordCheckService;
use App\Services\RecordImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordController extends Controller
{
    public function __construct(
        private RecordCheckService $checkService,
        private RecordImportService $importService,
        private BestListService $bestLists,
    ) {}

    // ── Index (tabbed VR / Ewige BL / Jahres BL / LR) ──────────────────────

    public function index()
    {
        $vereinsrekorde = Record::where('type', 'vereinsrekord')
            ->whereNull('age_group')
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')->orderBy('course')
            ->get();

        $landesrekorde = Record::where('type', 'landesrekord')
            ->whereNull('age_group')
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')->orderBy('course')
            ->get();

        // Bestenlisten werden berechnet: Portal-Ergebnisse + historische Eintraege,
        // je Strecke die 10 schnellsten Schwimmer, jahrgangsuebergreifend.
        $availableYears = $this->bestLists->availableYears();
        $annualYear     = (int) request('year', $availableYears->first() ?? now()->year);

        $eternal = [
            'Langbahn' => $this->bestLists->lists('Langbahn'),
            'Kurzbahn' => $this->bestLists->lists('Kurzbahn'),
        ];
        $annual = [
            'Langbahn' => $this->bestLists->lists('Langbahn', $annualYear),
            'Kurzbahn' => $this->bestLists->lists('Kurzbahn', $annualYear),
        ];

        return view('admin.records.index', compact(
            'vereinsrekorde', 'landesrekorde',
            'eternal', 'annual', 'availableYears', 'annualYear'
        ));
    }

    // ── Manual create/store ──────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'         => ['required', 'in:vereinsrekord,landesrekord'],
            'discipline'   => ['required', 'in:F,B,R,S,L'],
            'distance'     => ['required', 'integer', 'min:25'],
            'gender'       => ['required', 'in:M,F'],
            'age_group'    => ['nullable', 'string', 'max:20'],
            'course'       => ['required', 'in:Kurzbahn,Langbahn'],
            'swimmer_name' => ['required', 'string', 'max:255'],
            'time_minutes' => ['nullable', 'integer', 'min:0'],
            'time_seconds' => ['required', 'integer', 'min:0', 'max:59'],
            'time_cs'      => ['required', 'integer', 'min:0', 'max:99'],
            'set_date'     => ['nullable', 'date'],
            'location'     => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        $timeMs = (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000
            + $data['time_cs'] * 10;

        $ageGroup = $data['age_group'] ?: null;

        // Vereinsrekorde: nur offene Wertung und nur Strecken der VR-Liste
        if ($data['type'] === 'vereinsrekord') {
            $ageGroup = null;
            if (!Record::isVrEvent($data['discipline'], (int) $data['distance'], $data['course'])) {
                return back()->withErrors([
                    'distance' => "{$data['distance']} m {$data['discipline']} ({$data['course']}) gehört nicht zur Vereinsrekordliste.",
                ])->withInput();
            }
        }

        $existing = Record::where('type', $data['type'])
            ->where('discipline', $data['discipline'])
            ->where('distance', $data['distance'])
            ->where('gender', $data['gender'])
            ->where('age_group', $ageGroup)
            ->where('course', $data['course'])
            ->first();

        if ($existing && $timeMs >= $existing->time_ms) {
            return back()->withErrors([
                'time_seconds' => "Ein {$data['type']} mit besserer Zeit ({$existing->formatted_time}) existiert bereits für diese Kategorie.",
            ])->withInput();
        }

        Record::updateOrCreate(
            [
                'type'       => $data['type'],
                'discipline' => $data['discipline'],
                'distance'   => $data['distance'],
                'gender'     => $data['gender'],
                'age_group'  => $ageGroup,
                'course'     => $data['course'],
            ],
            [
                'swimmer_name'          => $data['swimmer_name'],
                'user_id'               => null,
                'time_ms'               => $timeMs,
                'set_date'              => $data['set_date'] ?: null,
                'location'              => $data['location'] ?: null,
                'competition_result_id' => null,
                'notes'                 => $data['notes'] ?: null,
            ]
        );

        // Re-check existing results for this specific category
        $this->checkService->recheckAll();

        return redirect()->route('admin.records.index')
            ->with('success', 'Rekord gespeichert und Ergebnisse geprüft.');
    }

    // ── Rekord bearbeiten ────────────────────────────────────────────────────
    //
    // Fuer Korrekturen an historischen Rekorden: Name, Zeit, Datum, Ort.
    // Strecke, Bahn und Geschlecht bleiben fest - sonst waere es ein anderer
    // Rekord. Danach werden die Ergebnisse neu geprueft, damit Markierungen
    // an den Wettkampfergebnissen zur geaenderten Zeit passen.

    public function update(Request $request, Record $record)
    {
        $data = $request->validate([
            'swimmer_name' => ['required', 'string', 'max:255'],
            'time_minutes' => ['nullable', 'integer', 'min:0'],
            'time_seconds' => ['required', 'integer', 'min:0', 'max:59'],
            'time_cs'      => ['required', 'integer', 'min:0', 'max:99'],
            'set_date'     => ['nullable', 'date'],
            'location'     => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        $record->update([
            'swimmer_name' => $data['swimmer_name'],
            'time_ms'      => (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000 + $data['time_cs'] * 10,
            'set_date'     => $data['set_date'] ?: null,
            'location'     => $data['location'] ?: null,
            'notes'        => $data['notes'] ?: null,
        ]);

        $this->checkService->recheckAll();

        return redirect()->route('admin.records.index', ['tab' => $record->type === 'landesrekord' ? 'lr' : 'vr'])
            ->with('success', 'Rekord aktualisiert und Ergebnisse geprüft.');
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Record $record)
    {
        $record->delete();
        return back()->with('success', 'Rekord gelöscht.');
    }

    // ── Import: upload + parse ────────────────────────────────────────────────

    public function importUpload(Request $request)
    {
        $request->validate([
            'record_file' => ['required', 'file', 'max:20480'],
            'import_type' => ['required', 'in:vereinsrekord,landesrekord'],
        ]);

        $file = $request->file('record_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt', 'pdf', 'docx', 'doc'])) {
            return back()->withErrors(['record_file' => 'Nicht unterstütztes Format. Erlaubt: xlsx, xls, csv, pdf, docx, doc.']);
        }

        $path     = $file->store('record-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            // Course is derived per-block from the CSV; LCM is only the fallback for non-structured formats
            $parsed = $this->importService->parse($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['record_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed)) {
            return back()->withErrors(['record_file' => 'Keine Rekord-Zeilen erkannt. Bitte Datei prüfen.']);
        }

        session([
            'record_import_rows' => $parsed,
            'record_import_type' => $request->input('import_type'),
        ]);

        return redirect()->route('admin.records.import.preview');
    }

    // ── Import: preview ───────────────────────────────────────────────────────

    public function importPreview()
    {
        $rows = session('record_import_rows');
        $type = session('record_import_type');

        if (!$rows) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Keine Importdaten gefunden. Bitte Datei erneut hochladen.');
        }

        return view('admin.records.import-preview', compact('rows', 'type'));
    }

    // ── Import: execute ───────────────────────────────────────────────────────

    public function importExecute(Request $request)
    {
        $type = session('record_import_type');

        if (!$type || !session()->has('record_import_rows')) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Sitzung abgelaufen. Bitte Datei erneut hochladen.');
        }

        $rows = $request->input('rows', []);
        $saved = 0;

        foreach ($rows as $row) {
            if (empty($row['include'])) continue;

            $discipline  = $row['discipline']   ?? null;
            $distance    = (int)($row['distance'] ?? 0);
            $gender      = $row['gender']        ?? null;
            $swimmerName = trim($row['swimmer_name'] ?? '');
            $ageGroup    = trim($row['age_group'] ?? '') ?: null;
            $rowCourse   = $row['course'] ?? 'Langbahn';
            $timeMs      = (int)($row['time_ms'] ?? 0);
            $setDate     = $row['set_date'] ?: null;
            $location    = trim($row['location'] ?? '') ?: null;

            if (!$discipline || !$distance || !$gender || !$swimmerName || $timeMs <= 0) continue;
            if (!in_array($discipline, ['F', 'B', 'R', 'S', 'L'])) continue;
            if (!in_array($gender, ['M', 'F'])) continue;

            // Vereinsrekorde: nur offene Wertung und nur Strecken der VR-Liste.
            // Enthaelt die Importliste Altersklassen, gewinnt die schnellste
            // Zeit ueber alle Klassen (Pruefung "besser als bestehend" unten).
            if ($type === 'vereinsrekord') {
                if (!Record::isVrEvent($discipline, $distance, $rowCourse)) continue;
                $ageGroup = null;
            }

            $existing = Record::where('type', $type)
                ->where('discipline', $discipline)
                ->where('distance', $distance)
                ->where('gender', $gender)
                ->where('age_group', $ageGroup)
                ->where('course', $rowCourse)
                ->first();

            // Only save if no existing record or this time is better
            if (!$existing || $timeMs < $existing->time_ms) {
                Record::updateOrCreate(
                    [
                        'type'       => $type,
                        'discipline' => $discipline,
                        'distance'   => $distance,
                        'gender'     => $gender,
                        'age_group'  => $ageGroup,
                        'course'     => $rowCourse,
                    ],
                    [
                        'swimmer_name'          => $swimmerName,
                        'user_id'               => null,
                        'time_ms'               => $timeMs,
                        'set_date'              => $setDate,
                        'location'              => $location,
                        'competition_result_id' => null,
                    ]
                );
                $saved++;
            }
        }

        session()->forget(['record_import_rows', 'record_import_type']);

        if ($saved > 0) {
            $this->checkService->recheckAll();
        }

        return redirect()->route('admin.records.index')
            ->with('success', "{$saved} Rekord(e) importiert und alle Wettkampfergebnisse geprüft.");
    }

    // ── Re-check all results ──────────────────────────────────────────────────

    public function recheckAll()
    {
        $this->checkService->recheckAll();
        return back()->with('success', 'Alle Wettkampfergebnisse wurden gegen die Rekordlisten geprüft.');
    }

    // ── Bestenlisten: Eintraege von Hand pflegen ─────────────────────────────
    //
    // Gepflegt werden nur historische bzw. manuelle Eintraege. Zeilen, die aus
    // einem Wettkampfergebnis stammen, werden berechnet und sind hier nicht
    // editierbar - dort ist das Ergebnis selbst zu korrigieren.

    public function storeBestListEntry(Request $request)
    {
        $data = $this->validateBestListEntry($request);

        BestListEntry::create($data + [
            'list_type' => 'eternal',   // Listen werden berechnet; Feld bleibt aus Altbestand
            'user_id'   => null,
            'source'    => 'manual',
        ]);

        return redirect()->route('admin.records.index', ['tab' => 'eternal'])
            ->with('success', 'Eintrag gespeichert.');
    }

    public function updateBestListEntry(Request $request, BestListEntry $bestListEntry)
    {
        abort_if($bestListEntry->competition_result_id !== null, 403,
            'Dieser Eintrag stammt aus einem Wettkampfergebnis und wird dort gepflegt.');

        $bestListEntry->update($this->validateBestListEntry($request));

        return redirect()->route('admin.records.index', ['tab' => request('tab', 'eternal')])
            ->with('success', 'Eintrag aktualisiert.');
    }

    public function destroyBestListEntry(BestListEntry $bestListEntry)
    {
        abort_if($bestListEntry->competition_result_id !== null, 403,
            'Dieser Eintrag stammt aus einem Wettkampfergebnis und wird dort gepflegt.');

        $bestListEntry->delete();

        return redirect()->route('admin.records.index', ['tab' => request('tab', 'eternal')])
            ->with('success', 'Eintrag gelöscht.');
    }

    /** @return array<string, mixed> */
    private function validateBestListEntry(Request $request): array
    {
        $data = $request->validate([
            'discipline'   => ['required', 'in:F,B,R,S,L'],
            'distance'     => ['required', 'integer', 'min:25'],
            'gender'       => ['required', 'in:M,F'],
            'course'       => ['required', 'in:Kurzbahn,Langbahn'],
            'birth_year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'set_year'     => ['required', 'integer', 'min:1900', 'max:2100'],
            'swimmer_name' => ['required', 'string', 'max:255'],
            'time_minutes' => ['nullable', 'integer', 'min:0'],
            'time_seconds' => ['required', 'integer', 'min:0', 'max:59'],
            'time_cs'      => ['required', 'integer', 'min:0', 'max:99'],
            'location'     => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        if (!Record::isVrEvent($data['discipline'], (int) $data['distance'], $data['course'])) {
            abort(422, "{$data['distance']} m {$data['discipline']} ({$data['course']}) gehört nicht zur Streckenliste.");
        }

        return [
            'discipline'   => $data['discipline'],
            'distance'     => (int) $data['distance'],
            'gender'       => $data['gender'],
            'course'       => $data['course'],
            'birth_year'   => $data['birth_year'] ?: null,
            'set_year'     => (int) $data['set_year'],
            'swimmer_name' => $data['swimmer_name'],
            'time_ms'      => (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000 + $data['time_cs'] * 10,
            'location'     => $data['location'] ?: null,
            'notes'        => $data['notes'] ?: null,
        ];
    }

    // ── Bestenlisten: Import aus der Vereins-Excel ───────────────────────────

    public function importBestListUpload(Request $request)
    {
        $request->validate(['bestlist_file' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);

        if (!class_exists(\ZipArchive::class)) {
            return back()->withErrors(['bestlist_file' =>
                'Auf dem Server fehlt die PHP-Erweiterung "zip"; ohne sie lassen sich xlsx-Dateien nicht lesen.']);
        }

        $path     = $request->file('bestlist_file')->store('bestlist-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = (new BestListWorkbookParser())->parse($fullPath);
        } catch (\Throwable $e) {
            return back()->withErrors(['bestlist_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed['entries'])) {
            return back()->withErrors(['bestlist_file' =>
                'Keine Einträge erkannt. Erwartet wird die Vereinsvorlage: Kopfzeile mit Bahn und '
                . 'Geschlecht, darunter Blöcke je Strecke mit Platz, Name, Jahrgang, Zeit und Jahr.']);
        }

        session(['bestlist_import' => $parsed]);

        return redirect()->route('admin.bestlist.import.preview');
    }

    public function importBestListPreview()
    {
        $parsed = session('bestlist_import');
        if (!$parsed) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Keine Importdaten gefunden. Bitte Datei erneut hochladen.');
        }

        $entries = collect($parsed['entries']);

        return view('admin.records.bestlist-import-preview', [
            'entries'  => $entries,
            'warnings' => $parsed['warnings'],
            'courses'  => $entries->pluck('course')->unique()->values(),
            'existing' => BestListEntry::whereNull('competition_result_id')
                ->whereIn('course', $entries->pluck('course')->unique())
                ->whereIn('source', ['import'])->count(),
        ]);
    }

    public function importBestListExecute(Request $request)
    {
        $parsed = session('bestlist_import');
        if (!$parsed) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Sitzung abgelaufen. Bitte Datei erneut hochladen.');
        }

        $entries = collect($parsed['entries']);
        $courses = $entries->pluck('course')->unique();
        $replace = $request->boolean('replace', true);

        $removed = 0;
        if ($replace) {
            // Nur fruehere Importe derselben Bahn ersetzen - von Hand angelegte
            // Eintraege bleiben erhalten.
            $removed = BestListEntry::whereNull('competition_result_id')
                ->whereIn('course', $courses)
                ->where('source', 'import')
                ->delete();
        }

        foreach ($entries as $e) {
            BestListEntry::create([
                'list_type'    => 'eternal',
                'discipline'   => $e['discipline'],
                'distance'     => $e['distance'],
                'gender'       => $e['gender'],
                'course'       => $e['course'],
                'birth_year'   => $e['birth_year'],
                'set_year'     => $e['set_year'],
                'swimmer_name' => $e['swimmer_name'],
                'user_id'      => null,
                'time_ms'      => $e['time_ms'],
                'source'       => 'import',
            ]);
        }

        session()->forget('bestlist_import');

        $msg = "{$entries->count()} Einträge importiert (" . $courses->implode(', ') . ").";
        if ($removed > 0) $msg .= " {$removed} frühere importierte Einträge ersetzt.";

        return redirect()->route('admin.records.index', ['tab' => 'eternal'])->with('success', $msg);
    }

    public function export(Request $request)
    {
        $type   = $request->input('type', 'vereinsrekord');
        $course = $request->input('course', 'Langbahn');

        if (!in_array($type, ['vereinsrekord', 'landesrekord'])) abort(400);
        if (!in_array($course, ['Langbahn', 'Kurzbahn'])) abort(400);

        $records = Record::where('type', $type)
            ->where('course', $course)
            ->whereNull('age_group')
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')
            ->get();

        $filename = ($type === 'vereinsrekord' ? 'vereinsrekorde' : 'landesrekorde')
            . '_' . strtolower($course) . '_' . now()->format('Y-m-d') . '.csv';

        $labels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];

        return response()->streamDownload(function () use ($records, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Disziplin', 'Distanz', 'Geschlecht', 'Bahnlänge', 'Schwimmer', 'Zeit', 'Datum', 'Ort'], ';');
            foreach ($records as $r) {
                fputcsv($out, [
                    $labels[$r->discipline] ?? $r->discipline,
                    $r->distance,
                    $r->gender === 'M' ? 'Männlich' : 'Weiblich',
                    $r->course,
                    $r->swimmer_name,
                    $r->formatted_time,
                    $r->set_date?->format('d.m.Y') ?? '',
                    $r->location ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Export der berechneten Bestenliste (Platz, Name, Jahrgang, Zeit, Jahr) */
    public function exportBestList(Request $request)
    {
        $listType = $request->input('list_type', 'eternal');
        $course   = $request->input('course', 'Langbahn');
        $year     = $listType === 'annual' ? (int) $request->input('year', now()->year) : null;

        if (!in_array($listType, ['eternal', 'annual'])) abort(400);
        if (!in_array($course, ['Langbahn', 'Kurzbahn'])) abort(400);

        $lists    = $this->bestLists->lists($course, $year);
        $labels   = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
        $namePart = $listType === 'eternal' ? 'ewige_bestenliste' : "jahresbestenliste_{$year}";
        $filename = $namePart . '_' . strtolower($course) . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($lists, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Geschlecht', 'Strecke', 'Platz', 'Name', 'Jahrgang', 'Zeit', 'Jahr', 'Quelle'], ';');

            foreach ($lists as $gender => $events) {
                foreach ($events as $key => $rows) {
                    [$discipline, $distance] = explode('_', $key);
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $gender === 'M' ? 'Männlich' : 'Weiblich',
                            $distance . ' m ' . ($labels[$discipline] ?? $discipline),
                            $row['rank'],
                            $row['name'],
                            $row['birth_year'] ?? '',
                            \App\Models\SwimmingTime::formatMs($row['time_ms']),
                            $row['year'] ?? '',
                            $row['source'] === 'portal' ? 'Wettkampf' : 'historisch',
                        ], ';');
                    }
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
