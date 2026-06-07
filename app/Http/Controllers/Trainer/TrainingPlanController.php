<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingBlockTime;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanBlock;
use App\Models\TrainingSession;
use Illuminate\Http\Request;

class TrainingPlanController extends Controller
{
    public function edit(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $plan = $session->trainingPlan()->with('blocks')->first();

        // Pre-compute minute/second splits so Alpine.js can initialise inputs directly
        $initialBlocks = $plan
            ? $plan->blocks->map(fn($b) => [
                'id'                   => $b->id,
                'label'                => $b->label ?? '',
                // Use nested structure if available, otherwise wrap int into single-element array
                'repetition_levels'    => !empty($b->repetitions_nested)
                    ? array_map('strval', $b->repetitions_nested)
                    : ($b->repetitions ? [strval($b->repetitions)] : ['']),
                'distance'             => $b->distance ?? '',
                'disciplines'          => $b->disciplines ?? [],
                'additions'            => $b->additions ?? [],
                'materials'            => $b->materials ?? [],
                'comment'              => $b->comment ?? '',
                'start_interval_min'   => $b->start_interval_seconds ? intdiv($b->start_interval_seconds, 60) : 0,
                'start_interval_sec'   => $b->start_interval_seconds ? $b->start_interval_seconds % 60 : 0,
                'recovery_min'         => $b->recovery_seconds ? intdiv($b->recovery_seconds, 60) : 0,
                'recovery_sec'         => $b->recovery_seconds ? $b->recovery_seconds % 60 : 0,
            ])->values()->all()
            : [];

        // Target duration in seconds from session times
        $targetSeconds = 0;
        if ($session->start_time && $session->end_time) {
            $start = strtotime($session->start_time);
            $end   = strtotime($session->end_time);
            if ($end > $start) $targetSeconds = $end - $start;
        }

        return view('trainer.sessions.plan', compact('session', 'plan', 'initialBlocks', 'targetSeconds'));
    }

    public function save(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'],
            'blocks_json' => ['nullable', 'string'],
            'attachment'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $plan = TrainingPlan::firstOrNew(['training_session_id' => $session->id]);
        $plan->description   = $data['description'] ?? null;
        $plan->created_by_id = $plan->created_by_id ?? auth()->id();

        if ($request->hasFile('attachment')) {
            if ($plan->attachment_path) {
                \Storage::disk('local')->delete($plan->attachment_path);
            }
            $plan->attachment_path = $request->file('attachment')->store('training-plans', 'local');
        }

        $plan->save();

        // Rebuild blocks
        $plan->blocks()->delete();

        $blocks = json_decode($data['blocks_json'] ?? '[]', true) ?? [];
        foreach ($blocks as $i => $b) {
            $startSec   = ((int)($b['start_interval_min'] ?? 0)) * 60 + (int)($b['start_interval_sec'] ?? 0);
            $recoverySec = ((int)($b['recovery_min'] ?? 0)) * 60 + (int)($b['recovery_sec'] ?? 0);

            // Parse multi-level repetitions: [4, 6, 2] → nested=[4,6,2], product=48
            $levels  = $this->parseRepetitionLevels($b['repetitions'] ?? null);
            $product = !empty($levels) ? array_reduce($levels, fn($c, $r) => $c * $r, 1) : null;

            TrainingPlanBlock::create([
                'training_plan_id'       => $plan->id,
                'sort_order'             => $i,
                'label'                  => ($b['label'] ?? '') ?: null,
                'repetitions'            => $product,
                'repetitions_nested'     => $levels ?: null,
                'distance'               => ($b['distance'] !== '' && $b['distance'] !== null) ? (int)$b['distance'] : null,
                'disciplines'            => $b['disciplines'] ?? [],
                'additions'              => $b['additions'] ?? [],
                'materials'              => $b['materials'] ?? [],
                'comment'                => ($b['comment'] ?? '') ?: null,
                'start_interval_seconds' => $startSec > 0 ? $startSec : null,
                'recovery_seconds'       => $recoverySec > 0 ? $recoverySec : null,
            ]);
        }

        return back()->with('success', 'Trainingsplan gespeichert.');
    }

    public function deleteAttachment(TrainingSession $session)
    {
        $this->authorizeSession($session);
        $plan = $session->trainingPlan;
        if ($plan?->attachment_path) {
            \Storage::disk('local')->delete($plan->attachment_path);
            $plan->update(['attachment_path' => null]);
        }
        return back()->with('success', 'Anhang gelöscht.');
    }

    public function saveBlockTime(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'block_id'   => ['required', 'integer'],
            'user_id'    => ['required', 'integer'],
            'repetition' => ['required', 'integer', 'min:1', 'max:255'],
            'time_cs'    => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ]);

        $block = TrainingPlanBlock::where('id', $data['block_id'])
            ->whereHas('plan', fn($q) => $q->where('training_session_id', $session->id))
            ->firstOrFail();

        $blockTime = TrainingBlockTime::updateOrCreate(
            [
                'training_plan_block_id' => $block->id,
                'user_id'                => $data['user_id'],
                'repetition'             => $data['repetition'],
            ],
            ['time_cs' => $data['time_cs']]
        );

        return response()->json([
            'ok'        => true,
            'formatted' => TrainingBlockTime::format($blockTime->time_cs),
        ]);
    }

    public function downloadAttachment(TrainingSession $session)
    {
        $plan = $session->trainingPlan;
        if (!$plan?->attachment_path) abort(404);

        if (auth()->user()->role === 'schwimmer' && $session->date->gt(today())) {
            abort(403);
        }

        return \Storage::disk('local')->download($plan->attachment_path);
    }

    // Parse incoming repetitions (int or array) into a clean int[] with no zero/empty values
    private function parseRepetitionLevels(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) return [];
        $arr = is_array($value) ? $value : [$value];
        return array_values(array_filter(array_map('intval', $arr), fn($v) => $v > 0));
    }

    private function authorizeSession(TrainingSession $session): void
    {
        if (!auth()->user()->isAdmin() && !$session->coTrainers()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }
    }
}
