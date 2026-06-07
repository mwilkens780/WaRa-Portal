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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurrence_until' => 'date',
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
            'kondition'      => 'Kondition',
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

    public function hallBookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HallBooking::class, 'training_session_id');
    }

    public function getHasMissingTrainerAttribute(): bool
    {
        if ($this->relationLoaded('coTrainers')) {
            return $this->coTrainers->isEmpty();
        }
        return !$this->coTrainers()->exists();
    }
}
