<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\RelayResult;
use App\Models\User;
use App\Services\DsvImportService;
use App\Services\RecordCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompetitionResultImportController extends Controller
{
    // Keywords used to pre-select our own club in the preview
    private const CLUB_KEYWORDS = ['wasserratten', 'norderstedt', 'sgwn', 'sgw'];

    public function __construct(
        private DsvImportService $service,
        private RecordCheckService $recordCheck,
    ) {}

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

        // Auto-match athletes; keep ALL clubs (user selects in preview)
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->get();

        foreach ($parsed['meets'] as &$meet) {
            foreach ($meet['clubs'] as &$club) {
                foreach ($club['athletes'] as &$athlete) {
                    $athlete['matched_user_id'] = $this->matchAthlete($athlete, $swimmers);
                }
            }
        }
        unset($meet, $club, $athlete);

        // Determine which club indices look like our club (for pre-selection in preview)
        $suggestedClubs = [];
        foreach ($parsed['meets'][0]['clubs'] as $ci => $club) {
            if ($this->looksLikeOurClub($club['name'])) {
                $suggestedClubs[] = $ci;
            }
        }

        // If none matched, suggest all clubs (so the page is not empty)
        if (empty($suggestedClubs)) {
            $suggestedClubs = array_keys($parsed['meets'][0]['clubs']);
        }

        // Check if file date matches competition date (warn if more than 14 days apart)
        $fileMeet    = $parsed['meets'][0];
        $fileDateStr = $fileMeet['startdate'] ?? null;
        $mismatch    = null;

        if ($fileDateStr) {
            $fileDate  = \Carbon\Carbon::parse($fileDateStr);
            $compDate  = $competition->date;
            $diffDays  = abs($fileDate->diffInDays($compDate));

            if ($diffDays > 14) {
                $mismatch = sprintf(
                    'Das Datum in der Datei (%s) weicht um %d Tage vom Wettkampf-Datum (%s) ab. Wurde die richtige Datei hochgeladen?',
                    $fileDate->format('d.m.Y'),
                    $diffDays,
                    $compDate->format('d.m.Y')
                );
            }
        }

        session([
            'comp_result_import_parsed'    => $parsed,
            'comp_result_import_comp'      => $competition->id,
            'comp_result_import_suggested' => $suggestedClubs,
            'comp_result_import_mismatch'  => $mismatch,
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

        $parsed         = session('comp_result_import_parsed');
        $suggestedClubs = session('comp_result_import_suggested', []);
        $mismatch       = session('comp_result_import_mismatch');
        $swimmers       = User::where('role', 'schwimmer')->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        return view('admin.competitions.result-import-preview',
            compact('competition', 'parsed', 'swimmers', 'suggestedClubs', 'mismatch'));
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

        $imported      = 0;
        $skipped       = 0;
        $relayImported = 0;

        foreach ($meet['clubs'] as $ci => $club) {
            foreach ($club['athletes'] as $ai => $athlete) {
                if ($athlete['is_relay'] ?? false) {
                    foreach ($athlete['results'] as $result) {
                        $this->importRelayResult($competition->id, $club['name'], $athlete, $result);
                        $relayImported++;
                    }
                    continue;
                }

                $userId = (int)($data['mappings'][$ci][$ai] ?? 0);

                if (!$userId) {
                    $skipped++;
                    continue;
                }

                foreach ($athlete['results'] as $result) {
                    $saved = $this->importResult($competition->id, $userId, $result, $athlete['gender'] ?? 'X');
                    if ($saved) {
                        $this->recordCheck->checkResult($saved->load(['user', 'competition']));
                    }
                    $imported++;
                }
            }
        }

        session()->forget([
            'comp_result_import_parsed',
            'comp_result_import_comp',
            'comp_result_import_suggested',
            'comp_result_import_mismatch',
        ]);

        $msg = "{$imported} Ergebnis" . ($imported !== 1 ? 'se' : '') . " importiert";
        if ($relayImported > 0) {
            $msg .= ", {$relayImported} Staffelergebnis" . ($relayImported !== 1 ? 'se' : '') . " importiert";
        }
        if ($skipped > 0) {
            $msg .= ", {$skipped} Athlet" . ($skipped !== 1 ? 'en' : '') . " übersprungen";
        }

        return redirect()->route('admin.competitions.show', $competition)
            ->with('success', $msg . '.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function looksLikeOurClub(string $clubName): bool
    {
        $lower = mb_strtolower($clubName);
        foreach (self::CLUB_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    private function matchAthlete(array $athlete, \Illuminate\Support\Collection $swimmers): ?int
    {
        $aFirst = mb_strtolower(trim($athlete['firstname'] ?? ''));
        $aLast  = mb_strtolower(trim($athlete['lastname']  ?? ''));

        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->firstname)) === $aFirst
                && mb_strtolower(trim($swimmer->lastname)) === $aLast) {
                return $swimmer->id;
            }
        }

        // Fallback: combined name
        $combined = mb_strtolower(trim($athlete['name'] ?? ''));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->name)) === $combined) {
                return $swimmer->id;
            }
        }

        return null;
    }

    private function importRelayResult(int $competitionId, string $clubName, array $athlete, array $result): void
    {
        $exists = RelayResult::where([
            'competition_id' => $competitionId,
            'discipline'     => $result['discipline'],
            'distance'       => $result['distance'],
            'club_name'      => $clubName,
            'time_ms'        => $result['time_ms'],
        ])->exists();

        if ($exists) return;

        RelayResult::create([
            'competition_id' => $competitionId,
            'discipline'     => $result['discipline'],
            'distance'       => $result['distance'],
            'club_name'      => $clubName,
            'time_ms'        => $result['time_ms'],
            'placement'      => $result['place'] ?? null,
            'age_group'      => $result['age_group'] ?? null,
            'gender'         => $athlete['gender'] ?? null,
            'status'         => 'OK',
        ]);
    }

    private function importResult(int $competitionId, int $userId, array $result, string $gender = 'X'): ?CompetitionResult
    {
        $ageGroup  = $result['age_group'] ?? null;
        $wertungen = !empty($result['wertungen']) ? $result['wertungen'] : ($ageGroup ? [$ageGroup] : null);
        $isDns     = !empty($result['status']);
        $resGender = $result['gender'] ?? $gender;
        if ($resGender === 'X') $resGender = $gender;
        $isFinal   = in_array($result['round_type'] ?? '', ['F', 'E']);

        // Dedup by physical swim: competition + user + discipline + distance + round_type + time
        $exists = CompetitionResult::where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->where('is_final', $isFinal)
            ->where('time_ms', $isDns ? 0 : $result['time_ms'])
            ->exists();

        if ($exists) return null;

        // DNS / AB / DNF / DQ — store with time_ms=0, no PB
        if ($isDns) {
            return CompetitionResult::create([
                'competition_id'   => $competitionId,
                'user_id'          => $userId,
                'discipline'       => $result['discipline'],
                'distance'         => $result['distance'],
                'time_ms'          => 0,
                'placement'        => 0,
                'is_personal_best' => false,
                'age_group'        => $ageGroup,
                'wertungen'        => $wertungen,
                'gender'           => $resGender !== 'X' ? $resGender : null,
                'notes'            => $result['status'],
                'is_final'         => $isFinal,
            ]);
        }

        $existingBest = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $isPb = !$existingBest || $result['time_ms'] < $existingBest;

        if ($isPb && $existingBest) {
            CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->where('is_personal_best', true)
                ->update(['is_personal_best' => false]);
        }

        return CompetitionResult::create([
            'competition_id'   => $competitionId,
            'user_id'          => $userId,
            'discipline'       => $result['discipline'],
            'distance'         => $result['distance'],
            'time_ms'          => $result['time_ms'],
            'placement'        => $result['place'] ?? null,
            'is_personal_best' => $isPb,
            'age_group'        => $ageGroup,
            'wertungen'        => $wertungen,
            'gender'           => $resGender !== 'X' ? $resGender : null,
            'is_final'         => $isFinal,
        ]);
    }
}
