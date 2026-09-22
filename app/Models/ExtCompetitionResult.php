<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtCompetitionResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'competition_id', 'athlete_id', 'discipline', 'distance',
        'time_ms', 'status', 'placement', 'age_group', 'gender',
        'is_final', 'dsv_points',
    ];

    protected function casts(): array
    {
        return ['is_final' => 'boolean'];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function getFormattedTimeAttribute(): string
    {
        if (!$this->time_ms) return '–';
        return SwimmingTime::formatMs($this->time_ms);
    }
}
