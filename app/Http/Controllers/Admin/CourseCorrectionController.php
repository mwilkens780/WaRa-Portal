<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\RecordCheckService;
use Illuminate\Http\Request;

/**
 * Sammelkorrektur: Wettkaempfe ohne Bahnlaenge.
 *
 * Ohne Bahnlaenge zaehlen Ergebnisse weder fuer Rekorde noch fuer
 * Bestenlisten. WebClub liefert die Angabe oft nicht (verBAHN = "0"), deshalb
 * lassen sich hier viele Wettkaempfe in einem Rutsch nachtragen.
 *
 * Wirkung wie bei der Einzelaenderung im Wettkampfformular: Bahn speichern,
 * danach Rekorde und Bestenlisten neu berechnen - hier einmal fuer alle
 * Aenderungen zusammen statt einmal pro Wettkampf.
 */
class CourseCorrectionController extends Controller
{
    public function __construct(private RecordCheckService $records) {}

    public function index()
    {
        $competitions = Competition::whereNull('course')
            ->withCount('results')
            ->orderByDesc('date')
            ->get();

        $suggestions = $this->suggestions($competitions);

        return view('admin.corrections.course', compact('competitions', 'suggestions'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'course'   => ['array'],
            'course.*' => ['nullable', 'in:Kurzbahn,Langbahn'],
        ]);

        // Nur Wettkaempfe, die noch keine Bahn haben - schuetzt vor einem
        // veralteten Formular, das eine inzwischen gesetzte Bahn ueberschreibt
        $chosen = collect($data['course'] ?? [])->filter();
        $targets = Competition::whereNull('course')
            ->whereIn('id', $chosen->keys())
            ->get();

        if ($targets->isEmpty()) {
            return back()->with('error', 'Es war keine Bahnlänge ausgewählt.');
        }

        $withResults = 0;
        foreach ($targets as $competition) {
            // Ueber das Modell, damit das Aenderungsprotokoll greift
            $competition->update(['course' => $chosen[$competition->id]]);
            if ($competition->results()->exists()) $withResults++;
        }

        $msg = sprintf('%d Wettkämpfe aktualisiert.', $targets->count());
        if ($withResults > 0) {
            $this->records->recheckAll();
            $msg .= " Rekorde und Bestenlisten wurden neu berechnet ({$withResults} Wettkämpfe mit Ergebnissen).";
        }

        return redirect()->route('admin.corrections.course.index')->with('success', $msg);
    }

    /**
     * Vorschlaege nur, wo sie eindeutig sind:
     *  - Name enthaelt "Kurzbahn" bzw. "Langbahn"
     *  - Ergebnisse ueber 100 m Lagen -> Kurzbahn (100 L wird nur auf der
     *    Kurzbahn geschwommen)
     * Alles andere bleibt leer und wird von Hand entschieden.
     *
     * @return array<int, array{course: string, reason: string}>
     */
    private function suggestions($competitions): array
    {
        $with100L = \App\Models\CompetitionResult::whereIn('competition_id', $competitions->pluck('id'))
            ->where('discipline', 'L')->where('distance', 100)
            ->distinct()->pluck('competition_id')->flip();

        $out = [];
        foreach ($competitions as $c) {
            $name = mb_strtolower($c->name);
            if (str_contains($name, 'kurzbahn')) {
                $out[$c->id] = ['course' => 'Kurzbahn', 'reason' => 'Name enthält „Kurzbahn"'];
            } elseif (str_contains($name, 'langbahn')) {
                $out[$c->id] = ['course' => 'Langbahn', 'reason' => 'Name enthält „Langbahn"'];
            } elseif ($with100L->has($c->id)) {
                $out[$c->id] = ['course' => 'Kurzbahn', 'reason' => '100 m Lagen geschwommen'];
            }
        }

        return $out;
    }
}
