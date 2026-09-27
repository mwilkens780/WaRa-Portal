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

    // Die Gruppenfarben stehen in TrainingGroup::COLORS - hier keine zweite,
    // unvollstaendige Liste mehr fuehren (siehe colorHex() dort).

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

    /**
     * Hintergrundfarbe eines Blocks im Belegungsplan.
     *
     * Reihenfolge: eigene Farbe der Belegung, sonst die Farbe der
     * Trainingsgruppe genau wie in der Gruppendefinition, sonst die Farbe der
     * Belegungsart (Kurs, Schule, externer Verein, Wartung).
     */
    public function getDisplayColorAttribute(): string
    {
        if ($this->color) return $this->color;
        if ($this->trainingGroup) {
            return TrainingGroup::colorHex($this->trainingGroup->color);
        }

        return self::TYPE_COLORS[$this->type] ?? self::TYPE_COLORS['other'];
    }

    /**
     * Schriftfarbe, die auf dieser Hintergrundfarbe lesbar ist.
     *
     * Auf Weiß/Pink oder Gelb war weisse Schrift praktisch unlesbar - genau
     * deshalb war von manchen Bloecken keine Beschriftung zu erkennen.
     * Gerechnet wird mit der wahrgenommenen Helligkeit (ITU-R BT.601).
     */
    public function getTextColorAttribute(): string
    {
        return self::readableTextColor($this->display_color);
    }

    public static function readableTextColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#ffffff';

        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $helligkeit  = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $helligkeit > 0.62 ? '#1f2937' : '#ffffff';
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
            'recurrence_group_id'  => $this->trainingSession?->recurrence_group_id,
            'notes'                => $this->notes,
            'display_color'        => $this->display_color,
            'text_color'           => $this->text_color,
            'group_color'          => $this->trainingGroup?->color,
            'start_slot'           => $this->start_slot,
            'duration_slots'       => $this->duration_slots,
            'has_missing_trainer'  => $this->has_missing_trainer,
        ];
    }
}
