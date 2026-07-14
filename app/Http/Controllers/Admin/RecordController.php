<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BestListEntry;
use App\Models\Record;
use App\Services\RecordCheckService;
use App\Services\RecordImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordController extends Controller
{
    public function __construct(
        private RecordCheckService $checkService,
        private RecordImportService $importService,
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

        // Ewige Bestenlisten: grouped by discipline+distance+gender+course → sorted by birth_year → top 10 per group
        $eternalEntries = BestListEntry::where('list_type', 'eternal')
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')->orderBy('course')
            ->orderBy('birth_year')->orderBy('time_ms')
            ->get();

        // Jahresbestenlisten: grouped by set_year → discipline+distance+gender+course → sorted by birth_year
        $annualEntries = BestListEntry::where('list_type', 'annual')
            ->orderBy('set_year', 'desc')
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')->orderBy('course')
            ->orderBy('birth_year')->orderBy('time_ms')
            ->get();

        $availableYears = $annualEntries->pluck('set_year')->filter()->unique()->sortDesc()->values();

        return view('admin.records.index', compact(
            'vereinsrekorde', 'landesrekorde',
            'eternalEntries', 'annualEntries', 'availableYears'
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

    // ── BestListEntry: manual store ───────────────────────────────────────────

    public function storeBestListEntry(Request $request)
    {
        $data = $request->validate([
            'list_type'    => ['required', 'in:eternal,annual'],
            'discipline'   => ['required', 'in:F,B,R,S,L'],
            'distance'     => ['required', 'integer', 'min:25'],
            'gender'       => ['required', 'in:M,F'],
            'birth_year'   => ['required', 'integer', 'min:1900', 'max:2030'],
            'course'       => ['required', 'in:Kurzbahn,Langbahn'],
            'set_year'     => ['nullable', 'integer', 'min:1900', 'max:2030'],
            'swimmer_name' => ['required', 'string', 'max:255'],
            'time_minutes' => ['nullable', 'integer', 'min:0'],
            'time_seconds' => ['required', 'integer', 'min:0', 'max:59'],
            'time_cs'      => ['required', 'integer', 'min:0', 'max:99'],
            'set_date'     => ['nullable', 'date'],
            'location'     => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        $timeMs  = (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000 + $data['time_cs'] * 10;
        $setYear = $data['list_type'] === 'annual'
            ? ($data['set_year'] ?: ($data['set_date'] ? (int) substr($data['set_date'], 0, 4) : null))
            : null;

        BestListEntry::create([
            'list_type'    => $data['list_type'],
            'discipline'   => $data['discipline'],
            'distance'     => $data['distance'],
            'gender'       => $data['gender'],
            'birth_year'   => $data['birth_year'],
            'course'       => $data['course'],
            'set_year'     => $setYear,
            'swimmer_name' => $data['swimmer_name'],
            'user_id'      => null,
            'time_ms'      => $timeMs,
            'set_date'     => $data['set_date'] ?: null,
            'location'     => $data['location'] ?: null,
            'notes'        => $data['notes'] ?: null,
        ]);

        return redirect()->route('admin.records.index', ['tab' => $data['list_type'] === 'eternal' ? 'eternal' : 'annual'])
            ->with('success', 'Eintrag gespeichert.');
    }

    // ── BestListEntry: destroy ────────────────────────────────────────────────

    public function destroyBestListEntry(BestListEntry $bestListEntry)
    {
        $tab = $bestListEntry->list_type === 'eternal' ? 'eternal' : 'annual';
        $bestListEntry->delete();
        return redirect()->route('admin.records.index', ['tab' => $tab])
            ->with('success', 'Eintrag gelöscht.');
    }

    // ── BestListEntry: import upload + preview + execute ─────────────────────

    public function importBestListUpload(Request $request)
    {
        $request->validate([
            'bestlist_file'   => ['required', 'file', 'max:20480'],
            'bestlist_type'   => ['required', 'in:eternal,annual'],
            'bestlist_course' => ['required', 'in:Langbahn,Kurzbahn'],
            'bestlist_year'   => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        $file = $request->file('bestlist_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt'])) {
            return back()->withErrors(['bestlist_file' => 'Nicht unterstütztes Format. Erlaubt: xlsx, xls, csv.']);
        }

        $path     = $file->store('bestlist-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->importService->parseBestList(
                $fullPath,
                $request->input('bestlist_course', 'Langbahn')
            );
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['bestlist_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed)) {
            return back()->withErrors(['bestlist_file' => 'Keine Einträge erkannt. Bitte Spaltenüberschriften prüfen (erwartet: Disziplin, Distanz, Geschlecht, Jahrgang, Name, Zeit).']);
        }

        session([
            'bestlist_import_rows'   => $parsed,
            'bestlist_import_type'   => $request->input('bestlist_type'),
            'bestlist_import_course' => $request->input('bestlist_course'),
            'bestlist_import_year'   => $request->input('bestlist_year'),
        ]);

        return redirect()->route('admin.bestlist.import.preview');
    }

    public function importBestListPreview()
    {
        $rows   = session('bestlist_import_rows');
        $type   = session('bestlist_import_type');
        $course = session('bestlist_import_course');
        $year   = session('bestlist_import_year');

        if (!$rows) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Keine Importdaten gefunden. Bitte Datei erneut hochladen.');
        }

        return view('admin.records.bestlist-import-preview', compact('rows', 'type', 'course', 'year'));
    }

    public function importBestListExecute(Request $request)
    {
        $type   = session('bestlist_import_type');
        $course = session('bestlist_import_course');
        $year   = session('bestlist_import_year');

        if (!$type || !session()->has('bestlist_import_rows')) {
            return redirect()->route('admin.records.index')
                ->with('error', 'Sitzung abgelaufen. Bitte Datei erneut hochladen.');
        }

        $rows  = $request->input('rows', []);
        $saved = 0;

        foreach ($rows as $row) {
            if (empty($row['include'])) continue;

            $discipline  = $row['discipline']   ?? null;
            $distance    = (int)($row['distance'] ?? 0);
            $gender      = $row['gender']        ?? null;
            $birthYear   = (int)($row['birth_year'] ?? 0);
            $swimmerName = trim($row['swimmer_name'] ?? '');
            $timeMs      = (int)($row['time_ms'] ?? 0);
            $setDate     = $row['set_date'] ?: null;
            $location    = trim($row['location'] ?? '') ?: null;

            $setYear = $type === 'annual'
                ? ((int)($row['set_year'] ?? $year ?? ($setDate ? (int) substr($setDate, 0, 4) : 0)) ?: null)
                : null;

            if (!$discipline || !$distance || !$gender || !$birthYear || !$swimmerName || $timeMs <= 0) continue;
            if (!in_array($discipline, ['F', 'B', 'R', 'S', 'L'])) continue;
            if (!in_array($gender, ['M', 'F'])) continue;
            if ($type === 'annual' && !$setYear) continue;

            BestListEntry::create([
                'list_type'    => $type,
                'discipline'   => $discipline,
                'distance'     => $distance,
                'gender'       => $gender,
                'birth_year'   => $birthYear,
                'course'       => $course,
                'set_year'     => $setYear,
                'swimmer_name' => $swimmerName,
                'user_id'      => null,
                'time_ms'      => $timeMs,
                'set_date'     => $setDate,
                'location'     => $location,
            ]);
            $saved++;
        }

        session()->forget(['bestlist_import_rows', 'bestlist_import_type', 'bestlist_import_course', 'bestlist_import_year']);

        return redirect()->route('admin.records.index', ['tab' => $type === 'eternal' ? 'eternal' : 'annual'])
            ->with('success', "{$saved} Einträge importiert.");
    }

    // ── Export ────────────────────────────────────────────────────────────────

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

    public function exportBestList(Request $request)
    {
        $listType = $request->input('list_type', 'eternal');
        $course   = $request->input('course', 'Langbahn');
        $year     = $request->input('year');

        if (!in_array($listType, ['eternal', 'annual'])) abort(400);
        if (!in_array($course, ['Langbahn', 'Kurzbahn'])) abort(400);

        $query = BestListEntry::where('list_type', $listType)
            ->where('course', $course)
            ->orderBy('discipline')->orderBy('distance')->orderBy('gender')
            ->orderBy('birth_year')->orderBy('time_ms');

        if ($listType === 'annual' && $year) {
            $query->where('set_year', (int) $year);
        }

        $entries  = $query->get();
        $namePart = $listType === 'eternal' ? 'ewige_bestenliste' : "jahresbestenliste_{$year}";
        $filename = $namePart . '_' . strtolower($course) . '_' . now()->format('Y-m-d') . '.csv';
        $labels   = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
        $isAnnual = $listType === 'annual';

        $headers = ['Disziplin', 'Distanz', 'Geschlecht', 'Jahrgang', 'Bahnlänge'];
        if ($isAnnual) $headers[] = 'Jahr';
        array_push($headers, 'Schwimmer', 'Zeit', 'Datum', 'Ort');

        return response()->streamDownload(function () use ($entries, $labels, $headers, $isAnnual) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($entries as $e) {
                $row = [
                    $labels[$e->discipline] ?? $e->discipline,
                    $e->distance,
                    $e->gender === 'M' ? 'Männlich' : 'Weiblich',
                    $e->birth_year,
                    $e->course,
                ];
                if ($isAnnual) $row[] = $e->set_year;
                array_push($row,
                    $e->swimmer_name,
                    $e->formatted_time,
                    $e->set_date?->format('d.m.Y') ?? '',
                    $e->location ?? '',
                );
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
