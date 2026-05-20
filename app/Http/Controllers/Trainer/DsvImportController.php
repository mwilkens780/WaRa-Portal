<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
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

        return view('trainer.dsv-import.preview', compact('parsed', 'swimmers'));
    }

    // ── Schritt 4: Import durchführen ───────────────────────────────────────

    public function execute(Request $request)
    {
        $data = $request->validate([
            'meet_index'    => ['required', 'integer', 'min:0'],
            'comp_name'     => ['required', 'string', 'max:255'],
            'comp_location' => ['required', 'string', 'max:255'],
            'comp_date'     => ['required', 'date'],
            'comp_date_end' => ['nullable', 'date', 'after_or_equal:comp_date'],
            'comp_type'     => ['required', 'in:vereinsintern,regional,national,international,meisterschaften,einladung'],
            'comp_course'   => ['required', 'in:LCM,SCM'],
            'mappings'      => ['present', 'array'],
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

        $competition = Competition::create([
            'name'      => $data['comp_name'],
            'location'  => $data['comp_location'],
            'date'      => $data['comp_date'],
            'date_end'  => $data['comp_date_end'] ?: null,
            'type'      => $data['comp_type'],
            'course'    => $data['comp_course'],
        ]);

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

        // Cleanup session + temp file
        session()->forget(['dsv_import_parsed', 'dsv_import_file']);
        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()->route('trainer.dsv-import.index')
            ->with('import_success', [
                'competition' => $competition->name,
                'date'        => $competition->date->format('d.m.Y'),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'comp_id'     => $competition->id,
            ]);
    }

    // ── Helper ──────────────────────────────────────────────────────────────

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

        // Fallback: match combined name (handles legacy "Vorname Nachname" entries)
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
        // Check if already imported (same competition + user + discipline + distance)
        $exists = CompetitionResult::where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->exists();

        if ($exists) return;

        // Determine personal best status
        $existingBest = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $result['discipline'])
            ->where('distance', $result['distance'])
            ->min('time_ms');

        $isPb = !$existingBest || $result['time_ms'] < $existingBest;

        if ($isPb && $existingBest) {
            // Revoke previous PB flag
            CompetitionResult::where('user_id', $userId)
                ->where('discipline', $result['discipline'])
                ->where('distance', $result['distance'])
                ->where('is_personal_best', true)
                ->update(['is_personal_best' => false]);
        }

        CompetitionResult::create([
            'competition_id'  => $competitionId,
            'user_id'         => $userId,
            'discipline'      => $result['discipline'],
            'distance'        => $result['distance'],
            'time_ms'         => $result['time_ms'],
            'placement'       => $result['place'],
            'is_personal_best'=> $isPb,
        ]);
    }
}
