<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    // ── Index (tabbed VR / LR) ───────────────────────────────────────────────

    public function index()
    {
        $vereinsrekorde = Record::where('type', 'vereinsrekord')
            ->orderBy('discipline')->orderBy('distance')
            ->orderBy('gender')->orderBy('age_group')->orderBy('course')
            ->get();

        $landesrekorde = Record::where('type', 'landesrekord')
            ->orderBy('discipline')->orderBy('distance')
            ->orderBy('gender')->orderBy('age_group')->orderBy('course')
            ->get();

        $buildKlassen = fn($records) => $records->map(fn($r) => [
            'key'   => $r->gender . '|' . $r->course . '|' . ($r->age_group ?? ''),
            'label' => ($r->gender === 'F' ? 'Weiblich' : 'Männlich') . ', ' .
                       ($r->course === 'Kurzbahn' ? 'Kurzbahn' : 'Langbahn') . ', ' .
                       ($r->age_group ?: 'Offen'),
        ])->unique('key')->sortBy('label')->values();

        $vrKlassen = $buildKlassen($vereinsrekorde);
        $lrKlassen = $buildKlassen($landesrekorde);

        return view('admin.records.index', compact('vereinsrekorde', 'landesrekorde', 'vrKlassen', 'lrKlassen'));
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
}
