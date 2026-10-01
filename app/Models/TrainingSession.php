<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingSession extends Model
{
    use HasFactory, Auditable;

    protected static function booted(): void
    {
        // Letzte Einheit einer Serie weg: ständige Absagen darauf verweisen sonst ins Leere
        static::deleted(function (TrainingSession $session) {
            $group = $session->recurrence_group_id;
            if ($group && !static::where('recurrence_group_id', $group)->exists()) {
                SwimmerSeriesExclusion::where('recurrence_group_id', $group)->delete();
            }
        });
    }

    public function getAuditLabel(): string
    {
        return ($this->title ?? '–') . ' (' . ($this->date?->format('d.m.Y') ?? '') . ')';
    }

    protected $fillable = [
        'title', 'date', 'start_time', 'end_time', 'location', 'type', 'notes',
        'recurrence_type', 'recurrence_until', 'recurrence_group_id',
        'team_plan_path', 'individual_plan_path',
        'max_participants', 'registration_open', 'guest_group_id',
        'status', 'cancel_reason', 'cancelled_at', 'overridden_fields',
    ];

    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'recurrence_until'  => 'date',
            'registration_open' => 'boolean',
            'cancelled_at'      => 'datetime',
            'overridden_fields' => 'array',
        ];
    }

    /**
     * Uhrzeiten ohne Sekunden ("06:00", nicht "06:00:00") - ueberall, wo sie
     * angezeigt werden. Gespeichert wird weiter als TIME.
     */
    protected function startTime(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(fn($v) => $v === null ? null : substr($v, 0, 5));
    }

    protected function endTime(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(fn($v) => $v === null ? null : substr($v, 0, 5));
    }

    public function trainingGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'training_session_group');
    }

    public function attendances()
    {
        return $this->hasMany(TrainingAttendance::class);
    }

    public function presentSwimmers()
    {
        return $this->belongsToMany(User::class, 'training_attendances')
            ->wherePivot('attended', true);
    }

    public function swimmingTimes()
    {
        return $this->hasMany(SwimmingTime::class);
    }

    public function diaries()
    {
        return $this->hasMany(TrainingDiary::class);
    }

    public function trainingPlan()
    {
        return $this->hasOne(TrainingPlan::class);
    }

    public function diaryFor(int $userId): ?TrainingDiary
    {
        return $this->diaries()->where('user_id', $userId)->first();
    }

    /**
     * Ende der Einheit als Zeitpunkt.
     *
     * Ohne hinterlegte Endzeit laeuft sie bis Tagesende - ohne Angabe laesst
     * sich nicht behaupten, sie sei schon vorbei.
     */
    public function endsAt(): \Illuminate\Support\Carbon
    {
        $tag = \Illuminate\Support\Carbon::parse($this->date)->startOfDay();

        return $this->end_time
            ? $tag->setTimeFromTimeString($this->end_time)
            : $tag->endOfDay();
    }

    /** Ist die Einheit vorbei? Erst dann ist sie eine vergangene. */
    public function isOver(): bool
    {
        return $this->endsAt()->isPast();
    }

    /**
     * Beendete Einheiten. Der Tag allein reicht nicht: Das Training von heute
     * Abend ist am Vormittag noch keine vergangene Einheit - bis zum Ende der
     * Trainingszeit sind Zu- und Absagen, Anwesenheit, Plan und Zeiten dran.
     *
     * Absichtlich ohne Datenbankfunktionen fuer Zeitrechnung: Die Vergleiche
     * laufen gegen Zeichenketten und damit auf MySQL wie auf SQLite gleich.
     */
    public function scopeFinished(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        $heute = today()->toDateString();
        $jetzt = now()->format('H:i:s');

        return $q->where(fn($w) => $w
            ->whereDate('date', '<', $heute)
            ->orWhere(fn($t) => $t
                ->whereDate('date', '=', $heute)
                ->whereNotNull('end_time')
                ->where('end_time', '<=', $jetzt)));
    }

    /**
     * Findet statt (nicht "faellt aus"). Fuer alles, was zaehlt oder erinnert:
     * Beteiligung, Quoten, offene Tagebuecher. Anzeigen fuer Schwimmer und Eltern
     * zeigen ausgefallene Termine dagegen weiter an - mit Kennzeichnung.
     */
    public function scopeTakingPlace(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('status', '!=', 'cancelled');
    }

    /** Das Gegenstueck: alles, was noch aussteht oder gerade laeuft. */
    public function scopeUpcomingOrRunning(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        $heute = today()->toDateString();
        $jetzt = now()->format('H:i:s');

        return $q->where(fn($w) => $w
            ->whereDate('date', '>', $heute)
            ->orWhere(fn($t) => $t
                ->whereDate('date', '=', $heute)
                ->where(fn($z) => $z->whereNull('end_time')->orWhere('end_time', '>', $jetzt))));
    }

    public function siblings()
    {
        if (!$this->recurrence_group_id) return collect();
        return self::where('recurrence_group_id', $this->recurrence_group_id)
            ->where('id', '!=', $this->id)
            ->orderBy('date')
            ->get();
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'technik'        => 'Technik',
            'wettkampf'      => 'Wettkampfvorbereitung',
            'ausdauer'       => 'Ausdauer',
            'krafttraining'  => 'Krafttraining',
            'physio'         => 'Physiotherapie',
            'mentaltraining' => 'Mentaltraining',
            'sonstiges'      => 'Sonstiges',
            // Ohne Art bleibt das Feld leer, statt mit einem Typfehler die
            // ganze Seite mitzureissen.
            default          => (string) ($this->type ?? ''),
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'kondition'      => 'bg-orange-100 text-orange-700',
            'technik'        => 'bg-blue-100 text-blue-700',
            'wettkampf'      => 'bg-red-100 text-red-700',
            'ausdauer'       => 'bg-green-100 text-green-700',
            'krafttraining'  => 'bg-purple-100 text-purple-700',
            'physio'         => 'bg-pink-100 text-pink-700',
            'mentaltraining' => 'bg-teal-100 text-teal-700',
            default          => 'bg-gray-100 text-gray-600',
        };
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->end_time) return null;
        $diff = (strtotime($this->end_time) - strtotime($this->start_time)) / 60;
        return $diff . ' Min.';
    }

    public function getIsRecurringAttribute(): bool
    {
        return $this->recurrence_type !== 'none' && $this->recurrence_type !== null;
    }

    public function coTrainers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_session_trainers');
    }

    /**
     * Einheiten, die ein Benutzer als Trainer sehen und bearbeiten darf:
     * Admins alle; Trainer die, bei denen sie als Co-Trainer eingetragen sind
     * ODER die zu einer Trainingsgruppe gehoeren, die sie betreuen.
     *
     * Bisher zaehlte nur der Co-Trainer-Eintrag - ein Gruppentrainer kam an
     * Einheiten seiner eigenen Gruppe nicht heran, sobald dort ein anderer
     * Trainer eingetragen war. Einzige Quelle fuer diese Regel; alle
     * Zugriffspruefungen auf Einheiten gehen hierueber.
     */
    public function scopeManageableBy(\Illuminate\Database\Eloquent\Builder $q, User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->isAdmin()) {
            return $q;
        }

        return $q->where(fn($w) => $w
            ->whereHas('coTrainers', fn($c) => $c->where('users.id', $user->id))
            ->orWhereHas('trainingGroups.trainers', fn($t) => $t->where('users.id', $user->id)));
    }

    /**
     * Einheiten, die dieser Sportler sehen darf.
     *
     * Eine Regel fuer alle Stellen - Dashboard, Kalender und Detailseite
     * hatten bisher jede ihre eigene. Der Kalender zeigte deshalb Einheiten
     * an, deren Detailseite den Klick dann mit 403 abwies.
     *
     * Sichtbar ist eine Einheit, wenn der Sportler in einer zugeordneten
     * Gruppe ist, einzeln oder ueber eine Serie zugewiesen wurde, als
     * anwesend eingetragen ist - oder wenn die Einheit gar keiner Gruppe
     * zugeordnet ist und damit allen offensteht.
     */
    public function scopeVisibleToSwimmer(\Illuminate\Database\Eloquent\Builder $q, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $gruppenIds = $user->trainingGroups()->pluck('training_groups.id');
        $einzelIds  = \App\Models\TrainingSessionSwimmer::where('user_id', $user->id)
            ->whereNotNull('training_session_id')->pluck('training_session_id');
        $serienIds  = \App\Models\TrainingSessionSwimmer::where('user_id', $user->id)
            ->whereNotNull('recurrence_group_id')->pluck('recurrence_group_id');

        return $q->where(function ($w) use ($user, $gruppenIds, $einzelIds, $serienIds) {
            $w->whereDoesntHave('trainingGroups');
            if ($gruppenIds->isNotEmpty()) {
                $w->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $gruppenIds));
            }
            if ($einzelIds->isNotEmpty()) $w->orWhereIn('id', $einzelIds);
            if ($serienIds->isNotEmpty()) $w->orWhereIn('recurrence_group_id', $serienIds);
            $w->orWhereHas('attendances', fn($a) => $a->where('user_id', $user->id)->where('attended', true));
        });
    }

    /** Dieselbe Regel fuer eine einzelne Einheit. */
    public function isVisibleToSwimmer(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleToSwimmer($user)->exists();
    }

    public function isManageableBy(User $user): bool
    {
        return $user->isAdmin()
            || static::whereKey($this->getKey())->manageableBy($user)->exists();
    }

    // Backward-compat accessor: returns first assigned trainer (or null)
    public function getTrainerAttribute(): ?\App\Models\User
    {
        if ($this->relationLoaded('coTrainers')) {
            return $this->coTrainers->first();
        }
        return $this->coTrainers()->first();
    }

    public function individualSwimmers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_session_swimmers', 'training_session_id', 'user_id')
            ->whereNotNull('training_session_swimmers.training_session_id');
    }

    public function registrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainingSessionRegistration::class, 'training_session_id');
    }

    public function registrationCount(): int
    {
        return $this->registrations()->count();
    }

    public function remainingSpots(): ?int
    {
        if ($this->max_participants === null) return null;
        return max(0, $this->max_participants - $this->registrationCount());
    }

    public function hallBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HallBooking::class, 'training_session_id');
    }

    public function series(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TrainingSeries::class, 'recurrence_group_id');
    }

    /** Ausnahme-Bahnen nur fuer diesen Termin (nicht im Hallenplan) */
    public function exceptionLanes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(HallResource::class, 'training_session_lanes')->withTimestamps();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Wer zu diesem Termin gehoert: Gruppen, Einzel- und Gastzuweisungen, ohne staendige Absagen */
    public function participants(): \Illuminate\Support\Collection
    {
        $this->loadMissing('trainingGroups');
        $ids = $this->trainingGroups->flatMap(fn($g) => $g->swimmers()->where('users.active', true)->pluck('users.id'))
            ->merge(TrainingSessionSwimmer::where('training_session_id', $this->id)->pluck('user_id'));
        if ($this->recurrence_group_id) {
            $ids = $ids->merge(TrainingSessionSwimmer::where('recurrence_group_id', $this->recurrence_group_id)->whereNull('training_session_id')->pluck('user_id'))
                ->diff(SwimmerSeriesExclusion::where('recurrence_group_id', $this->recurrence_group_id)->pluck('user_id'));
        }

        return User::whereIn('id', $ids->unique())->where('active', true)->get();
    }

    public function guestGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'guest_group_id');
    }

    public function guestBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\TrainingSessionSwimmer::class, 'training_session_id')
            ->where('is_guest', true);
    }

    /**
     * Count of expected participants: group members + individually assigned swimmers.
     * Pass precomputed maps from the controller to avoid N+1 in list views.
     */
    public function expectedParticipantCount(
        array $groupSwimmerCounts = [],
        array $sessionIndividualCounts = [],
        array $seriesIndividualCounts = []
    ): int {
        if (!empty($groupSwimmerCounts) || !empty($sessionIndividualCounts) || !empty($seriesIndividualCounts)) {
            $count = 0;
            foreach ($this->trainingGroups as $g) {
                $count += $groupSwimmerCounts[$g->id] ?? 0;
            }
            $count += $sessionIndividualCounts[$this->id] ?? 0;
            if ($this->recurrence_group_id) {
                $count += $seriesIndividualCounts[$this->recurrence_group_id] ?? 0;
            }
            return $count;
        }

        // Precise fallback (for single-session context, avoids N+1)
        $this->loadMissing('trainingGroups');
        $ids = collect();
        foreach ($this->trainingGroups as $g) {
            $ids = $ids->merge($g->swimmers()->where('users.active', true)->pluck('users.id'));
        }
        $ids = $ids->merge(
            \App\Models\TrainingSessionSwimmer::where('training_session_id', $this->id)->pluck('user_id')
        );
        if ($this->recurrence_group_id) {
            $ids = $ids->merge(
                \App\Models\TrainingSessionSwimmer::where('recurrence_group_id', $this->recurrence_group_id)->pluck('user_id')
            );
        }
        return $ids->unique()->count();
    }

    /** Available spots for guest bookings: returns null if no limit set. */
    public function availableSpotsForGuests(): ?int
    {
        if ($this->max_participants === null) return null;

        $preAbsentCount = $this->attendances()->where('pre_absent', true)->count();
        $baseCount      = $this->expectedParticipantCount();
        $available      = $this->max_participants - ($baseCount - $preAbsentCount);
        return max(0, $available);
    }

    public function getHasMissingTrainerAttribute(): bool
    {
        if ($this->relationLoaded('coTrainers')) {
            return $this->coTrainers->isEmpty();
        }
        return !$this->coTrainers()->exists();
    }
}
