<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionRelayEntry extends Model
{
    public $timestamps = false;

    const GENDER_LABELS = [
        'M'     => 'Männlich',
        'F'     => 'Weiblich',
        'mixed' => 'Mixed',
    ];

    protected $fillable = [
        'competition_id', 'competition_event_id', 'discipline', 'distance',
        'gender', 'age_group', 'entry_time_ms', 'status', 'notes', 'created_by_id',
    ];

    public function getGenderLabelAttribute(): string
    {
        return self::GENDER_LABELS[$this->gender] ?? $this->gender;
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionEvent()
    {
        return $this->belongsTo(CompetitionEvent::class);
    }

    public function members()
    {
        return $this->hasMany(CompetitionRelayEntryMember::class, 'relay_entry_id')
                    ->orderBy('position');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
