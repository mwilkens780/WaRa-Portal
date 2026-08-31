<?php

namespace App\Http\Controllers\Swimmer;

use App\Http\Controllers\Controller;
use App\Models\GroupMottoWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MottoController extends Controller
{
    public function index()
    {
        $user   = auth()->user();
        $monday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        // Groups where motto_week is enabled and user is a swimmer member
        $swimmerGroupIds = $user->trainingGroups()
            ->where('motto_week_enabled', true)
            ->pluck('training_groups.id');

        // Also check if user is a trainer in any motto-enabled group
        $trainerGroupIds = $user->trainerGroups()
            ->where('motto_week_enabled', true)
            ->pluck('training_groups.id');

        $groupIds = $swimmerGroupIds->merge($trainerGroupIds)->unique()->values();

        // All motto weeks for user's groups (full schedule)
        $weeks = GroupMottoWeek::whereIn('training_group_id', $groupIds)
            ->with(['group:id,name,color', 'user:id,firstname,lastname'])
            ->orderBy('week_start')
            ->get();

        // My assigned weeks (past, current, upcoming)
        $myWeeks = GroupMottoWeek::whereIn('training_group_id', $groupIds)
            ->where('user_id', $user->id)
            ->with('group:id,name,color')
            ->orderBy('week_start')
            ->get();

        // Current week's motto per group
        $currentMottos = GroupMottoWeek::whereIn('training_group_id', $groupIds)
            ->where('week_start', $monday->format('Y-m-d'))
            ->with(['group:id,name,color', 'user:id,firstname,lastname'])
            ->get();

        return view('swimmer.motto.index', compact(
            'weeks', 'myWeeks', 'currentMottos', 'monday', 'groupIds'
        ));
    }

    public function save(Request $request, GroupMottoWeek $week)
    {
        $user = auth()->user();

        // Only the responsible person or an admin may save
        abort_if($week->user_id !== $user->id && !$user->isAdmin(), 403);

        $data = $request->validate([
            'motto' => ['required', 'string', 'max:500'],
        ]);

        $week->update(['motto' => $data['motto']]);

        return back()->with('success', 'Motto gespeichert.');
    }

    public function generateAi(GroupMottoWeek $week)
    {
        $user = auth()->user();

        // Responsible person, trainer of the group, or admin
        $isGroupTrainer = $user->trainerGroups()
            ->where('training_groups.id', $week->training_group_id)
            ->exists();

        abort_if(
            $week->user_id !== $user->id && !$user->isAdmin() && !$isGroupTrainer,
            403
        );

        // Collect already-used mottos to avoid repeats
        $usedMottos = GroupMottoWeek::where('training_group_id', $week->training_group_id)
            ->whereNotNull('motto')
            ->pluck('motto')
            ->toArray();

        $usedList = empty($usedMottos)
            ? ''
            : "\n\nBereits verwendete Mottos (diese NICHT wiederverwenden):\n- " . implode("\n- ", $usedMottos);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 150,
                'system'     => 'Du generierst kurze, motivierende Mottos der Woche für eine Schwimmsportgruppe. Das Motto soll inspirierend, positiv und direkt mit dem Schwimmsport verbunden sein. Maximal 2 Sätze, gerne mit einem Schwimmbezug (Wasser, Bewegung, Ausdauer, Teamgeist). Antworte NUR mit dem Motto-Text, keine Erklärung, keine Anführungszeichen.',
                'messages'   => [[
                    'role'    => 'user',
                    'content' => "Generiere ein neues, motivierendes Motto der Woche für unsere Schwimmgruppe.{$usedList}",
                ]],
            ]);

            if ($response->successful()) {
                $text = trim($response->json('content.0.text', ''));
                if ($text !== '') {
                    $week->update(['generated_motto' => $text]);
                    return response()->json(['motto' => $text]);
                }
            }
        } catch (\Exception $e) {
            // fall through to error response
        }

        return response()->json(['error' => 'Generierung fehlgeschlagen. Bitte erneut versuchen.'], 500);
    }
}
