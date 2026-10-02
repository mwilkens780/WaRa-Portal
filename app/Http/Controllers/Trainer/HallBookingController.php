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
    // Lesbare Namen fuer Validierungsmeldungen im Buchungsdialog
    private const FELDNAMEN = [
        'hall_resource_ids' => 'Bahn/Ressource',
        'day_of_week'       => 'Wochentag',
        'start_time'        => 'Von',
        'end_time'          => 'Bis',
        'label'             => 'Bezeichnung',
        'type'              => 'Typ',
        'notes'             => 'Notizen',
        'color'             => 'Farbe',
    ];

    public function index(): \Illuminate\View\View
    {
        $resources = HallResource::where('active', true)->orderBy('sort_order')->get();

        $bookings = HallBooking::with(['resource', 'trainingGroup', 'trainer', 'trainingSession.coTrainers:id,firstname,lastname'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day_of_week for easy Blade access
        $bookingsByDay = $bookings->groupBy('day_of_week');

        // Compute expired series IDs (series whose all sessions are in the past)
        $sessionIds = $bookings->pluck('training_session_id')->filter()->unique();
        $expiredGroupIds = collect();
        if ($sessionIds->isNotEmpty()) {
            $groupIds = TrainingSession::whereIn('id', $sessionIds)
                ->whereNotNull('recurrence_group_id')
                ->pluck('recurrence_group_id')
                ->unique();
            if ($groupIds->isNotEmpty()) {
                $activeGroupIds = TrainingSession::whereIn('recurrence_group_id', $groupIds)
                    ->where('date', '>=', now()->toDateString())
                    ->pluck('recurrence_group_id')
                    ->unique();
                $expiredGroupIds = $groupIds->diff($activeGroupIds)->values();
            }
        }

        // Serialize all bookings for Alpine.js
        $bookingsJson = $bookings->map->toGridArray()->values();

        $groups = TrainingGroup::visibleTo(auth()->user())
            ->with('trainers:id,firstname,lastname')->where('active', true)->orderBy('name')->get();

        $trainers = User::whereIn('role', ['trainer', 'admin'])
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname']);

        // Legende: nur zeigen, was im Plan auch vorkommt - eine Liste aller
        // denkbaren Farben hilft niemandem beim Lesen des Plans.
        $belegteGruppenIds = $bookings->pluck('training_group_id')->filter()->unique();

        $legendGroups = TrainingGroup::whereIn('id', $belegteGruppenIds)
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn($g) => [
                'name'  => $g->name,
                'hex'   => TrainingGroup::colorHex($g->color),
                'text'  => HallBooking::readableTextColor(TrainingGroup::colorHex($g->color)),
                'count' => $bookings->where('training_group_id', $g->id)->count(),
            ])->values();

        // Belegungsarten ohne Gruppe: Kurse, Schule, externe Vereine, Wartung
        $legendTypes = collect(HallBooking::TYPE_LABELS)
            ->map(fn($label, $key) => [
                'key'   => $key,
                'name'  => $label,
                'hex'   => HallBooking::TYPE_COLORS[$key] ?? HallBooking::TYPE_COLORS['other'],
                'count' => $bookings->where('type', $key)->whereNull('training_group_id')->count(),
            ])
            ->filter(fn($t) => $t['count'] > 0)
            ->values();

        return view('trainer.hall.index', compact(
            'resources', 'bookings', 'bookingsByDay', 'bookingsJson', 'groups', 'trainers', 'expiredGroupIds',
            'legendGroups', 'legendTypes'
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
        ], [], self::FELDNAMEN);

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

        // Ueberschneidung nur speichern, wenn ausdruecklich bestaetigt
        if ($conflicts->isNotEmpty() && !$request->boolean('force')) {
            return $this->conflictResponse($conflicts);
        }

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
            $created[] = $booking;
        }

        // Mit einer Serie verknuepft: an die Serie haengen, Doppelte zusammenlegen
        if (!empty($data['training_session_id'])
            && ($group = \App\Models\TrainingSession::whereKey($data['training_session_id'])->value('recurrence_group_id'))) {
            $this->attachToSeries($group, $data['hall_resource_ids']);
            $created = HallBooking::where('training_series_id', $group)->whereIn('hall_resource_id', $data['hall_resource_ids'])->get()->all();
        }

        $created = array_map(fn($b) => $b->load(['resource', 'trainingGroup', 'trainer', 'trainingSession.coTrainers:id,firstname,lastname'])->toGridArray(), $created);

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
        ], [], self::FELDNAMEN);

        // Apply resource change if a new one was selected
        if (!empty($data['hall_resource_ids'])) {
            $data['hall_resource_id'] = (int) $data['hall_resource_ids'][0];
        }
        unset($data['hall_resource_ids']);

        $conflicts = $this->findConflicts(
            [$data['hall_resource_id'] ?? $booking->hall_resource_id],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time'],
            $booking->id
        );
        if ($conflicts->isNotEmpty() && !$request->boolean('force')) {
            return $this->conflictResponse($conflicts);
        }
        unset($data['force']);

        // Wochentag einer Serienbelegung: verschiebt Termine - das gehoert in die Serie
        if ($booking->trainingSession?->recurrence_group_id && (int) $data['day_of_week'] !== (int) $booking->day_of_week) {
            return response()->json([
                'message' => 'Diese Belegung gehört zu einer Trainingsserie. Den Wochentag bitte in der Serie ändern (Trainingseinheiten → Serie öffnen).',
            ], 422);
        }

        $vorher = [substr($booking->start_time, 0, 5), substr($booking->end_time, 0, 5), $booking->training_session_id];
        $booking->update($data);
        $booking->refresh();
        $geaendert = $vorher !== [$data['start_time'], $data['end_time'], $booking->training_session_id];

        if (($group = $booking->trainingSession?->recurrence_group_id) && $geaendert) {
            // Serienbelegung: Zeit gilt fuer die Serie ab heute (Termine mit eigener
            // Zeit behalten sie), alle Bahnen der Serie ziehen mit, Doppelte fallen weg
            $this->applySeriesTime($group, $data['start_time'], $data['end_time']);
            $this->attachToSeries($group, [$booking->hall_resource_id]);
        } elseif (!$group && ($linked = $booking->trainingSession)) {
            $linked->update(['start_time' => $data['start_time'], 'end_time' => $data['end_time']]);
        }

        // Aktueller Stand fuer den Plan - er wird ohne Neuladen aktualisiert.
        // Beim Zusammenlegen kann die bearbeitete Belegung selbst entfallen sein.
        $booking = HallBooking::find($booking->id)
            ?? HallBooking::where('training_series_id', $group ?? '')->where('hall_resource_id', $data['hall_resource_id'] ?? $booking->hall_resource_id)->first()
            ?? $booking;
        $booking->load(['resource', 'trainingGroup', 'trainer', 'trainingSession.coTrainers:id,firstname,lastname']);

        return response()->json(['success' => true, 'booking' => $booking->toGridArray()]);
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
            ->manageableBy(auth()->user())
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

    /**
     * Belegung(en) mit einer Serie verbunden: an die Serie haengen, je Bahn eine.
     * Vorher entstand beim Verknuepfen eine zweite, unsichtbare Belegung.
     */
    private function attachToSeries(string $group, array $resourceIds): void
    {
        app(\App\Services\TrainingSeriesBackfill::class)->ensure($group);
        $lanes = app(\App\Services\SeriesHallBookings::class);
        $all = $lanes->bookingsOf($group)->pluck('hall_resource_id')->merge($resourceIds)->map(fn($id) => (int) $id)->unique()->values()->all();
        $lanes->sync($group, $all, auth()->id());
    }

    /** Neue Zeit fuer Serie und kommende Termine ohne eigene Zeit */
    private function applySeriesTime(string $group, string $start, string $end): void
    {
        \App\Models\TrainingSeries::whereKey($group)->update(['start_time' => $start, 'end_time' => $end]);
        \App\Models\TrainingSession::where('recurrence_group_id', $group)->where('date', '>=', today())->get()
            ->reject(fn($s) => array_intersect(['start_time', 'end_time'], $s->overridden_fields ?? []))
            ->each(fn($s) => $s->update(['start_time' => $start, 'end_time' => $end]));
    }

    private function conflictResponse($conflicts): JsonResponse
    {
        return response()->json([
            'message'   => 'Die Belegung überschneidet sich mit bestehenden Belegungen.',
            'conflicts' => $conflicts,
        ], 409);
    }

    private function findConflicts(
        array $resourceIds, int $day, string $start, string $end, ?int $excludeId = null
    ) {
        return HallBooking::with(['resource', 'trainingGroup', 'trainingSession:id,recurrence_group_id', 'series:id,title'])
            ->whereIn('hall_resource_id', $resourceIds)
            ->where('day_of_week', $day)
            ->where(fn($q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start))
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->map(function ($b) {
                // Belegung einer Serie: aufloesen auf Serienebene (oeffnen / ab Datum beenden)
                $seriesId = $b->training_series_id ?? $b->trainingSession?->recurrence_group_id;
                return [
                    'id'       => $b->id,
                    'resource' => $b->resource->name,
                    'label'    => $b->label,
                    'time'     => $b->formatted_time,
                    'series'   => $seriesId ? [
                        'title' => $b->series?->title ?? $b->label,
                        'open'  => route('trainer.sessions.series.show', $seriesId),
                        'end'   => route('trainer.sessions.series.delete', $seriesId),
                    ] : null,
                ];
            });
    }
}
