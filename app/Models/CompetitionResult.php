<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    protected $fillable = [
        'competition_id', 'user_id', 'discipline', 'distance', 'time_ms',
        'placement', 'is_personal_best', 'age_group', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_personal_best' => 'boolean',
            'distance' => 'integer',
            'time_ms' => 'integer',
            'placement' => 'integer',
        ];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedTimeAttribute(): string
    {
        return SwimmingTime::formatMs($this->time_ms);
    }

    public function getDisciplineLabelAttribute(): string
    {
        return match($this->discipline) {
            'freistil' => 'Freistil',
            'brust' => 'Brust',
            'ruecken' => 'Rücken',
            'schmetterling' => 'Schmetterling',
            'lagen' => 'Lagen',
            default => $this->discipline,
        };
    }
}
