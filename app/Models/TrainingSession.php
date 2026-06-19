<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingSession extends Model
{
    use HasFactory, Auditable;

    public function getAuditLabel(): string
    {
        return ($this->title ?? '–') . ' (' . ($this->date?->format('d.m.Y') ?? '') . ')';
    }

    protected $fillable = [
        'title', 'date', 'start_time', 'end_time', 'location', 'type', 'notes',
        'recurrence_type', 'recurrence_until', 'recurrence_group_id',
        'team_plan_path', 'individual_plan_path',
        'max_participants', 'registration_open', 'guest_group_id',
    ];

    protected function casts(): array
    {
        return [
            'date'              => 'date',
            'recurrence_until'  => 'date',
            'registration_open' => 'boolean',
        ];
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
            default          => $this->type,
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
