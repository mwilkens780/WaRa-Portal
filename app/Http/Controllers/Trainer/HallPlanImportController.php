<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\Import\HallPlanImportService;
use App\Services\Import\HallPlanMatcher;
use App\Services\Import\HallPlanParser;
use App\Services\Import\HallPlanReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Import des Hallenbelegungsplans aus der Excel-Vorlage.
 *
 * Ablauf wie bei den uebrigen Importen: hochladen, in der Vorschau pruefen und
 * ergaenzen, dann speichern. Der eigentliche Schreibvorgang passiert erst im
 * letzten Schritt und nur fuer die dort ausgewaehlten Zeilen.
 */
class HallPlanImportController extends Controller
{
    private const SESSION_KEY = 'hall_plan_import';

    public function __construct(private HallPlanImportService $importer) {}

    public function index()
    {
        return view('trainer.hall.import.upload', [
            'season' => Season::current(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'plan' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [], ['plan' => 'Belegungsplan']);

        if (!class_exists(\ZipArchive::class)) {
            return back()->with('error',
                'Auf dem Server fehlt die PHP-Erweiterung "zip". Ohne sie lassen sich '
                . 'xlsx-Dateien nicht lesen. Bitte beim Hoster aktivieren lassen.');
        }

        $path = $request->file('plan')->store('hallenbelegung-import', 'local');

        try {
            $reader = new HallPlanReader(Storage::disk('local')->path($path));
            $sheets = $reader->sheetNames();
            $data   = $reader->read($request->input('sheet') ?: null);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return back()->with('error', 'Datei konnte nicht gelesen werden: ' . $e->getMessage());
        }

        $parsed  = (new HallPlanParser())->parse($data['cells']);
        $entries = (new HallPlanMatcher($this->groupMap(), $this->trainerMap()))
            ->match($parsed['entries']);
        $entries = $this->importer->analyse($entries);

        session([self::SESSION_KEY => [
            'path'     => $path,
            'sheet'    => $data['sheet'],
            'sheets'   => $sheets,
            'entries'  => $entries,
            'warnings' => $parsed['warnings'],
        ]]);

        return redirect()->route('trainer.hall.import.preview');
    }

    public function preview()
    {
        $state = session(self::SESSION_KEY);
        if (!$state) {
            return redirect()->route('trainer.hall.import.index')
                ->with('error', 'Keine hochgeladene Datei gefunden. Bitte erneut hochladen.');
        }

        // Probelauf: zeigt vor dem Speichern, wie viele Einheiten entstehen.
        $preview = $this->importer->import($state['entries'], auth()->id(), true);

        return view('trainer.hall.import.preview', [
            'entries'  => $state['entries'],
            'warnings' => $state['warnings'],
            'sheet'    => $state['sheet'],
            'groups'   => TrainingGroup::where('active', true)->orderBy('name')->get(),
            'trainers' => User::whereIn('role', ['trainer', 'admin'])->where('active', true)
                              ->orderBy('firstname')->get(),
            'season'   => Season::current(),
            'preview'  => $preview,
        ]);
    }

    public function execute(Request $request)
    {
        $state = session(self::SESSION_KEY);
        if (!$state) {
            return redirect()->route('trainer.hall.import.index')
                ->with('error', 'Keine hochgeladene Datei gefunden. Bitte erneut hochladen.');
        }

        $selected = array_map('intval', $request->input('selected', []));
        $groupOf   = $request->input('group', []);
        $trainerOf = $request->input('trainer', []);

        // Nur ausgewaehlte Zeilen, angereichert um die manuellen Ergaenzungen
        $entries = [];
        foreach ($state['entries'] as $i => $entry) {
            if (!in_array($i, $selected, true)) continue;

            if (!empty($groupOf[$i]))   $entry['group_ids']   = [(int) $groupOf[$i]];
            if (!empty($trainerOf[$i])) $entry['trainer_ids'] = [(int) $trainerOf[$i]];

            $entry['session_ready'] = ($entry['needs_session'] ?? false)
                && $entry['group_ids'] !== [] && $entry['trainer_ids'] !== [];

            $entries[] = $entry;
        }

        if (!$entries) {
            return back()->with('error', 'Es war keine Zeile ausgewählt.');
        }

        $result = $this->importer->import($entries, auth()->id());

        Storage::disk('local')->delete($state['path']);
        session()->forget(self::SESSION_KEY);

        $msg = sprintf('%d Belegungen und %d Trainingseinheiten angelegt.',
            $result['bookings'], $result['sessions']);
        if ($result['skipped'] > 0) {
            $msg .= sprintf(' %d Zeilen übersprungen (bestehende Belegungen bleiben unangetastet).',
                $result['skipped']);
        }

        return redirect()->route('trainer.hall.index')->with('success', $msg);
    }

    /** @return array<int,string> */
    private function groupMap(): array
    {
        return TrainingGroup::where('active', true)->pluck('name', 'id')->all();
    }

    /** @return array<int,array{firstname:string,lastname:string}> */
    private function trainerMap(): array
    {
        return User::whereIn('role', ['trainer', 'admin'])->where('active', true)
            ->get(['id', 'firstname', 'lastname'])
            ->mapWithKeys(fn($u) => [$u->id => [
                'firstname' => $u->firstname,
                'lastname'  => $u->lastname,
            ]])->all();
    }
}
