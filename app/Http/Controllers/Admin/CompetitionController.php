<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionDocument;
use App\Models\CompetitionEvent;
use App\Models\CompetitionResult;
use App\Models\SwimmingTime;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\CompetitionResultGrouper;
use App\Services\Competition\AusschreibungParserService;
use App\Services\DsvImportService;
use App\Services\Import\FullCompetitionImporter;
use App\Services\RecordCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CompetitionController extends Controller
{
    public function __construct(
        private DsvImportService      $service,
        private RecordCheckService    $recordCheck,
        private FullCompetitionImporter $fullImporter,
    ) {}

    public function index(Request $request)
    {
        $query = Competition::withCount('results')
            ->addSelect([
                'participants_count' => \App\Models\CompetitionResult::query()
                    ->selectRaw('COUNT(DISTINCT user_id)')
                    ->whereColumn('competition_id', 'competitions.id'),
                'entries_count' => \App\Models\CompetitionEntry::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('competition_id', 'competitions.id')
                    ->where('status', 'entered'),
            ]);

        if ($ort = $request->get('ort')) {
            $query->where('location', 'like', '%' . $ort . '%');
        }
        if ($von = $request->get('von')) {
            $query->whereDate('date', '>=', $von);
        }
        if ($bis = $request->get('bis')) {
            $query->whereDate('date', '<=', $bis);
        }
        if ($typ = $request->get('typ')) {
            $query->where('type', $typ);
        }

        $competitions = $query->orderByDesc('date')->paginate(20)->withQueryString();
        $filters = $request->only(['ort', 'von', 'bis', 'typ']);

        return view('admin.competitions.index', compact('competitions', 'filters'));
    }

    public function create()
    {
        // Pre-filled data from Lenex upload (Step 2 of Lenex flow)
        $lenexData = session('lenex_competition_data');

        return view('admin.competitions.create', compact('lenexData'));
    }

    // ── Lenex-Datei für Wettkampf-Anlage einlesen ──────────────────────────

    public function parseLenex(Request $request)
    {
        $request->validate([
            'lenex_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('lenex_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xml', 'lef', 'txt', 'dsv7'])) {
            return back()->withErrors(['lenex_file' => 'Nur .xml, .lef, .dsv7 oder .txt Dateien sind erlaubt.']);
        }

        $path     = $file->store('dsv-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->service->parseMeetDefinition($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['lenex_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        // If multiple meets, take first (user can switch via JS if needed)
        session(['lenex_competition_data' => $parsed['meets'][0]]);

        return redirect()->route('admin.competitions.create')
            ->with('lenex_loaded', true);
    }

    // ── Wettkampf anlegen (manuell oder aus Lenex) ─────────────────────────

    public function store(Request $request)
    {
        $levelValues = implode(',', array_keys(Competition::LEVEL_LABELS));
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'date_end'    => ['nullable', 'date', 'gte:date'],
            'type'        => ['required', 'in:vereinsintern,regional,national,international,meisterschaften,einladung,nop,dms,shsv'],
            'level'       => ['nullable', "in:{$levelValues}"],
            'organizer'   => ['nullable', 'string', 'max:255'],
            'course'      => ['nullable', 'in:Kurzbahn,Langbahn'],
            'description' => ['nullable', 'string'],
            'events_json' => ['nullable', 'json'],
        ]);

        // Duplicate check: same name + start date
        $existing = Competition::where('name', $data['name'])
            ->whereDate('date', $data['date'])
            ->first();

        if ($existing) {
            return back()->withInput()
                ->withErrors(['name' => 'Ein Wettkampf mit diesem Namen und Datum existiert bereits.'])
                ->with('duplicate_competition_id', $existing->id);
        }

        $events = [];
        if (!empty($data['events_json'])) {
            $events = json_decode($data['events_json'], true) ?? [];
        }

        $competition = DB::transaction(function () use ($data, $events) {
            $competition = Competition::create([
                'name'        => $data['name'],
                'location'    => $data['location'],
                'date'        => $data['date'],
                'date_end'    => $data['date_end'] ?? null,
                'type'        => $data['type'],
                'level'       => $data['level'] ?? null,
                'organizer'   => $data['organizer'] ?? null,
                'course'      => $data['course'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            foreach ($events as $ev) {
                $ageMin = (int)($ev['age_min'] ?? 0);
                $ageMax = (int)($ev['age_max'] ?? 0);

                CompetitionEvent::create([
                    'competition_id' => $competition->id,
                    'event_number'   => (int)($ev['event_number'] ?? 0),
                    'session_number' => (int)($ev['session_number'] ?? 1),
                    'session_date'   => $ev['session_date'] ?: null,
                    'session_name'   => $ev['session_name'] ?: null,
                    'discipline'     => $ev['discipline'],
                    'distance'       => (int)$ev['distance'],
                    'gender'         => $ev['gender'] ?? 'X',
                    // Normalize: 0 = "no minimum", 9999+ = "no maximum" → store as null
                    'age_min'             => ($ageMin > 0) ? $ageMin : null,
                    'age_max'             => ($ageMax > 0 && $ageMax < 9999) ? $ageMax : null,
                    'age_group'           => mb_substr($ev['age_group'] ?? '', 0, 50) ?: null,
                    'qualifying_time_ms'  => isset($ev['qualifying_time_ms']) && (int)$ev['qualifying_time_ms'] > 0
                                                ? (int)$ev['qualifying_time_ms'] : null,
                    'qualifying_deadline' => $ev['qualifying_deadline'] ?? null,
                    'meldegeld'           => isset($ev['meldegeld']) && (float)$ev['meldegeld'] > 0
                                                ? (float)$ev['meldegeld'] : null,
                ]);
            }

            return $competition;
        });

        session()->forget('lenex_competition_data');

        $eventCount = count($events);
        $msg = 'Wettkampf wurde angelegt.';
        if ($eventCount > 0) {
            $msg .= " {$eventCount} Startdisziplinen aus Lenex-Datei übernommen.";
        }

        return redirect()->route('admin.competitions.show', $competition)
            ->with('success', $msg);
    }

    public function show(Competition $competition)
    {
        $competition->load(['events' => fn($q) => $q
            ->orderBy('session_number')
            ->orderBy('event_number')
            ->orderBy('age_group'),
            'trainingGroups',
        ]);

        $rawResults = $competition->results()->with('user')->orderBy('discipline')->orderBy('distance')->get();
        $results    = CompetitionResultGrouper::forCompetition($rawResults);

        $swimmers         = User::where('role', 'schwimmer')->where('active', true)->orderBy('name')->get();
        $allGroups        = TrainingGroup::visibleTo(auth()->user())->orderBy('name')->get();
        $hasPflichtzeiten = $competition->events->where('qualifying_time_ms', '>', 0)->isNotEmpty();
        $hasMeldegelder   = $competition->events->where('meldegeld', '>', 0)->isNotEmpty();

        $signupRequest = $competition->signupRequest()->with(['responses.user', 'createdBy'])->first();

        // ── Qualifikations-Daten ──────────────────────────────────────────────
        $qualifyingEvents       = collect();
        $qualificationSwimmers  = collect();
        $qualResultsByUserEvent = [];   // "{uid}_{disc}_{dist}" → min time_ms
        $bestTimesByUserEvent   = [];   // same, but for Meldungen entry-time suggestion (unrestricted period)

        if ($hasPflichtzeiten && $signupRequest) {
            // Unique qualifying events (one row per discipline+distance combination)
            $qualifyingEvents = $competition->events
                ->where('qualifying_time_ms', '>', 0)
                ->sortBy(fn($e) => [$e->discipline, $e->distance])
                ->values();

            // Swimmers: all from assigned training groups + individually assigned
            $groupSwimmers = $competition->trainingGroups->isNotEmpty()
                ? User::where('active', true)
                    ->where('role', 'schwimmer')
                    ->whereHas('trainingGroups', fn($q) =>
                        $q->whereIn('training_groups.id', $competition->trainingGroups->pluck('id'))
                    )
                    ->orderBy('lastname')->orderBy('firstname')
                    ->get()
                : collect();

            $individualIds = collect($signupRequest->eligible_user_ids ?? []);
            $individualSwimmers = $individualIds->isNotEmpty()
                ? User::whereIn('id', $individualIds)->orderBy('lastname')->orderBy('firstname')->get()
                : collect();

            $qualificationSwimmers = $groupSwimmers->merge($individualSwimmers)->unique('id')->values();

            if ($qualificationSwimmers->isNotEmpty() && $qualifyingEvents->isNotEmpty()) {
                $qStart = $signupRequest->qualifying_period_start;
                $qEnd   = $signupRequest->qualifying_period_end;

                // Best qualifying-period results per user per discipline+distance
                $qResults = CompetitionResult::whereIn('user_id', $qualificationSwimmers->pluck('id'))
                    ->where('time_ms', '>', 0)
                    ->when($qStart || $qEnd, fn($q) =>
                        $q->whereHas('competition', fn($cq) =>
                            $cq->when($qStart, fn($q2) => $q2->where('date', '>=', $qStart))
                               ->when($qEnd,   fn($q2) => $q2->where('date', '<=', $qEnd))
                        )
                    )
                    ->orderBy('time_ms')
                    ->get();

                foreach ($qResults as $r) {
                    $key = "{$r->user_id}_{$r->discipline}_{$r->distance}";
                    if (!isset($qualResultsByUserEvent[$key]) || $r->time_ms < $qualResultsByUserEvent[$key]) {
                        $qualResultsByUserEvent[$key] = $r->time_ms;
                    }
                }

                // Best times overall (no date filter) for Meldungen entry-time suggestion
                $allResults = CompetitionResult::whereIn('user_id', $qualificationSwimmers->pluck('id'))
                    ->where('time_ms', '>', 0)
                    ->orderBy('time_ms')
                    ->get();

                foreach ($allResults as $r) {
                    $key = "{$r->user_id}_{$r->discipline}_{$r->distance}";
                    if (!isset($bestTimesByUserEvent[$key]) || $r->time_ms < $bestTimesByUserEvent[$key]) {
                        $bestTimesByUserEvent[$key] = $r->time_ms;
                    }
                }
            }
        }

        $hasQualifikation = $hasPflichtzeiten && $signupRequest;

        $documents = CompetitionDocument::where('competition_id', $competition->id)
            ->with('createdBy')
            ->orderBy('category')
            ->orderBy('created_at')
            ->get()
            ->groupBy('category');

        return view('admin.competitions.show',
            compact('competition', 'results', 'swimmers', 'allGroups',
                    'hasPflichtzeiten', 'hasMeldegelder', 'signupRequest',
                    'hasQualifikation', 'qualifyingEvents', 'qualificationSwimmers',
                    'qualResultsByUserEvent', 'bestTimesByUserEvent', 'documents'));
    }

    // ── Ausschreibungs-Import (PDF → Claude → strukturierte Daten) ────────────

    public function parseAnnouncement(Request $request, Competition $competition)
    {
        $request->validate([
            'announcement_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $path = $request->file('announcement_pdf')
                        ->store('announcements', 'local');

        try {
            $service = new AusschreibungParserService();
            $data    = $service->parseFromPath(storage_path('app/' . $path));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Keep the PDF on disk; path returned for potential save step
        return response()->json([
            'pdf_path' => $path,
            'data'     => $data,
        ]);
    }

    public function saveAnnouncement(Request $request, Competition $competition)
    {
        $request->validate([
            'pdf_path'        => ['nullable', 'string'],
            'announcement'    => ['required', 'array'],
            'apply_to_fields' => ['nullable', 'array'],  // which top-level fields to write
        ]);

        $data    = $request->input('announcement');
        $apply   = $request->input('apply_to_fields', [
            'name', 'level', 'date', 'date_end', 'location',
            'organizer', 'ausrichter', 'meldeschluss',
            'venue_details', 'kampfgericht', 'contact_info',
        ]);

        $service = new AusschreibungParserService();
        $mapped  = $service->mapToCompetitionFields($data);

        // Only update fields the trainer explicitly confirmed
        $toSave = array_intersect_key($mapped, array_flip($apply));

        // Always save announcement_data and pdf_path
        $toSave['announcement_data'] = $data;
        if ($pdfPath = $request->input('pdf_path')) {
            $toSave['announcement_pdf_path'] = $pdfPath;
        }

        $competition->update($toSave);

        // Optionally write qualifying times to competition_events
        if ($request->boolean('import_qualifying_times')) {
            $qtRows = $service->extractQualifyingTimes($data);
            foreach ($qtRows as $row) {
                CompetitionEvent::where([
                    'competition_id' => $competition->id,
                    'discipline'     => $row['discipline'],
                    'distance'       => $row['distance'],
                    'gender'         => $row['gender'],
                    'age_group'      => $row['age_group'],
                ])->update(['qualifying_time_ms' => $row['qualifying_time_ms']]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function saveOrganisation(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $competition->update(['organisation_notes' => ['text' => $data['notes'] ?? '']]);

        return back()->with('success', 'Organisationsnotizen gespeichert.');
    }

    public function syncGroups(Request $request, Competition $competition)
    {
        $request->validate(['groups' => ['nullable', 'array'], 'groups.*' => ['exists:training_groups,id']]);
        $competition->trainingGroups()->sync($request->input('groups', []));
        return back()->with('success', 'Startberechtigte Gruppen aktualisiert.');
    }

    public function edit(Competition $competition)
    {
        return view('admin.competitions.edit', compact('competition'));
    }

    public function update(Request $request, Competition $competition)
    {
        $levelValues = implode(',', array_keys(Competition::LEVEL_LABELS));
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'date_end'    => ['nullable', 'date', 'gte:date'],
            'type'        => ['required', 'in:vereinsintern,regional,national,international,meisterschaften,einladung,nop,dms,shsv'],
            'level'       => ['nullable', "in:{$levelValues}"],
            'organizer'   => ['nullable', 'string', 'max:255'],
            'course'      => ['nullable', 'in:Kurzbahn,Langbahn'],
            'description' => ['nullable', 'string'],
        ]);

        $competition->update($data);

        return redirect()->route('admin.competitions.show', $competition)
            ->with('success', 'Wettkampf wurde aktualisiert.');
    }

    public function destroy(Competition $competition)
    {
        $competition->delete();
        return redirect()->route('admin.competitions.index')
            ->with('success', 'Wettkampf wurde gelöscht.');
    }

    // ── Ergebnis manuell eintragen ─────────────────────────────────────────

    public function storeResult(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'user_id'           => ['required', 'exists:users,id'],
            'discipline'        => ['required', 'in:F,B,R,S,L'],
            'distance'          => ['required', 'integer', 'min:25'],
            'gender'            => ['nullable', 'in:M,F'],
            'time_minutes'      => ['nullable', 'integer', 'min:0'],
            'time_seconds'      => ['required', 'integer', 'min:0', 'max:59'],
            'time_centiseconds' => ['required', 'integer', 'min:0', 'max:99'],
            'placement'         => ['nullable', 'integer', 'min:1'],
            'age_group'         => ['nullable', 'string', 'max:20'],
            'notes'             => ['nullable', 'string'],
        ]);

        $timeMs = (($data['time_minutes'] ?? 0) * 60 + $data['time_seconds']) * 1000
            + $data['time_centiseconds'] * 10;

        $existingBest = CompetitionResult::where('user_id', $data['user_id'])
            ->where('discipline', $data['discipline'])
            ->where('distance', $data['distance'])
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $isPb = !$existingBest || $timeMs < $existingBest;

        $result = CompetitionResult::create([
            'competition_id'  => $competition->id,
            'user_id'         => $data['user_id'],
            'discipline'      => $data['discipline'],
            'distance'        => $data['distance'],
            'time_ms'         => $timeMs,
            'placement'       => $data['placement'] ?? null,
            'is_personal_best'=> $isPb,
            'age_group'       => $data['age_group'] ?? null,
            'gender'          => $data['gender'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        $this->recordCheck->checkResult($result->load(['user', 'competition']));

        return back()->with('success', 'Ergebnis wurde eingetragen.');
    }

    public function destroyResult(CompetitionResult $result)
    {
        // Cascade-delete all rows of the same physical swim (same time in same competition)
        CompetitionResult::where('competition_id', $result->competition_id)
            ->where('user_id', $result->user_id)
            ->where('discipline', $result->discipline)
            ->where('distance', $result->distance)
            ->where('time_ms', $result->time_ms)
            ->delete();

        return back()->with('success', 'Ergebnis wurde gelöscht.');
    }

    // ── Auswertungstext speichern / PDF-Export ───────────────────────────────

    public function saveAnalysis(Request $request, Competition $competition)
    {
        $request->validate(['text' => ['nullable', 'string']]);
        $competition->update(['analysis_text' => $request->input('text') ?: null]);
        return response()->json(['success' => true]);
    }

    public function exportAnalysisPdf(Competition $competition)
    {
        $rawResults = $competition->results()->with('user')->orderBy('discipline')->orderBy('distance')->get();
        $results    = CompetitionResultGrouper::forCompetition($rawResults);

        return view('competitions.auswertung-print', [
            'competition'  => $competition,
            'results'      => $results,
            'analysisHtml' => $competition->analysis_text ?? '',
        ]);
    }

    // ── Wettkampfdefinitionsdatei-Import für bestehenden Wettkampf ────────────

    public function importDefinition(Request $request, Competition $competition)
    {
        $request->validate([
            'def_file' => ['required', 'file', 'max:10240'],
        ]);

        $path     = $request->file('def_file')->store('dsv-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->service->parseMeetDefinition($fullPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return back()->with('error', 'Fehler beim Parsen der Definitionsdatei: ' . $e->getMessage());
        }

        Storage::disk('local')->delete($path);

        $meet = $parsed['meets'][0] ?? null;
        if (!$meet || empty($meet['events'])) {
            return back()->with('error', 'Keine Wettkampf-Events in der Datei gefunden.');
        }

        DB::transaction(function () use ($competition, $meet) {
            $competition->events()->delete();

            foreach ($meet['events'] as $ev) {
                $ageMin = (int)($ev['age_min'] ?? 0);
                $ageMax = (int)($ev['age_max'] ?? 0);

                CompetitionEvent::create([
                    'competition_id'      => $competition->id,
                    'event_number'        => (int)($ev['event_number'] ?? 0),
                    'session_number'      => (int)($ev['session_number'] ?? 1),
                    'session_date'        => $ev['session_date'] ?: null,
                    'session_name'        => $ev['session_name'] ?: null,
                    'discipline'          => $ev['discipline'],
                    'distance'            => (int)$ev['distance'],
                    'gender'              => $ev['gender'] ?? 'X',
                    'age_min'             => ($ageMin > 0) ? $ageMin : null,
                    'age_max'             => ($ageMax > 0 && $ageMax < 9999) ? $ageMax : null,
                    'age_group'           => mb_substr($ev['age_group'] ?? '', 0, 50) ?: null,
                    'qualifying_time_ms'  => isset($ev['qualifying_time_ms']) && (int)$ev['qualifying_time_ms'] > 0
                                                ? (int)$ev['qualifying_time_ms'] : null,
                    'qualifying_deadline' => $ev['qualifying_deadline'] ?? null,
                    'meldegeld'           => isset($ev['meldegeld']) && (float)$ev['meldegeld'] > 0
                                                ? (float)$ev['meldegeld'] : null,
                ]);
            }
        });

        $eventCount = count($meet['events']);
        return back()->with('success', "Wettkampffolge importiert: {$eventCount} Wertungen übernommen.");
    }

    // ── Vollständiger Ergebnisimport (alle Clubs → ext_competition_results) ───

    public function fullImport(Request $request, Competition $competition)
    {
        $request->validate([
            'dsv_file' => ['required', 'file', 'max:20480'],
        ]);

        $path     = $request->file('dsv_file')->store('dsv-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $stats = $this->fullImporter->importFile($fullPath, $competition, 'manual');
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['error' => $e->getMessage()], 422);
        }

        Storage::disk('local')->delete($path);

        return response()->json([
            'success'  => true,
            'inserted' => $stats['inserted'],
            'errors'   => $stats['errors'],
        ]);
    }

    public function generateAnalysis(Request $request, Competition $competition)
    {
        $rawResults = $competition->results()->with('user')->where('time_ms', '>', 0)->get();
        $grouped    = CompetitionResultGrouper::forCompetition($rawResults);

        $lines = [];
        foreach ($grouped as $swims) {
            foreach ($swims as $swim) {
                $name = $swim->user?->name ?? 'Unbekannt';
                $placements = collect($swim->placements)
                    ->map(fn($p) => ($p->age_group ? $p->age_group . ': ' : '') . 'Platz ' . $p->placement)
                    ->implode(', ');
                $badges = array_filter([
                    $swim->is_final             ? 'Finale'         : null,
                    $swim->is_personal_best     ? 'PB'             : null,
                    $swim->breaks_vereinsrekord ? 'Vereinsrekord'  : null,
                    $swim->breaks_landesrekord  ? 'Landesrekord'   : null,
                ]);

                $line = "- {$name}: {$swim->distance}m {$swim->discipline_label} in {$swim->formatted_time}";
                if ($placements) $line .= ", {$placements}";
                if ($badges)     $line .= ' [' . implode(', ', $badges) . ']';
                $lines[] = $line;
            }
        }

        if (empty($lines)) {
            return response()->json(['error' => 'Keine gültigen Ergebnisse für die Auswertung vorhanden.'], 422);
        }

        $apiKey = env('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Kein API-Key konfiguriert (ANTHROPIC_API_KEY in .env).'], 500);
        }

        $prompt = "Schreibe eine kurze, motivierende Wettkampfauswertung für den Trainer-Newsletter der SG Wasserratten Norderstedt e.V.\n\n"
            . "Wettkampf: {$competition->name}\nDatum: {$competition->date_range}\nOrt: {$competition->location}\n\n"
            . "Ergebnisse unserer Schwimmer:\n" . implode("\n", $lines) . "\n\n"
            . "Der Text soll 2–3 kurze Absätze umfassen, die besten Leistungen (besonders Podiumsplätze, PBs, Rekorde, Finalläufe) hervorheben, motivierend und positiv formuliert sein und kein HTML oder Markdown enthalten.";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])
              ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 800,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'API-Fehler ' . $response->status()], 500);
            }

            return response()->json(['text' => $response->json('content.0.text', '')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
