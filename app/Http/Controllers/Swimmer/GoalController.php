<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\CompetitionResult;
use App\Models\Season;
use App\Models\SwimmerGoal;
use App\Models\SwimmingTime;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $swimmer = auth()->user();
        $seasons = Season::orderByDesc('start_date')->get();

        $activeSeason = $request->filled('season_id')
            ? $seasons->firstWhere('id', $request->get('season_id'))
            : (Season::current() ?? $seasons->first());

        $goals = SwimmerGoal::where('user_id', $swimmer->id)
            ->where('season_id', $activeSeason?->id)
            ->with('comments.trainer')
            ->orderByRaw("FIELD(type,'time','qualification','free')")
            ->orderByRaw("FIELD(status,'open','achieved','not_achieved','cancelled')")
            ->orderBy('created_at')
            ->get();

        // Check for auto-achieved goals that haven't been notified yet
        $autoAchieved = $goals->where('notified', false)->where('achieved', true);
        if ($autoAchieved->isNotEmpty()) {
            SwimmerGoal::whereIn('id', $autoAchieved->pluck('id'))->update(['notified' => true]);
            session()->flash('auto_achieved', $autoAchieved->pluck('title'));
        }

        // Preload season bests for time goals
        $seasonBests = [];
        if ($activeSeason) {
            $timeGoals = $goals->where('type', 'time')
                ->filter(fn($g) => $g->discipline && $g->distance);

            if ($timeGoals->isNotEmpty()) {
                $start = $activeSeason->start_date;
                $end   = $activeSeason->end_date;

                $trainBests = SwimmingTime::where('user_id', $swimmer->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
                    ->groupBy('discipline', 'distance')
                    ->get()
                    ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

                $compBests = CompetitionResult::where('user_id', $swimmer->id)
                    ->where('time_ms', '>', 0)
                    ->whereHas('competition', fn($q) => $q->whereBetween('date', [$start, $end]))
                    ->selectRaw('discipline, distance, MIN(time_ms) as best_ms')
                    ->groupBy('discipline', 'distance')
                    ->get()
                    ->keyBy(fn($r) => $r->discipline . '_' . $r->distance);

                $trainCounts = SwimmingTime::where('user_id', $swimmer->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('discipline, COUNT(*) as cnt')
                    ->groupBy('discipline')
                    ->get()
                    ->keyBy('discipline');

                foreach ($timeGoals as $goal) {
                    $key  = $goal->discipline . '_' . $goal->distance;
                    $tMs  = $trainBests->get($key)?->best_ms;
                    $cMs  = $compBests->get($key)?->best_ms;
                    $best = ($tMs && $cMs) ? min($tMs, $cMs) : ($tMs ?? $cMs);
                    $seasonBests[$key] = [
                        'best_ms'   => $best,
                        'formatted' => $best ? SwimmingTime::formatMs($best) : null,
                        'count'     => $trainCounts->get($goal->discipline)?->cnt ?? 0,
                    ];
                }
            }
        }

        $quote = $this->quoteOfTheDay();

        return view('swimmer.my-goals', compact('goals', 'seasons', 'activeSeason', 'quote', 'seasonBests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'season_id'  => ['required', 'exists:seasons,id'],
            'type'       => ['required', 'in:time,qualification,free'],
            'title'      => ['required', 'string', 'max:255'],
            'discipline' => ['nullable', 'in:freistil,brust,ruecken,schmetterling,lagen'],
            'distance'   => ['nullable', 'integer', 'min:25'],
            'course'     => ['nullable', 'in:SCM,LCM'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'target_minutes'      => ['nullable', 'integer', 'min:0'],
            'target_seconds'      => ['nullable', 'integer', 'min:0', 'max:59'],
            'target_centiseconds' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $targetMs = null;
        if ($request->filled('target_seconds') || $request->filled('target_minutes')) {
            $targetMs = (((int)$request->input('target_minutes', 0) * 60)
                + (int)$request->input('target_seconds', 0)) * 1000
                + (int)$request->input('target_centiseconds', 0) * 10;
            if ($targetMs <= 0) $targetMs = null;
        }

        SwimmerGoal::create([
            'user_id'       => auth()->id(),
            'season_id'     => $data['season_id'],
            'type'          => $data['type'],
            'title'         => $data['title'],
            'discipline'    => $data['discipline'] ?? null,
            'distance'      => $data['distance'] ?? null,
            'course'        => $data['course'] ?? null,
            'target_time_ms'=> $targetMs,
            'notes'         => $data['notes'] ?? null,
            'status'        => 'open',
            'notified'      => true,
        ]);

        return back()->with('success', 'Ziel gespeichert.');
    }

    public function destroy(SwimmerGoal $goal)
    {
        abort_if($goal->user_id !== auth()->id(), 403);
        $goal->delete();
        return back()->with('success', 'Ziel gelöscht.');
    }

    public function evaluate(Request $request, SwimmerGoal $goal)
    {
        abort_if($goal->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'status'                => ['required', 'in:achieved,not_achieved,cancelled'],
            'achieved_minutes'      => ['nullable', 'integer', 'min:0'],
            'achieved_seconds'      => ['nullable', 'integer', 'min:0', 'max:59'],
            'achieved_centiseconds' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $achievedMs = null;
        if ($data['status'] === 'achieved' && $goal->type === 'time') {
            if ($request->filled('achieved_seconds') || $request->filled('achieved_minutes')) {
                $achievedMs = (((int)($data['achieved_minutes'] ?? 0) * 60)
                    + (int)($data['achieved_seconds'] ?? 0)) * 1000
                    + (int)($data['achieved_centiseconds'] ?? 0) * 10;
                if ($achievedMs <= 0) $achievedMs = null;
            }
        }

        $isAchieved = $data['status'] === 'achieved';

        $goal->update([
            'status'           => $data['status'],
            'achieved'         => $isAchieved,
            'achieved_at'      => $isAchieved ? now()->toDateString() : null,
            'achieved_time_ms' => $achievedMs,
            'progress'         => $isAchieved ? 100 : $goal->progress,
            'notified'         => true,
        ]);

        if ($isAchieved) {
            return redirect()
                ->route('swimmer.goals.index', ['season_id' => $goal->season_id])
                ->with('just_achieved', $goal->title);
        }

        return back()->with('success', 'Ziel bewertet.');
    }

    public function updateProgress(Request $request, SwimmerGoal $goal)
    {
        abort_if($goal->user_id !== auth()->id(), 403);
        $data = $request->validate(['progress' => ['required', 'integer', 'min:0', 'max:100']]);
        $goal->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back();
    }

    private function quoteOfTheDay(): string
    {
        $quotes = [
            'Der einzige schlechte Wettkampf ist der, an dem man nicht teilgenommen hat.',
            'Erfolg ist die Summe kleiner Anstrengungen, die täglich wiederholt werden.',
            'Schwimmen ist nicht nur Sport – es ist ein Gefühl der Freiheit.',
            'Jeder Meter im Wasser bringt dich deinem Ziel näher.',
            'Gib niemals auf. Genau dort, wo es schwer wird, beginnt der Fortschritt.',
            'Champions trainieren, wenn andere schlafen.',
            'Dein größter Konkurrent bist du selbst – und du wirst jeden Tag besser.',
            'Das Wasser kennt keine Ausreden.',
            'Schmerz ist vorübergehend. Stolz ist für immer.',
            'Nicht die Bedingungen machen den Schwimmer, sondern der Schwimmer die Bedingungen.',
            'Kleine Schritte führen zu großen Siegen.',
            'Vertraue dem Prozess. Die Ergebnisse kommen.',
            'Im Wasser zählen keine Ausreden – nur Sekunden.',
            'Sei so gut, dass man dich nicht ignorieren kann.',
            'Heute ist dein bester Tag – bis morgen.',
            'Disziplin ist die Brücke zwischen Zielen und Ergebnissen.',
            'Jede Trainingseinheit ist eine Investition in deine Zukunft.',
            'Wer aufhört, besser zu werden, hat aufgehört, gut zu sein.',
            'Träume groß. Trainiere härter. Erreiche alles.',
            'Das Wasser ist dein Zuhause – zeig, was du kannst.',
            'Mut ist nicht das Fehlen von Angst, sondern das Urteil, dass etwas anderes wichtiger ist.',
            'Großartige Leistungen entstehen nicht durch Zufall – sie werden trainiert.',
        ];
        return $quotes[date('z') % count($quotes)];
    }
}
