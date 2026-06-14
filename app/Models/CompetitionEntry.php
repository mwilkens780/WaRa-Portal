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
        $ms  = $this->entry_time_ms;
        $min = intdiv($ms, 60_000);
        $sec = intdiv($ms % 60_000, 1_000);
        $hun = intdiv($ms % 1_000, 10);
        return $min > 0
            ? sprintf('%d:%02d,%02d', $min, $sec, $hun)
            : sprintf('%d,%02d', $sec, $hun);
    }

    public function isEntered(): bool
    {
        return $this->status === 'entered';
    }
}
