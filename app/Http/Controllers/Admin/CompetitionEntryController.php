<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionResult;
use App\Services\Competition\DefinitionsdateiGenerator;
use App\Services\Competition\AusschreibungGenerator;
use App\Services\Competition\EntryService;
use App\Services\Competition\EntryValidationService;
use App\Services\Competition\MeldedateiGenerator;
use Illuminate\Http\Request;

class CompetitionEntryController extends Controller
{
    public function __construct(
        private EntryService            $entryService,
        private EntryValidationService  $validator,
        private MeldedateiGenerator     $meldedatei,
        private DefinitionsdateiGenerator $definitionsdatei,
    ) {}

    // ── Entries ──────────────────────────────────────────────────────────────

    /**
     * Return all entries + per-swimmer validation warnings as JSON.
     * Called from the Meldungen tab via AJAX.
     */
    public function index(Competition $competition)
    {
        $entries = CompetitionEntry::with(['user', 'competitionEvent'])
            ->where('competition_id', $competition->id)
            ->where('status', 'entered')
            ->get()
            ->groupBy('user_id');

        $warnings = $this->validator->validateAll($competition->id);

        return response()->json([
            'entries'  => $entries,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Create or update a single entry (AJAX).
     */
    public function store(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'user_id'              => ['required', 'exists:users,id'],
            'discipline'           => ['required', 'in:F,B,R,S,L'],
            'distance'             => ['required', 'integer', 'min:25'],
            'gender'               => ['required', 'in:M,F,X'],
            'age_group'            => ['nullable', 'string', 'max:50'],
            'competition_event_id' => ['nullable', 'exists:competition_events,id'],
            'entry_time_ms'        => ['nullable', 'integer', 'min:1'],
        ]);

        // Auto-fill entry_time_ms from best competition result if not supplied
        if (empty($data['entry_time_ms'])) {
            $signup = $competition->signupRequest;
            $best   = CompetitionResult::where('user_id', $data['user_id'])
                ->where('discipline', $data['discipline'])
                ->where('distance', $data['distance'])
                ->where('time_ms', '>', 0)
                ->when($signup?->qualifying_period_start, fn($q) =>
                    $q->whereHas('competition', fn($cq) => $cq->where('date', '>=', $signup->qualifying_period_start))
                )
                ->when($signup?->qualifying_period_end, fn($q) =>
                    $q->whereHas('competition', fn($cq) => $cq->where('date', '<=', $signup->qualifying_period_end))
                )
                ->orderBy('time_ms')
                ->value('time_ms');

            if (!$best && ($signup?->qualifying_period_start || $signup?->qualifying_period_end)) {
                // Fallback: best time overall if period filter returned nothing
                $best = CompetitionResult::where('user_id', $data['user_id'])
                    ->where('discipline', $data['discipline'])
                    ->where('distance', $data['distance'])
                    ->where('time_ms', '>', 0)
                    ->orderBy('time_ms')
                    ->value('time_ms');
            }

            if ($best) {
                $data['entry_time_ms'] = $best;
            }
        }

        $entry = $this->entryService->setEntry(
            $competition->id,
            $data['user_id'],
            array_merge($data, ['created_by_id' => auth()->id()])
        );

        $warnings = $this->validator->validate($data['user_id'], $competition->id);

        return response()->json([
            'entry'    => $entry,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Delete a single entry (AJAX).
     */
    public function destroy(CompetitionEntry $entry)
    {
        $this->entryService->deleteEntry($entry->id);
        return response()->json(['success' => true]);
    }

    /**
     * Save a relay entry with members (AJAX).
     */
    public function storeRelay(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'discipline'           => ['required', 'in:F,B,R,S,L'],
            'distance'             => ['required', 'integer'],
            'gender'               => ['required', 'in:M,F,mixed'],
            'age_group'            => ['nullable', 'string'],
            'competition_event_id' => ['nullable', 'exists:competition_events,id'],
            'members'              => ['required', 'array', 'min:1', 'max:4'],
            'members.*'            => ['nullable', 'exists:users,id'],
        ]);

        $relay = $this->entryService->setRelayEntry(
            $competition->id,
            array_merge($data, ['created_by_id' => auth()->id()]),
            $data['members']
        );

        return response()->json(['relay' => $relay->load('members.user')]);
    }

    // ── DSV7 Downloads ───────────────────────────────────────────────────────

    /**
     * Download DSV7 *-Vm.DSV7 Vereinsmeldedatei.
     */
    public function downloadMeldedatei(Competition $competition)
    {
        $content  = $this->meldedatei->generate($competition);
        $filename = $competition->date->format('Y-m-d') . '-' . $this->slugify($competition->location) . '-Vm.DSV7';

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download DSV7 *-Wk.DSV7 Wettkampfdefinitionsdatei.
     */
    public function downloadDefinitionsdatei(Competition $competition)
    {
        $content  = $this->definitionsdatei->generate($competition);
        $filename = $competition->date->format('Y-m-d') . '-' . $this->slugify($competition->location) . '-Wk.DSV7';

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download generated Ausschreibungs-PDF.
     */
    public function downloadAusschreibungPdf(Competition $competition)
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'PDF-Generator nicht verfügbar. Bitte composer require barryvdh/laravel-dompdf ausführen.');
        }

        $generator = app(AusschreibungGenerator::class);
        $path      = $generator->generate($competition);
        $filename  = $competition->date->format('Y-m-d') . '-' . $this->slugify($competition->name) . '-Ausschreibung.pdf';

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function slugify(string $str): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', $str) ?: 'wettkaampf';
    }
}
