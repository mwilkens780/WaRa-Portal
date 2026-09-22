<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionEntry extends Model
{
    protected $fillable = [
        'competition_id', 'user_id', 'competition_event_id',
        'discipline', 'distance', 'gender', 'age_group',
        'entry_time_ms', 'status', 'created_by_id',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function competitionEvent()
    {
        return $this->belongsTo(CompetitionEvent::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getEntryTimeFormattedAttribute(): string
    {
        if (!$this->entry_time_ms) return '–';
        return SwimmingTime::formatMs($this->entry_time_ms);
    }

    public function isEntered(): bool
    {
        return $this->status === 'entered';
    }
}
