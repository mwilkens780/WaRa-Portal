<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trainingsserie - Quelle fuer alles Regelmaessige (docs/konzept-trainingsserien.md).
 *
 * Die ID ist die recurrence_group_id der Einheiten. Einheiten tragen (noch) eine
 * Kopie der Serienwerte; bewusste Abweichungen stehen in overridden_fields.
 */
class TrainingSeries extends Model
{
    protected $table = 'training_series';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'season_id', 'title', 'type', 'day_of_week', 'start_time', 'end_time', 'location',
        'recurrence_type', 'valid_from', 'valid_until', 'skip_holidays',
        'max_participants', 'registration_open', 'guest_group_id', 'notes', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week'       => 'integer',
            'valid_from'        => 'date',
            'valid_until'       => 'date',
            'skip_holidays'     => 'boolean',
            'registration_open' => 'boolean',
        ];
    }

    /** Felder, die eine Einheit von der Serie erbt (und bewusst abweichen darf) */
    public const INHERITED = ['title', 'type', 'start_time', 'end_time', 'location', 'notes', 'max_participants', 'registration_open', 'guest_group_id'];

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'recurrence_group_id')->orderBy('date');
    }

    public function trainingGroups(): BelongsToMany
    {
        return $this->belongsToMany(TrainingGroup::class, 'training_series_group');
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_series_trainers');
    }

    public function hallBookings(): HasMany
    {
        return $this->hasMany(HallBooking::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function guestGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'guest_group_id');
    }
}
