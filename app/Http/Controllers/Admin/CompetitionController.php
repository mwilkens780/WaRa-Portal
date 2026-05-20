<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEvent;
use App\Models\CompetitionResult;
use App\Models\User;
use App\Services\DsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionController extends Controller
{
    public function __construct(private DsvImportService $service) {}

    public function index()
    {
        $competitions = Competition::withCount('results')
            ->orderByDesc('date')
            ->paginate(15);

        return view('admin.competitions.index', compact('competitions'));
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
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'date_end'    => ['nullable', 'date', 'gte:date'],
            'type'        => ['required', 'in:vereinsintern,regional,national,international,meisterschaften,einladung'],
            'organizer'   => ['nullable', 'string', 'max:255'],
            'course'      => ['nullable', 'in:LCM,SCM'],
            'description' => ['nullable', 'string'],
            'events_json' => ['nullable', 'json'],
        ]);

        $competition = Competition::create([
            'name'        => $data['name'],
            'location'    => $data['location'],
            'date'        => $data['date'],
            'date_end'    => $data['date_end'] ?? null,
            'type'        => $data['type'],
            'organizer'   => $data['organizer'] ?? null,
            'course'      => $data['course'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        // Save events if provided (from Lenex import)
        if (!empty($data['events_json'])) {
            $events = json_decode($data['events_json'], true);
            foreach ($events as $ev) {
                CompetitionEvent::create([
                    'competition_id' => $competition->id,
                    'event_number'   => (int)($ev['event_number'] ?? 0),
                    'session_number' => (int)($ev['session_number'] ?? 1),
                    'session_date'   => $ev['session_date'] ?: null,
                    'session_name'   => $ev['session_name'] ?: null,
                    'discipline'     => $ev['discipline'],
                    'distance'       => (int)$ev['distance'],
                    'gender'         => $ev['gender'] ?? 'X',
                    'age_min'        => ($ev['age_min'] ?? null) ?: null,
                    'age_max'        => ($ev['age_max'] ?? null) ?: null,
                    'age_group'      => $ev['age_group'] ?: null,
                ]);
            }
        }

        session()->forget('lenex_competition_data');

        $eventCount = $competition->events()->count();
        $msg = 'Wettkampf wurde angelegt.';
        if ($eventCount > 0) {
            $msg .= " {$eventCount} Startdisziplinen aus Lenex-Datei übernommen.";
        }

        return redirect()->route('admin.competitions.show', $competition)
            ->with('success', $msg);
    }

    public function show(Competition $competition)
    {
        $competition->load('events');

        $results = $competition->results()
            ->with('user')
            ->orderBy('discipline')
            ->orderBy('distance')
            ->orderBy('time_ms')
            ->get()
            ->groupBy(fn($r) => $r->discipline . '_' . $r->distance);

        $swimmers = User::where('role', 'schwimmer')->where('active', true)->orderBy('name')->get();

        return view('admin.competitions.show', compact('competition', 'results', 'swimmers'));
    }

    public function edit(Competition $competition)
    {
        return view('admin.competitions.edit', compact('competition'));
    }

    public function update(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'date_end'    => ['nullable', 'date', 'gte:date'],
            'type'        => ['required', 'in:vereinsintern,regional,national,international,meisterschaften,einladung'],
            'organizer'   => ['nullable', 'string', 'max:255'],
            'course'      => ['nullable', 'in:LCM,SCM'],
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
            'discipline'        => ['required', 'in:freistil,brust,ruecken,schmetterling,lagen'],
            'distance'          => ['required', 'integer', 'min:25'],
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
            ->min('time_ms');

        $isPb = !$existingBest || $timeMs < $existingBest;

        CompetitionResult::create([
            'competition_id'  => $competition->id,
            'user_id'         => $data['user_id'],
            'discipline'      => $data['discipline'],
            'distance'        => $data['distance'],
            'time_ms'         => $timeMs,
            'placement'       => $data['placement'] ?? null,
            'is_personal_best'=> $isPb,
            'age_group'       => $data['age_group'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Ergebnis wurde eingetragen.');
    }

    public function destroyResult(CompetitionResult $result)
    {
        $result->delete();
        return back()->with('success', 'Ergebnis wurde gelöscht.');
    }
}
