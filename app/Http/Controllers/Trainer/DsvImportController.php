<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\RelayResult;
use App\Models\User;
use App\Services\DsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DsvImportController extends Controller
{
    public function __construct(private DsvImportService $service) {}

    // ── Schritt 1: Upload-Formular ──────────────────────────────────────────

    public function index()
    {
        return view('trainer.dsv-import.upload');
    }

    // ── Schritt 2: Datei einlesen + in Session speichern ───────────────────

    public function upload(Request $request)
    {
        $request->validate([
            'dsv_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('dsv_file');

        // Accept .lef, .xml, .txt — mime type varies by tool
        $ext = strtolower($file->getClientOriginalExtension());
        if (!\App\Support\DsvFile::allowedExtension($ext)) {
            return back()->withErrors(['dsv_file' => 'Erlaubt sind ' . \App\Support\DsvFile::LABEL . '.']);
        }

        $path     = $file->store('dsv-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->service->parse($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['dsv_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        }

        if (empty($parsed['meets'])) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['dsv_file' => 'Keine gültigen Wettkampfdaten gefunden. Bitte prüfe das Dateiformat (Lenex XML).']);
        }

        // Auto-match athletes by name against existing users
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->get();

        foreach ($parsed['meets'] as &$meet) {
            foreach ($meet['clubs'] as &$club) {
                foreach ($club['athletes'] as &$athlete) {
                    $athlete['matched_user_id'] = $this->matchAthlete($athlete, $swimmers);
                }
            }
        }

        session([
            'dsv_import_file'   => $path,
            'dsv_import_parsed' => $parsed,
        ]);

        return redirect()->route('trainer.dsv-import.preview');
    }

    // ── Schritt 3: Vorschau + Athleten-Zuordnung ────────────────────────────

    public function preview()
    {
        if (!session()->has('dsv_import_parsed')) {
            return redirect()->route('trainer.dsv-import.index')
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $parsed   = session('dsv_import_parsed');
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->orderBy('name')->get();

        // Dieser Import legt immer einen neuen Wettkampf an. Gibt es im selben
        // Zeitraum schon einen (aus WebClub, Ausschreibung, Crawler), entstuende
        // er doppelt - die Vorschau weist darauf hin und verlinkt den Import dort.
        $possibleDuplicates = [];
        foreach ($parsed['meets'] as $mi => $meet) {
            try {
                $von = \Illuminate\Support\Carbon::parse($meet['startdate'])->subDay();
                $bis = \Illuminate\Support\Carbon::parse($meet['enddate'] ?: $meet['startdate'])->addDay();
            } catch (\Throwable $e) {
                continue;
            }
            $start = \Illuminate\Support\Carbon::parse($meet['startdate']);
            $possibleDuplicates[$mi] = Competition::whereBetween('date', [$von->toDateString(), $bis->toDateString()])
                ->get(['id', 'name', 'date', 'date_end', 'location', 'course'])
                ->sortBy(function ($c) use ($start, $meet) {
                    similar_text(mb_strtolower($c->name), mb_strtolower($meet['name']), $prozent);
                    return abs($c->date->diffInDays($start)) * 1000 - $prozent;
                })
                ->values();
        }

        return view('trainer.dsv-import.preview', compact('parsed', 'swimmers', 'possibleDuplicates'));
    }

    // ── Schritt 4: Import durchführen ───────────────────────────────────────

    public function execute(Request $request)
    {
        // target_competition_id: in einen vorhandenen Wettkampf zusammenfuehren
        // (die Vorschau schlaegt ihn vor, wenn es im Zeitraum schon einen gibt).
        // Ohne Ziel wird wie bisher ein neuer Wettkampf angelegt.
        $data = $request->validate([
            'meet_index'            => ['required', 'integer', 'min:0'],
            'target_competition_id' => ['nullable', 'integer', 'exists:competitions,id'],
            'comp_name'             => ['required_without:target_competition_id', 'nullable', 'string', 'max:255'],
            'comp_location'         => ['required_without:target_competition_id', 'nullable', 'string', 'max:255'],
            'comp_date'             => ['required_without:target_competition_id', 'nullable', 'date'],
            'comp_date_end'         => ['nullable', 'date', 'after_or_equal:comp_date'],
            'comp_type'             => ['required_without:target_competition_id', 'nullable', 'in:vereinsintern,regional,national,international,meisterschaften,einladung'],
            'comp_course'           => ['required_without:target_competition_id', 'nullable', 'in:Kurzbahn,Langbahn'],
            'mappings'              => ['present', 'array'],
        ], [], [
            'comp_name' => 'Name', 'comp_location' => 'Ort', 'comp_date' => 'Startdatum',
            'comp_type' => 'Kategorie', 'comp_course' => 'Bahnlänge',
        ]);

        $parsed   = session('dsv_import_parsed');
        $filePath = session('dsv_import_file');

        if (!$parsed) {
            return redirect()->route('trainer.dsv-import.index')
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $meet = $parsed['meets'][(int)$data['meet_index']] ?? null;
        if (!$meet) {
            return back()->withErrors(['meet_index' => 'Ungültiger Wettkampf-Index.']);
        }

        $merged = !empty($data['target_competition_id']);
        if ($merged) {
            // Zusammenfuehren: vorhandener Wettkampf bleibt massgeblich, nur
            // leere Angaben werden aus der Datei ergaenzt
            $competition = Competition::findOrFail($data['target_competition_id']);
            $ergaenzt = array_filter([
                'location' => $competition->location ? null : ($meet['city'] ?: null),
                'date_end' => $competition->date_end || $meet['enddate'] === $meet['startdate'] ? null : $meet['enddate'],
                'course'   => $competition->course ? null : ($meet['course'] ?? null),
            ]);
            if ($ergaenzt) {
                $competition->update($ergaenzt);
            }
        } else {
            $competition = Competition::create([
                'name'      => $data['comp_name'],
                'location'  => $data['comp_location'],
                'date'      => $data['comp_date'],
                'date_end'  => ($data['comp_date_end'] ?? null) ?: null,
                'type'      => $data['comp_type'],
                'course'    => $data['comp_course'],
            ]);
        }

        $imported      = 0;
        $duplicates    = 0;
        $skipped       = 0;
        $relayImported = 0;

        foreach ($meet['clubs'] as $ci => $club) {
            foreach ($club['athletes'] as $ai => $athlete) {
                if ($athlete['is_relay'] ?? false) {
                    foreach ($athlete['results'] as $result) {
                        if (app(\App\Services\Import\RelayResultWriter::class)->store($competition->id, $club['name'], $athlete, $result)) {
                            $relayImported++;
                        } else {
                            $duplicates++;
                        }
                    }
                    continue;
                }

                $userId = (int)($data['mappings'][$ci][$ai] ?? 0);

                if (!$userId) {
                    $skipped++;
                    continue;
                }

                foreach ($athlete['results'] as $result) {
                    if ($this->importResult($competition->id, $userId, $result)) {
                        $imported++;
                    } else {
                        $duplicates++;
                    }
                }
            }
        }

        // Cleanup session + temp file
        session()->forget(['dsv_import_parsed', 'dsv_import_file']);
        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()->route('trainer.dsv-import.index')
            ->with('import_success', [
                'competition'   => $competition->name,
                'date'          => $competition->date->format('d.m.Y'),
                'imported'      => $imported,
                'skipped'       => $skipped,
                'relay_imported'=> $relayImported,
                'duplicates'    => $duplicates,
                'merged'        => $merged,
                'comp_id'       => $competition->id,
            ]);
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    private function matchAthlete(array $athlete, \Illuminate\Support\Collection $swimmers): ?int
    {
        if ($athlete['is_relay'] ?? false) return null;
        // DSV-ID ist eindeutig – vor dem Namen prüfen
        if (!empty($athlete['dsvid']) && ($byDsv = $swimmers->firstWhere('dsv_id', (string) $athlete['dsvid']))) {
            return $byDsv->id;
        }

        $aFirst = mb_strtolower(trim($athlete['firstname'] ?? ''));
        $aLast  = mb_strtolower(trim($athlete['lastname']  ?? ''));

        foreach ($swimmers as $swimmer) {
            $sFirst = mb_strtolower(trim($swimmer->firstname));
            $sLast  = mb_strtolower(trim($swimmer->lastname));

            if ($sFirst === $aFirst && $sLast === $aLast) {
                return $swimmer->id;
            }
        }

        // Fallback: match combined name (handles legacy "Vorname Nachname" entries)
        $combined = mb_strtolower(trim($athlete['name'] ?? ''));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->name)) === $combined) {
                return $swimmer->id;
            }
        }

        return null;
    }


    /** @return bool true = neu gespeichert, false = schon vorhanden (Zusammenfuehren) */
    private function importResult(int $competitionId, int $userId, array $result): bool
    {
        $ageGroup  = $result['age_group'] ?? null;
        $wertungen = !empty($result['wertungen']) ? $result['wertungen'] : ($ageGroup ? [$ageGroup] : null);
        $isFinal   = in_array($result['round_type'] ?? '', ['F', 'E']);
        // Übungsform (Beine, Kicks …): markiert übernehmen, keine Bestzeit
        $exercise  = \App\Support\Exercise::normalize($result['ausuebung'] ?? null);
        // Startschwimmer einer Staffel: offizielle Einzelzeit, eigenes Rennen
        $leadoff   = !empty($result['relay_leadoff']);

        // Dedup by physical swim: competition + user + discipline + distance + round_type + time
        $exists = CompetitionResult::where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->where('exercise', $exercise)
            ->where('relay_leadoff', $leadoff)
            ->where('is_final', $isFinal)
            ->where('time_ms', $result['time_ms'])
            ->exists();

        if ($exists) return false;

        $existingBest = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->whereNull('exercise')
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $isPb = !$exercise && (!$existingBest || $result['time_ms'] < $existingBest);

        if ($isPb && $existingBest) {
            CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->whereNull('exercise')
                ->where('is_personal_best', true)
                ->update(['is_personal_best' => false]);
        }

        CompetitionResult::create([
            'competition_id'   => $competitionId,
            'user_id'          => $userId,
            'discipline'       => $result['discipline'],
            'distance'         => $result['distance'],
            'exercise'         => $exercise,
            'relay_leadoff'    => $leadoff,
            'notes'            => $result['notes'] ?? null,
            'time_ms'          => $result['time_ms'],
            'placement'        => $result['place'],
            'is_personal_best' => $isPb,
            'age_group'        => $ageGroup,
            'wertungen'        => $wertungen,
            'is_final'         => $isFinal,
        ]);

        return true;
    }
}
