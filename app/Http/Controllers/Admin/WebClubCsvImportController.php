<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\RelayResult;
use App\Models\User;
use App\Services\Import\WebClubResultsCsvParser;
use App\Services\RecordCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebClubCsvImportController extends Controller
{
    private const CLUB_KEYWORDS = ['wasserratten', 'norderstedt', 'sgwn'];

    public function __construct(
        private WebClubResultsCsvParser $parser,
        private RecordCheckService $recordCheck,
    ) {}

    // ── Schritt 1: CSV hochladen + parsen ────────────────────────────────────

    public function upload(Request $request, Competition $competition)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('csv_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt'])) {
            return back()->withErrors(['csv_file' => 'Nur .csv oder .txt Dateien sind erlaubt.']);
        }

        $path     = $file->store('webclub-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->parser->parse($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['csv_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed['meets'])) {
            return back()->withErrors(['csv_file' => 'Keine gültigen Wettkampfdaten gefunden.']);
        }

        // Auto-match individual athletes against registered swimmers
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->get();

        foreach ($parsed['meets'] as &$meet) {
            foreach ($meet['clubs'] as &$club) {
                foreach ($club['athletes'] as &$athlete) {
                    if ($athlete['is_relay'] ?? false) continue;
                    $athlete['matched_user_id'] = $this->matchAthlete($athlete, $swimmers);
                }
            }
        }
        unset($meet, $club, $athlete);

        $meet = $parsed['meets'][0];
        $mismatch = null;

        if ($meet['startdate']) {
            $fileDateStr = $meet['startdate'];
            $fileDate    = \Carbon\Carbon::parse($fileDateStr);
            $diffDays    = abs($fileDate->diffInDays($competition->date));
            if ($diffDays > 14) {
                $mismatch = sprintf(
                    'Das Datum in der Datei (%s) weicht um %d Tage vom Wettkampf-Datum (%s) ab.',
                    $fileDate->format('d.m.Y'),
                    $diffDays,
                    $competition->date->format('d.m.Y')
                );
            }
        }

        session([
            'wc_import_parsed'   => $parsed,
            'wc_import_comp'     => $competition->id,
            'wc_import_mismatch' => $mismatch,
        ]);

        return redirect()->route('admin.competitions.wc-import.preview', $competition);
    }

    // ── Schritt 2: Vorschau ──────────────────────────────────────────────────

    public function preview(Competition $competition)
    {
        if (!session()->has('wc_import_parsed')
            || session('wc_import_comp') !== $competition->id) {
            return redirect()->route('admin.competitions.show', $competition)
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $parsed   = session('wc_import_parsed');
        $mismatch = session('wc_import_mismatch');
        $swimmers = User::where('role', 'schwimmer')->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')->get();

        return view('admin.competitions.wc-import-preview',
            compact('competition', 'parsed', 'swimmers', 'mismatch'));
    }

    // ── Schritt 3: Import ausführen ──────────────────────────────────────────

    public function execute(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'meet_index' => ['required', 'integer', 'min:0'],
            'mappings'   => ['present', 'array'],
        ]);

        $parsed = session('wc_import_parsed');

        if (!$parsed || session('wc_import_comp') !== $competition->id) {
            return redirect()->route('admin.competitions.show', $competition)
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $meet = $parsed['meets'][(int)$data['meet_index']] ?? null;
        if (!$meet) {
            return back()->withErrors(['meet_index' => 'Ungültiger Index.']);
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

        session()->forget(['wc_import_parsed', 'wc_import_comp', 'wc_import_mismatch']);

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

    // ── Helpers ──────────────────────────────────────────────────────────────

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

        $combined = mb_strtolower(trim($athlete['name'] ?? ''));
        foreach ($swimmers as $swimmer) {
            if (mb_strtolower(trim($swimmer->name)) === $combined) {
                return $swimmer->id;
            }
        }

        return null;
    }

    private function importResult(int $competitionId, int $userId, array $result, string $gender = 'X'): ?CompetitionResult
    {
        $ageGroup  = $result['age_group'] ?? null;
        $resGender = $result['gender'] ?? $gender;
        if ($resGender === 'X') $resGender = $gender;

        // Allow multiple results per competition/swimmer/discipline/distance (e.g. Vorlauf + Finale)
        $exists = CompetitionResult::where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->where('time_ms', $result['time_ms'])
            ->exists();

        if ($exists) return null;

        $isFinal   = in_array($result['round_type'] ?? '', ['F', 'E']);
        $isPbz     = $result['is_personal_best'] ?? false;
        $isSbz     = $result['is_season_best'] ?? false;
        $isVr      = $result['is_vereinsrekord'] ?? false;
        $isLr      = $result['is_landesrekord'] ?? false;

        // Compute actual is_personal_best: trust the file if it says PBZ,
        // but also check if this is actually the best time in the system.
        $existingBest = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $computedPb = !$existingBest || $result['time_ms'] < $existingBest;
        $isPb = $isPbz || $computedPb;

        if ($isPb && $existingBest) {
            CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->where('is_personal_best', true)
                ->update(['is_personal_best' => false]);
        }

        return CompetitionResult::create([
            'competition_id'       => $competitionId,
            'user_id'              => $userId,
            'discipline'           => $result['discipline'],
            'distance'             => $result['distance'],
            'time_ms'              => $result['time_ms'],
            'placement'            => $result['place'] ?? null,
            'is_personal_best'     => $isPb,
            'is_season_best'       => $isSbz,
            'age_group'            => $ageGroup,
            'gender'               => $resGender !== 'X' ? $resGender : null,
            'is_final'             => $isFinal,
            'breaks_vereinsrekord' => $isVr,
            'breaks_landesrekord'  => $isLr,
            'notes'                => $result['rek'] !== '' ? $result['rek'] : null,
        ]);
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
}
