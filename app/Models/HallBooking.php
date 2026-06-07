<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallBooking extends Model
{
    protected $fillable = [
        'hall_resource_id', 'day_of_week', 'start_time', 'end_time',
        'label', 'type', 'training_group_id', 'trainer_id',
        'training_session_id', 'notes', 'color', 'created_by_id',
    ];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    const TYPE_LABELS = [
        'training'    => 'Training',
        'course'      => 'Kurs',
        'school'      => 'Schule',
        'external'    => 'Ext. Verein',
        'maintenance' => 'Wartung',
        'other'       => 'Sonstiges',
    ];

    const TYPE_COLORS = [
        'training'    => '#3B82F6',
        'course'      => '#8B5CF6',
        'school'      => '#F59E0B',
        'external'    => '#10B981',
        'maintenance' => '#6B7280',
        'other'       => '#9CA3AF',
    ];

    // Maps TrainingGroup::COLORS keys → hex
    const GROUP_COLOR_HEX = [
        'blue'   => '#3B82F6',
        'green'  => '#10B981',
        'red'    => '#EF4444',
        'orange' => '#F97316',
        'purple' => '#8B5CF6',
        'teal'   => '#14B8A6',
        'pink'   => '#EC4899',
        'indigo' => '#6366F1',
    ];

    const DAY_NAMES = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
        4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(HallResource::class, 'hall_resource_id');
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getDisplayColorAttribute(): string
    {
        if ($this->color) return $this->color;
        if ($this->trainingGroup) {
            return self::GROUP_COLOR_HEX[$this->trainingGroup->color] ?? '#3B82F6';
        }
        return self::TYPE_COLORS[$this->type] ?? '#9CA3AF';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Sonstiges';
    }

    public function getDayNameAttribute(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? '';
    }

    public function getFormattedTimeAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' – ' . substr($this->end_time, 0, 5);
    }

    const SCHEDULE_START_MIN = 330; // 05:30

    /** Zero-based slot index from 05:30 (1 slot = 15 min) */
    public function getStartSlotAttribute(): int
    {
        [$h, $m] = explode(':', $this->start_time);
        return ((int)$h * 60 + (int)$m - self::SCHEDULE_START_MIN) / 15;
    }

    public function getDurationSlotsAttribute(): int
    {
        [$sh, $sm] = explode(':', $this->start_time);
        [$eh, $em] = explode(':', $this->end_time);
        return ((int)$eh * 60 + (int)$em - (int)$sh * 60 - (int)$sm) / 15;
    }

    public function getHasMissingTrainerAttribute(): bool
    {
        return $this->training_group_id !== null && !$this->trainer_id;
    }

    /** Serialized form for Alpine.js data island */
    public function toGridArray(): array
    {
        return [
            'id'                   => $this->id,
            'hall_resource_id'     => $this->hall_resource_id,
            'day_of_week'          => $this->day_of_week,
            'start_time'           => substr($this->start_time, 0, 5),
            'end_time'             => substr($this->end_time, 0, 5),
            'label'                => $this->label,
            'type'                 => $this->type,
            'type_label'           => $this->type_label,
            'training_group_id'    => $this->training_group_id,
            'group_name'           => $this->trainingGroup?->name,
            'trainer_id'           => $this->trainer_id,
            'trainer_name'         => $this->trainer?->name,
            'training_session_id'  => $this->training_session_id,
            'session_title'        => $this->trainingSession?->title,
            'notes'                => $this->notes,
            'display_color'        => $this->display_color,
            'start_slot'           => $this->start_slot,
            'duration_slots'       => $this->duration_slots,
            'has_missing_trainer'  => $this->has_missing_trainer,
        ];
    }
}
