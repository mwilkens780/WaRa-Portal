<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $fillable = [
        'type', 'discipline', 'distance', 'gender', 'age_group', 'course',
        'swimmer_name', 'user_id', 'time_ms', 'set_date', 'location',
        'competition_result_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'set_date'   => 'date',
            'time_ms'    => 'integer',
            'distance'   => 'integer',
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
            'freistil'      => 'Freistil',
            'brust'         => 'Brust',
            'ruecken'       => 'Rücken',
            'schmetterling' => 'Schmetterling',
            'lagen'         => 'Lagen',
            default         => $this->discipline,
        };
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'M' ? 'Männlich' : 'Weiblich';
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'vereinsrekord' ? 'VR' : 'LR';
    }

    public function getAgeGroupLabelAttribute(): string
    {
        return $this->age_group ?: 'Offene Klasse';
    }
}
