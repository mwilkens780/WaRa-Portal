<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    protected $fillable = [
        'competition_id', 'user_id', 'discipline', 'distance', 'time_ms',
        'placement', 'is_personal_best', 'is_season_best', 'age_group', 'wertungen', 'gender', 'notes',
        'breaks_vereinsrekord', 'breaks_landesrekord', 'is_final', 'wa_points', 'wa_table_year',
    ];

    protected function casts(): array
    {
        return [
            'is_personal_best'     => 'boolean',
            'is_season_best'       => 'boolean',
            'breaks_vereinsrekord' => 'boolean',
            'breaks_landesrekord'  => 'boolean',
            'is_final'             => 'boolean',
            'distance'             => 'integer',
            'time_ms'              => 'integer',
            'placement'            => 'integer',
            'wertungen'            => 'array',
            'wa_points'            => 'integer',
            'wa_table_year'        => 'integer',
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
            'F' => 'Freistil',
            'B' => 'Brust',
            'R' => 'Rücken',
            'S' => 'Schmetterling',
            'L' => 'Lagen',
            default => $this->discipline,
        };
    }
}
