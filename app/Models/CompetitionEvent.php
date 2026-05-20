<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionEvent extends Model
{
    protected $fillable = [
        'competition_id', 'event_number', 'session_number', 'session_date',
        'session_name', 'discipline', 'distance', 'gender', 'age_min', 'age_max', 'age_group',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'distance'     => 'integer',
            'age_min'      => 'integer',
            'age_max'      => 'integer',
        ];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
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
        return match($this->gender) {
            'M' => 'Männlich',
            'F' => 'Weiblich',
            default => 'Mixed',
        };
    }

    public function getLabelAttribute(): string
    {
        $parts = [$this->distance . ' m', $this->discipline_label];
        if ($this->age_group) $parts[] = $this->age_group;
        if ($this->gender !== 'X') $parts[] = $this->gender === 'M' ? 'M' : 'W';
        return implode(' · ', $parts);
    }
}
