<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\GroupMottoWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MottoController extends Controller
{
    public function index()
    {
        $trainer  = auth()->user();
        $monday   = now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        // Gruppen mit Motto-Zyklus: eigene und solche, die die eigene Gruppe
        // als Partnergruppe fuehren (gemeinsames Training)
        $groupIds = $trainer->isAdmin()
            ? \App\Models\TrainingGroup::where('motto_week_enabled', true)->pluck('id')
            : app(\App\Services\MottoWeekService::class)->cycleGroupIdsFor($trainer);

        $currentWeeks = GroupMottoWeek::whereIn('training_group_id', $groupIds)
            ->where('week_start', $monday->format('Y-m-d'))
            ->with(['group:id,name,color', 'user:id,firstname,lastname'])
            ->get();

        $upcomingWeeks = GroupMottoWeek::whereIn('training_group_id', $groupIds)
            ->where('week_start', '>', $monday->format('Y-m-d'))
            ->where('week_start', '<=', $monday->copy()->addWeeks(4)->format('Y-m-d'))
            ->with(['group:id,name,color', 'user:id,firstname,lastname'])
            ->orderBy('week_start')
            ->get();

        return view('trainer.motto.index', compact('currentWeeks', 'upcomingWeeks', 'monday'));
    }

    public function saveMotto(\Illuminate\Http\Request $request, GroupMottoWeek $week)
    {
        abort_unless($this->editableGroupIds()->contains($week->training_group_id), 403);

        $data = $request->validate(['motto' => ['required', 'string', 'max:500']]);
        $week->update(['motto' => $data['motto']]);

        return back()->with('success', 'Motto aktualisiert.');
    }

    public function activateGenerated(GroupMottoWeek $week)
    {
        abort_unless($this->editableGroupIds()->contains($week->training_group_id), 403);

        if (!$week->generated_motto) {
            // Generate one on the spot
            $usedMottos = GroupMottoWeek::where('training_group_id', $week->training_group_id)
                ->whereNotNull('motto')
                ->pluck('motto')
                ->toArray();

            $usedList = empty($usedMottos)
                ? ''
                : "\n\nBereits verwendete Mottos:\n- " . implode("\n- ", $usedMottos);

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => config('services.anthropic.api_key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 150,
                    'system'     => 'Du generierst kurze, motivierende Mottos der Woche für eine Schwimmsportgruppe. Das Motto soll inspirierend, positiv und direkt mit dem Schwimmsport verbunden sein. Maximal 2 Sätze. Antworte NUR mit dem Motto-Text.',
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => "Generiere ein motivierendes Motto der Woche für unsere Schwimmgruppe.{$usedList}",
                    ]],
                ]);

                if ($response->successful()) {
                    $text = trim($response->json('content.0.text', ''));
                    if ($text !== '') {
                        $week->update(['generated_motto' => $text]);
                    }
                }
            } catch (\Exception $e) {
                // fall through
            }
        }

        if ($week->generated_motto) {
            $week->update(['motto' => $week->generated_motto]);
            return back()->with('success', 'Generiertes Motto aktiviert.');
        }

        return back()->with('error', 'Generierung fehlgeschlagen. Bitte erneut versuchen.');
    }

    /**
     * Zyklen, in die dieser Trainer eingreifen darf: seine eigenen Gruppen und
     * die, die eine seiner Gruppen als Partnergruppe fuehren. Trainiert man
     * gemeinsam, gehoert der gemeinsame Zyklus beiden Trainerteams.
     */
    private function editableGroupIds(): \Illuminate\Support\Collection
    {
        $trainer = auth()->user();

        if ($trainer->isAdmin()) {
            return \App\Models\TrainingGroup::pluck('id');
        }

        $own = $trainer->trainerGroups()->pluck('training_groups.id');

        return \App\Models\TrainingGroup::where(function ($q) use ($own) {
            $q->whereIn('id', $own)->orWhereIn('motto_partner_group_id', $own);
        })->pluck('id');
    }
}
