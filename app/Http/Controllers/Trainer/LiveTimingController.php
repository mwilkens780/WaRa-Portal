<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingBlockTime;
use App\Models\TrainingPlanBlock;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveTimingController extends Controller
{
    /** Mobile-first stopwatch view used at the poolside during training. */
    public function index(TrainingSession $session)
    {
        $this->authorizeSession($session);

        $session->load('trainingPlan.blocks', 'trainingGroups.swimmers', 'attendances');

        $blocks = $session->trainingPlan
            ? $session->trainingPlan->blocks->filter(fn($b) => $b->tracksTime())->values()
            : collect();

        $swimmers = $this->sessionSwimmers($session);

        // Who is actually standing at the pool: attendance if taken, otherwise
        // everyone who did not cancel beforehand.
        $attendedIds  = $session->attendances->where('attended', true)->pluck('user_id')->all();
        $preAbsentIds = $session->attendances->where('pre_absent', true)->pluck('user_id')->all();

        $present = !empty($attendedIds)
            ? $swimmers->whereIn('id', $attendedIds)->values()
            : $swimmers->reject(fn($s) => in_array($s->id, $preAbsentIds))->values();

        $timesMap = [];
        if ($blocks->isNotEmpty()) {
            TrainingBlockTime::whereIn('training_plan_block_id', $blocks->pluck('id'))
                ->get()
                ->each(function ($t) use (&$timesMap) {
                    $timesMap[$t->training_plan_block_id][$t->user_id][$t->repetition] = $t->time_cs;
                });
        }

        // Payload for Alpine: blocks, athletes and any times already recorded
        $liveBlocks = $blocks->map(fn($b) => [
            'id'           => $b->id,
            'label'        => $b->label ?: null,
            'reps'         => $b->total_repetitions,
            'display'      => $b->repetitions_display,
            'distance'     => $b->distance,
            'comment'      => $b->comment,
            'lanesPerWave' => $b->lanes_per_wave,
            'waveGapCs'    => $b->wave_gap_cs,
            'athleteOrder' => $b->athlete_order ?? [],
        ])->all();

        // Everyone is sent to the client, flagged by presence, so a swimmer who
        // turns up late can be switched on without reloading at the poolside.
        $presentIds = $present->pluck('id')->all();

        $liveAthletes = $swimmers->map(fn($u) => [
            'id'      => $u->id,
            'name'    => trim($u->firstname . ' ' . $u->lastname),
            'first'   => $u->firstname ?? '',
            'last'    => $u->lastname ?? '',
            'short'   => trim(($u->firstname ?? '') . ' ' . mb_substr($u->lastname ?? '', 0, 1) . '.'),
            'present' => in_array($u->id, $presentIds, true),
        ])->values()->all();

        return view('trainer.sessions.live', [
            'session'      => $session,
            'blocks'       => $blocks,
            'present'      => $present,
            'allSwimmers'  => $swimmers,
            'liveBlocks'   => $liveBlocks,
            'liveAthletes' => $liveAthletes,
            'timesMap'     => $timesMap,
            'attendanceTaken' => !empty($attendedIds),
        ]);
    }

    /** Save wave configuration (lanes, gap, athlete order) for one block. */
    public function saveWave(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'block_id'        => ['required', 'integer'],
            'lanes_per_wave'  => ['nullable', 'integer', 'min:1', 'max:4'],
            'wave_gap_cs'     => ['nullable', 'integer', 'in:0,300,500'],
            'athlete_order'   => ['nullable', 'array'],
            'athlete_order.*' => ['integer'],
        ]);

        $block = TrainingPlanBlock::whereHas(
            'plan', fn($q) => $q->where('training_session_id', $session->id)
        )->findOrFail((int) $data['block_id']);

        $block->update([
            'lanes_per_wave' => $data['lanes_per_wave'] ?? null,
            'wave_gap_cs'    => $data['wave_gap_cs'] ?? null,
            'athlete_order'  => $data['athlete_order'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Persist several cells at once — a whole row, or a burst of times
     * captured by voice while the trainer keeps both hands busy.
     */
    public function saveBulk(Request $request, TrainingSession $session)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'entries'              => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.block_id'   => ['required', 'integer'],
            'entries.*.user_id'    => ['required', 'integer'],
            'entries.*.repetition' => ['required', 'integer', 'min:1', 'max:255'],
            'entries.*.time_cs'    => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ]);

        // Only blocks belonging to this session may be written to
        $allowedBlocks = TrainingPlanBlock::whereHas(
            'plan', fn($q) => $q->where('training_session_id', $session->id)
        )->pluck('id')->all();

        // Only swimmers who actually belong to this session
        $allowedUsers = $this->sessionSwimmers($session)->pluck('id')->all();

        $saved = [];
        DB::transaction(function () use ($data, $allowedBlocks, $allowedUsers, &$saved) {
            foreach ($data['entries'] as $e) {
                if (!in_array((int)$e['block_id'], $allowedBlocks, true)) continue;
                if (!in_array((int)$e['user_id'], $allowedUsers, true))   continue;

                $key = [
                    'training_plan_block_id' => (int)$e['block_id'],
                    'user_id'                => (int)$e['user_id'],
                    'repetition'             => (int)$e['repetition'],
                ];

                // An empty value clears the cell instead of storing a zero
                if ($e['time_cs'] === null) {
                    TrainingBlockTime::where($key)->delete();
                } else {
                    TrainingBlockTime::updateOrCreate($key, ['time_cs' => (int)$e['time_cs']]);
                }

                $saved[] = [
                    'block_id'   => $key['training_plan_block_id'],
                    'user_id'    => $key['user_id'],
                    'repetition' => $key['repetition'],
                    'time_cs'    => $e['time_cs'] === null ? null : (int)$e['time_cs'],
                    'formatted'  => $e['time_cs'] === null ? '' : TrainingBlockTime::format((int)$e['time_cs']),
                ];
            }
        });

        return response()->json(['ok' => true, 'saved' => $saved, 'count' => count($saved)]);
    }

    /** Swimmers of the session's groups, or all active swimmers when no group is assigned. */
    private function sessionSwimmers(TrainingSession $session)
    {
        $query = User::where('role', 'schwimmer')->where('active', true);

        if ($session->trainingGroups->isNotEmpty()) {
            $ids = $session->trainingGroups
                ->flatMap(fn($g) => $g->swimmers->where('active', true)->pluck('id'))
                ->unique();
            $query->whereIn('id', $ids);
        }

        return $query->orderBy('lastname')->orderBy('firstname')->get();
    }

    private function authorizeSession(TrainingSession $session): void
    {
        if (!auth()->user()->isAdmin() && !$session->coTrainers()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }
    }
}
