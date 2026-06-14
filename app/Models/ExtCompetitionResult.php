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
        $ms  = $this->time_ms;
        $min = intdiv($ms, 60_000);
        $sec = intdiv($ms % 60_000, 1_000);
        $hun = intdiv($ms % 1_000, 10);
        return $min > 0
            ? sprintf('%d:%02d,%02d', $min, $sec, $hun)
            : sprintf('%d,%02d', $sec, $hun);
    }
}
