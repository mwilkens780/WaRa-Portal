<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BestListEntry extends Model
{
    protected $fillable = [
        'list_type', 'discipline', 'distance', 'gender', 'birth_year',
        'course', 'set_year', 'swimmer_name', 'user_id', 'time_ms',
        'set_date', 'location', 'competition_result_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'set_date'   => 'date',
            'time_ms'    => 'integer',
            'distance'   => 'integer',
            'birth_year' => 'integer',
            'set_year'   => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function competitionResult()
    {
        return $this->belongsTo(CompetitionResult::class);
    }

    public function getFormattedTimeAttribute(): string
    {
        return SwimmingTime::formatMs($this->time_ms);
    }

    public function getDisciplineLabelAttribute(): string
    {
        return match($this->discipline) {
            'F' => 'Freistil',
            'B' => 'Brust',
            'R' => 'Rücken',
            'S' => 'Schmetterling',
            'L' => 'Lagen',
            default => $this->discipline,
        };
    }
}
