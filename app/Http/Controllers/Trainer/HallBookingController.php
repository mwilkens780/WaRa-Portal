<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\HallBooking;
use App\Models\HallResource;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HallBookingController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $resources = HallResource::where('active', true)->orderBy('sort_order')->get();

        $bookings = HallBooking::with(['resource', 'trainingGroup', 'trainer', 'trainingSession'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day_of_week for easy Blade access
        $bookingsByDay = $bookings->groupBy('day_of_week');

        // Serialize all bookings for Alpine.js
        $bookingsJson = $bookings->map->toGridArray()->values();

        $groups = TrainingGroup::visibleTo(auth()->user())
            ->with('trainers:id,firstname,lastname')->where('active', true)->orderBy('name')->get();

        $trainers = User::whereIn('role', ['trainer', 'admin'])
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname']);

        return view('trainer.hall.index', compact(
            'resources', 'bookings', 'bookingsByDay', 'bookingsJson', 'groups', 'trainers'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hall_resource_ids'   => ['required', 'array', 'min:1'],
            'hall_resource_ids.*' => ['exists:hall_resources,id'],
            'day_of_week'         => ['required', 'integer', 'min:1', 'max:7'],
            'start_time'          => ['required', 'date_format:H:i'],
            'end_time'            => ['required', 'date_format:H:i', 'after:start_time'],
            'label'               => ['required', 'string', 'max:255'],
            'type'                => ['required', 'in:training,course,school,external,maintenance,other'],
            'training_group_id'   => ['nullable', 'exists:training_groups,id'],
            'trainer_id'          => ['nullable', 'exists:users,id'],
            'training_session_id' => ['nullable', 'exists:training_sessions,id'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            'color'               => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'force'               => ['boolean'],
        ]);

        // Auto-fill trainer from group if not explicitly set
        if (!empty($data['training_group_id']) && empty($data['trainer_id'])) {
            $group = TrainingGroup::with('trainers')->find($data['training_group_id']);
            $data['trainer_id'] = $group?->trainers->first()?->id;
        }

        $conflicts = $this->findConflicts(
            $data['hall_resource_ids'],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time']
        );

        $created = [];
        foreach ($data['hall_resource_ids'] as $resourceId) {
            $booking = HallBooking::create([
                'hall_resource_id'    => $resourceId,
                'day_of_week'         => $data['day_of_week'],
                'start_time'          => $data['start_time'],
                'end_time'            => $data['end_time'],
                'label'               => $data['label'],
                'type'                => $data['type'],
                'training_group_id'   => $data['training_group_id'] ?? null,
                'trainer_id'          => $data['trainer_id'] ?? null,
                'training_session_id' => $data['training_session_id'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'color'               => $data['color'] ?? null,
                'created_by_id'       => auth()->id(),
            ]);
            $booking->load(['resource', 'trainingGroup', 'trainer']);
            $created[] = $booking->toGridArray();
        }

        return response()->json(['success' => true, 'bookings' => $created]);
    }

    public function update(Request $request, HallBooking $booking): JsonResponse
    {
        $data = $request->validate([
            'hall_resource_ids'   => ['nullable', 'array', 'max:1'],
            'hall_resource_ids.*' => ['exists:hall_resources,id'],
            'day_of_week'         => ['required', 'integer', 'min:1', 'max:7'],
            'start_time'          => ['required', 'date_format:H:i'],
            'end_time'            => ['required', 'date_format:H:i', 'after:start_time'],
            'label'               => ['required', 'string', 'max:255'],
            'type'                => ['required', 'in:training,course,school,external,maintenance,other'],
            'training_group_id'   => ['nullable', 'exists:training_groups,id'],
            'trainer_id'          => ['nullable', 'exists:users,id'],
            'training_session_id' => ['nullable', 'exists:training_sessions,id'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            'color'               => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'force'               => ['boolean'],
        ]);

        // Apply resource change if a new one was selected
        if (!empty($data['hall_resource_ids'])) {
            $data['hall_resource_id'] = (int) $data['hall_resource_ids'][0];
        }
        unset($data['hall_resource_ids']);

        $booking->update($data);

        // Sync time to linked training session
        if ($booking->training_session_id) {
            \App\Models\TrainingSession::where('id', $booking->training_session_id)->update([
                'start_time' => $data['start_time'],
                'end_time'   => $data['end_time'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(HallBooking $booking): JsonResponse
    {
        $booking->delete();
        return response()->json(['success' => true]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $request->validate([
            'hall_resource_ids'   => ['required', 'array'],
            'hall_resource_ids.*' => ['integer'],
            'day_of_week'         => ['required', 'integer', 'min:1', 'max:7'],
            'start_time'          => ['required', 'date_format:H:i'],
            'end_time'            => ['required', 'date_format:H:i'],
            'exclude_id'          => ['nullable', 'integer'],
        ]);

        $conflicts = $this->findConflicts(
            $request->hall_resource_ids,
            $request->day_of_week,
            $request->start_time,
            $request->end_time,
            $request->exclude_id
        );

        return response()->json(['conflicts' => $conflicts]);
    }

    /**
     * Search for recurring training sessions matching a day/time window.
     * Used by the hall booking modal to find sessions to link.
     */
    public function searchSessions(Request $request): JsonResponse
    {
        $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i'],
        ]);

        // WEEKDAY() returns 0=Mon … 6=Sun; our day_of_week is 1=Mon … 7=Sun
        $weekday = (int)$request->day_of_week - 1;

        $sessions = TrainingSession::with(['coTrainers:id,firstname,lastname', 'trainingGroups:id,name,color'])
            ->where('date', '>=', now())
            ->whereRaw('WEEKDAY(date) = ?', [$weekday])
            ->where('start_time', '<=', $request->end_time)
            ->where(fn($q) => $q->whereNull('end_time')->orWhere('end_time', '>=', $request->start_time))
            ->when(!auth()->user()->isAdmin(), fn($q) => $q->whereHas('coTrainers', fn($q2) => $q2->where('user_id', auth()->id())))
            ->orderBy('date')
            ->limit(15)
            ->get();

        return response()->json([
            'sessions' => $sessions->map(fn($s) => [
                'id'     => $s->id,
                'title'  => $s->title,
                'time'   => substr($s->start_time, 0, 5) . ($s->end_time ? ' – ' . substr($s->end_time, 0, 5) : ''),
                'date'   => $s->date->format('d.m.Y'),
                'trainer'=> $s->trainer?->name,
                'groups' => $s->trainingGroups->pluck('name')->join(', '),
                'recurring' => (bool)$s->recurrence_group_id,
            ]),
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function findConflicts(
        array $resourceIds, int $day, string $start, string $end, ?int $excludeId = null
    ) {
        return HallBooking::with(['resource', 'trainingGroup'])
            ->whereIn('hall_resource_id', $resourceIds)
            ->where('day_of_week', $day)
            ->where(fn($q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start))
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->map(fn($b) => [
                'id'       => $b->id,
                'resource' => $b->resource->name,
                'label'    => $b->label,
                'time'     => $b->formatted_time,
            ]);
    }
}
