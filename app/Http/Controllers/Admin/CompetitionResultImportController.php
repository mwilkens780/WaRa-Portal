<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\User;
use App\Services\DsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionResultImportController extends Controller
{
    // Club name keywords used to filter relevant athletes from the DSV file
    private const CLUB_KEYWORDS = ['wasserratten', 'norderstedt'];

    public function __construct(private DsvImportService $service) {}

    // ── Schritt 1: Datei hochladen + parsen ─────────────────────────────────

    public function upload(Request $request, Competition $competition)
    {
        $request->validate([
            'dsv_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('dsv_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xml', 'lef', 'txt', 'dsv7'])) {
            return back()->withErrors(['dsv_file' => 'Nur .xml, .lef, .dsv7 oder .txt Dateien sind erlaubt.']);
        }

        $path     = $file->store('dsv-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->service->parse($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['dsv_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed['meets'])) {
            return back()->withErrors(['dsv_file' => 'Keine gültigen Wettkampfdaten gefunden.']);
        }

        // Try to filter to SG Wasserratten Norderstedt; fall back to all clubs if no match
        $clubWarning = null;
        foreach ($parsed['meets'] as &$meet) {
            $filtered = array_values(array_filter(
                $meet['clubs'],
                fn($club) => $this->isOurClub($club['name'])
            ));
            if (!empty($filtered)) {
                $meet['clubs'] = $filtered;
            } else {
                $clubWarning = 'Kein Verein mit "Wasserratten" oder "Norderstedt" gefunden – alle Vereine werden angezeigt.';
            }
        }
        unset($meet);

        // Auto-match athletes by firstname + lastname
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->get();

        foreach ($parsed['meets'] as &$meet) {
            foreach ($meet['clubs'] as &$club) {
                foreach ($club['athletes'] as &$athlete) {
                    $athlete['matched_user_id'] = $this->matchAthlete($athlete, $swimmers);
                }
            }
        }
        unset($meet, $club, $athlete);

        session([
            'comp_result_import_parsed'   => $parsed,
            'comp_result_import_comp'     => $competition->id,
            'comp_result_import_warning'  => $clubWarning,
        ]);

        return redirect()->route('admin.competitions.results-import.preview', $competition);
    }

    // ── Schritt 2: Vorschau ─────────────────────────────────────────────────

    public function preview(Competition $competition)
    {
        if (!session()->has('comp_result_import_parsed')
            || session('comp_result_import_comp') !== $competition->id) {
            return redirect()->route('admin.competitions.show', $competition)
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $parsed      = session('comp_result_import_parsed');
        $clubWarning = session('comp_result_import_warning');
        $swimmers    = User::where('role', 'schwimmer')->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        return view('admin.competitions.result-import-preview',
            compact('competition', 'parsed', 'swimmers', 'clubWarning'));
    }

    // ── Schritt 3: Import ausführen ─────────────────────────────────────────

    public function execute(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'meet_index' => ['required', 'integer', 'min:0'],
            'mappings'   => ['present', 'array'],
        ]);

        $parsed = session('comp_result_import_parsed');

        if (!$parsed || session('comp_result_import_comp') !== $competition->id) {
            return redirect()->route('admin.competitions.show', $competition)
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $meet = $parsed['meets'][(int)$data['meet_index']] ?? null;
        if (!$meet) {
            return back()->withErrors(['meet_index' => 'Ungültiger Wettkampf-Index.']);
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($meet['clubs'] as $ci => $club) {
            foreach ($club['athletes'] as $ai => $athlete) {
                $userId = (int)($data['mappings'][$ci][$ai] ?? 0);

                if (!$userId) {
                    $skipped++;
                    continue;
                }

                foreach ($athlete['results'] as $result) {
                    $this->importResult($competition->id, $userId, $result);
                    $imported++;
                }
            }
        }

        session()->forget(['comp_result_import_parsed', 'comp_result_import_comp', 'comp_result_import_warning']);

        return redirect()->route('admin.competitions.show', $competition)
            ->with('success', "{$imported} Ergebnis" . ($imported !== 1 ? 'se' : '') . " importiert" .
                ($skipped ? ", {$skipped} Athlet" . ($skipped !== 1 ? 'en' : '') . " übersprungen" : '') . '.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function isOurClub(string $clubName): bool
    {
        $lower = mb_strtolower($clubName);
        foreach (self::CLUB_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function matchAthlete(array $athlete, \Illuminate\Support\Collection $swimmers): ?int
    {
        $aFirst = mb_strtolower(trim($athlete['firstname'] ?? ''));
        $aLast  = mb_strtolower(trim($athlete['lastname']  ?? ''));

        foreach ($swimmers as $swimmer) {
            $sFirst = mb_strtolower(trim($swimmer->firstname));
            $sLast  = mb_strtolower(trim($swimmer->lastname));

            if ($sFirst === $aFirst && $sLast === $aLast) {
                return $swimmer->id;
            }
        }

        // Fallback: combined name match
        $combined = mb_strtolower(trim($athlete['name'] ?? ''));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->name)) === $combined) {
                return $swimmer->id;
            }
        }

        return null;
    }

    private function importResult(int $competitionId, int $userId, array $result): void
    {
        $exists = CompetitionResult::where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->exists();

        if ($exists) return;

        $existingBest = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->min('time_ms');

        $isPb = !$existingBest || $result['time_ms'] < $existingBest;

        if ($isPb && $existingBest) {
            CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->where('is_personal_best', true)
                ->update(['is_personal_best' => false]);
        }

        CompetitionResult::create([
            'competition_id'   => $competitionId,
            'user_id'          => $userId,
            'discipline'       => $result['discipline'],
            'distance'         => $result['distance'],
            'time_ms'          => $result['time_ms'],
            'placement'        => $result['place'] ?? null,
            'is_personal_best' => $isPb,
        ]);
    }
}
